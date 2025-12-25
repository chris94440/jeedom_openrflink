/**
 * Plugin RFlink pour Jeedom - Compatible Node.js 22
 * Conversion moderne avec ES6+ et modules actualisés
 */

const { SerialPort } = require('serialport');
const { ReadlineParser } = require('@serialport/parser-readline');
const net = require('net');
const axios = require('axios');

// Configuration
let config = {
  socketPort: 8020,
  jeedomUrl: '',
  apiKey: '',
  gwAddress: '',
  isNetwork: false,
  callbackUrl: ''
};

let serialPort = null;
let networkSocket = null;
let server = null;
let parser = null;
let isConnected = false;

/**
 * Logger avec timestamps
 */
function log(message, level = 'info') {
  const timestamp = new Date().toISOString();
  console.log(`[${timestamp}] [${level.toUpperCase()}] ${message}`);
}

/**
 * Initialisation depuis les arguments en ligne de commande
 */
function parseArguments() {
  const args = process.argv.slice(2);
  
  args.forEach((arg, index) => {
    if (arg === '--socketPort' && args[index + 1]) {
      config.socketPort = parseInt(args[index + 1]);
    } else if (arg === '--jeedomUrl' && args[index + 1]) {
      config.jeedomUrl = args[index + 1];
    } else if (arg === '--apiKey' && args[index + 1]) {
      config.apiKey = args[index + 1];
    } else if (arg === '--gwAddress' && args[index + 1]) {
      config.gwAddress = args[index + 1];
    } else if (arg === '--callback' && args[index + 1]) {
      config.callbackUrl = args[index + 1];
    }
  });

  // Détection du mode réseau vs série
  config.isNetwork = config.gwAddress.includes(':');
  
  log(`Configuration: Port=${config.socketPort}, Gateway=${config.gwAddress}, Mode=${config.isNetwork ? 'Réseau' : 'Série'}`);
}

/**
 * Envoie des données à Jeedom via l'API
 */
async function sendToJeedom(data) {
  if (!config.callbackUrl) {
    log('URL de callback non configurée', 'warn');
    return;
  }
  
  if (!config.apiKey) {
    log('Plugin apiKey non configurée', 'warn');
    return;
  }
  
  const url = `${config.callbackUrl}?apikey=${config.apiKey}&messagetype=saveValue&type=openrflink`;

  try {
    const response = await axios.put(url, { data: data.toString() }, {
      timeout: 5000,
      headers: { 'Content-Type': 'application/json' }
    });

    log(`Données envoyées à Jeedom:`);
    log(`	- url : ${url}`);
    log(`	- data : ${data.substring(0, 50)}...`);
    log(`		-> Resultat requete : ${response.status}`);

  } catch (error) {
    log(`Erreur lors de l'envoi à Jeedom: ${err.message}`, 'error');
  }
}

/**
 * Traite les données reçues du RFLink
 */
function processRFLinkData(data) {
  const trimmedData = data.trim();
  
  if (!trimmedData) return;
  
  log(`Données RFLink reçues: ${trimmedData}`);
  
  var datas = trimmedData.toString().split(";");
  var type = +datas[0];
  
  // decision on appropriate response
  log(`processRFLinkData | type: ${type} |trimmedData : ${trimmedData}`);
	switch (type) {
		case 20:
			log('processRFLinkData | sendToJeedom');
			sendToJeedom(trimmedData);
			break;
		default:
			// Handle all other types
			log(`processRFLinkData | unknown type: ${type}`);
			// Or add your default behavior here
			break;
	}
  
  // Broadcast aux clients connectés au serveur socket
  if (server) {
    server.getConnections((err, count) => {
      if (!err && count > 0) {
        log(`Broadcasting vers ${count} client(s)`);
      }
    });
  }
}


/**
 * Envoie une commande au RFLink
 */
function sendToRFLink(command) {
  if (!isConnected) {
    log('RFLink non connecté', 'error');
    return false;
  }

  const cmd = command.trim() + '\r\n';
  
  try {
    if (config.isNetwork && networkSocket) {
      networkSocket.write(cmd);
      log(`Commande envoyée (réseau): ${command}`);
    } else if (serialPort && serialPort.isOpen) {
      serialPort.write(cmd);
      log(`Commande envoyée (série): ${command}`);
    } else {
      log('Impossible d\'envoyer la commande, connexion fermée', 'error');
      return false;
    }
    return true;
  } catch (err) {
    log(`Erreur lors de l'envoi de la commande: ${err.message}`, 'error');
    return false;
  }
}

/**
 * Connexion au RFLink en mode série
 */
function connectSerial() {
  log(`Tentative de connexion série sur ${config.gwAddress}`);
  
  serialPort = new SerialPort({
    path: config.gwAddress,
    baudRate: 57600,
    autoOpen: false
  });

  parser = serialPort.pipe(new ReadlineParser({ delimiter: '\r\n' }));

  serialPort.on('open', () => {
    isConnected = true;
    log('Connexion série établie avec succès');
    
    // Demande d'informations au démarrage
    setTimeout(() => {
      sendToRFLink('10;VERSION;');
      sendToRFLink('10;STATUS;');
    }, 1000);
  });

  parser.on('data', (data) => {
    processRFLinkData(data);
  });

  serialPort.on('error', (err) => {
    log(`Erreur port série: ${err.message}`, 'error');
    isConnected = false;
    
    // Tentative de reconnexion après 5 secondes
    setTimeout(() => {
      log('Tentative de reconnexion...');
      connectSerial();
    }, 5000);
  });

  serialPort.on('close', () => {
    isConnected = false;
    log('Connexion série fermée', 'warn');
  });

  serialPort.open((err) => {
    if (err) {
      log(`Impossible d'ouvrir le port série: ${err.message}`, 'error');
      // Retry après 5 secondes
      setTimeout(() => connectSerial(), 5000);
    }
  });
}

/**
 * Connexion au RFLink en mode réseau
 */
function connectNetwork() {
  const [host, port] = config.gwAddress.split(':');
  
  log(`Tentative de connexion réseau sur ${host}:${port}`);
  
  networkSocket = new net.Socket();
  
  networkSocket.connect(parseInt(port), host, () => {
    isConnected = true;
    log('Connexion réseau établie avec succès');
    
    // Demande d'informations au démarrage
    setTimeout(() => {
      sendToRFLink('10;VERSION;');
      sendToRFLink('10;STATUS;');
    }, 1000);
  });

  networkSocket.on('data', (data) => {
    const lines = data.toString().split('\r\n');
	log(`connectNetwork | Reception donnée ${lines}`);
    lines.forEach(line => {
      if (line.trim()) {
        processRFLinkData(line);
      }
    });
  });

  networkSocket.on('error', (err) => {
    log(`Erreur connexion réseau: ${err.message}`, 'error');
    isConnected = false;
    
    // Tentative de reconnexion après 5 secondes
    setTimeout(() => {
      log('Tentative de reconnexion...');
      connectNetwork();
    }, 5000);
  });

  networkSocket.on('close', () => {
    isConnected = false;
    log('Connexion réseau fermée', 'warn');
    
    // Tentative de reconnexion après 5 secondes
    setTimeout(() => {
      log('Tentative de reconnexion...');
      connectNetwork();
    }, 5000);
  });
}

/**
 * Création du serveur socket pour recevoir les commandes de Jeedom
 */
function createSocketServer() {
  server = net.createServer((socket) => {
    log('Nouveau client connecté');
    
    socket.on('data', (data) => {
      const command = data.toString().trim();
      log(`Commande reçue du client: ${command}`);
      sendToRFLink(command);
    });
    
    socket.on('error', (err) => {
      log(`Erreur socket client: ${err.message}`, 'error');
    });
    
    socket.on('close', () => {
      log('Client déconnecté');
    });
  });

  server.listen(config.socketPort, '0.0.0.0', () => {
    log(`Serveur socket démarré sur le port ${config.socketPort}`);
  });

  server.on('error', (err) => {
    log(`Erreur serveur socket: ${err.message}`, 'error');
    process.exit(1);
  });
}

/**
 * Gestion de l'arrêt propre
 */
function gracefulShutdown() {
  log('Arrêt du service...');
  
  if (serialPort && serialPort.isOpen) {
    serialPort.close();
  }
  
  if (networkSocket) {
    networkSocket.destroy();
  }
  
  if (server) {
    server.close();
  }
  
  process.exit(0);
}

// Gestion des signaux d'arrêt
process.on('SIGTERM', gracefulShutdown);
process.on('SIGINT', gracefulShutdown);

// Gestion des erreurs non capturées
process.on('uncaughtException', (err) => {
  log(`Erreur non capturée: ${err.message}`, 'error');
  log(err.stack, 'error');
});

process.on('unhandledRejection', (reason, promise) => {
  log(`Promesse rejetée non gérée: ${reason}`, 'error');
});

/**
 * Point d'entrée principal
 */
function main() {
  log('=== Démarrage du service RFLink ===');
  
  // Parse les arguments
  parseArguments();
  
  // Validation de la configuration
  if (!config.gwAddress) {
    log('Adresse de la gateway non spécifiée', 'error');
    process.exit(1);
  }
  
  // Création du serveur socket
  createSocketServer();
  
  // Connexion au RFLink
  if (config.isNetwork) {
    connectNetwork();
  } else {
    connectSerial();
  }
  
  log('Service RFLink initialisé');
}

// Démarrage
main();