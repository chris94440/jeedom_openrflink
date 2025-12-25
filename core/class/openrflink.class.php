<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class openrflink extends eqLogic {

    public static function cronDaily() {
        openrflink::check('daily');
    }

    public static function check($type = 'default') {
        $xml = new DOMDocument();
        $gateway = config::byKey('gateLib','openrflink');
        if ($type = 'install') {
            $release = '48';
            $version = '1';
        } else {
            $release = substr($gateway, -2);
            $version = substr($gateway, -9, 3);
        }
        $url = 'http://www.nemcon.nl/blog2/fw/update.jsp?ver=' . $version . '&rel=' . $release;
        $xml->load($url);
        log::add('openrflink','debug','Recherche firmware ' .  $url );

        if ($xml->getElementsByTagName('Value')->item(0)->nodeValue == 1) {
            //update dispo
            $file = file_get_contents($xml->getElementsByTagName('Url')->item(0)->nodeValue);
            $resource_path = realpath(dirname(__FILE__) . '/../../resources/openrflink/openrflink.cpp.hex');
            $release = str_replace("http://www.openrflink.nl/fw/","",str_replace("/openrflink.cpp.hex","",$xml->getElementsByTagName('Url')->item(0)->nodeValue));
            log::add('openrflink','debug','Download ' . $xml->getElementsByTagName('Url')->item(0)->nodeValue . ' in ' . $resource_path . ' for release ' . $release);
            exec('sudo rm ' . $resource_path);
            file_put_contents($resource_path,$file);
            config::save('avaLib', $release,  'openrflink');
            return true;
        }
    }

    public static function flashRF( ) {
        log::add('openrflink','info','Flash du openrflink');
        if (config::byKey('nodeGateway', 'openrflink') == 'none' || config::byKey('nodeGateway', 'openrflink') == '') {
            return true;
        }
        if (config::byKey('nodeGateway', 'openrflink') == 'acm') {
            $usbGateway = "/dev/ttyACM0";
        } else {
            $usbGateway = jeedom::getUsbMapping(config::byKey('nodeGateway', 'openrflink'));
        }
        $resource_path = realpath(dirname(__FILE__) . '/../../resources');
        config::save('flashing', '1',  'openrflink');
        openrflink::deamon_stop();
        exec('/usr/bin/avrdude -v -v -v -p atmega2560 -c wiring -D -P ' . $usbGateway . ' -b 115200 -U flash:w:' . $resource_path . '/openrflink/openrflink.cpp.hex:i > ' . log::getPathToLog('openrflink_flash') . ' 2>&1');
        config::save('flashing', '0',  'openrflink');
        return true;
    }

    public static function echoController( $command ) {
        if (config::byKey('nodeGateway', 'openrflink') == 'none' || config::byKey('nodeGateway', 'openrflink') == '') {
            return false;
        }

        log::add('openrflink', 'info', $command);
		$sockerPort=config::byKey('socketport', __CLASS__, '8020');
		
        $fp = fsockopen('127.0.0.1', $sockerPort, $errno, $errstr);
        if (!$fp) {
            echo "Service ne répond pas";
            return false;
        } else {
            fwrite($fp, $command);
            fclose($fp);
        }
    }

    public static function sendToController( $protocol, $id, $request ) {
        $nodeid = $protocol . '_' . $id;
        $id = str_pad($id, 6, "0", STR_PAD_LEFT);
        $msg = "10;" . $protocol . ";" . $id . ";" . $request . ";";
        log::add('openrflink', 'debug', $msg);
        openrflink::echoController($msg);

        //sauvegarde de la valeur envoyée
        $explode = explode(";", $request);
        $cmd = $explode[0];
        $value = $explode[1];
        if ($value == 'OFF') {
            $value = '0';
        } else if ($value == 'ON') {
            $value = '1';
        }

        $openrflink = self::byLogicalId($nodeid, 'openrflink');
        $openrflink->checkAndUpdateCmd($cmd, $value);
    }

    public function checkCmdOk($_id, $_name, $_subtype, $_value) {
        $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),$_id);
        if (!is_object($openrflinkCmd)) {
            log::add('openrflink', 'debug', 'Création de la commande ' . $_id);
            $openrflinkCmd = new openrflinkCmd();
            $cmds = $this->getCmd();
            $order = count($cmds);
            $openrflinkCmd->setOrder($order);
            $openrflinkCmd->setName(__($_name, __FILE__));
            $openrflinkCmd->setEqLogic_id($this->id);
            $openrflinkCmd->setEqType('openrflink');
            $openrflinkCmd->setLogicalId($_id);
            $openrflinkCmd->setType('info');
            $openrflinkCmd->setSubType($_subtype);
            $openrflinkCmd->setTemplate("mobile",'line' );
            $openrflinkCmd->setTemplate("dashboard",'line' );
            $openrflinkCmd->setDisplay("forceReturnLineAfter","1");
            $openrflinkCmd->setConfiguration('value',$_value);
            $openrflinkCmd->save();
        }
    }

    public function checkActOk($_id, $_name, $_subtype, $_cmdid, $_request, $_maxslider) {
        $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),$_id);
        if (!is_object($openrflinkCmd)) {
            log::add('openrflink', 'debug', 'Création de la commande ' . $_id);
            $openrflinkCmd = new openrflinkCmd();
            $cmds = $this->getCmd();
            $order = count($cmds);
            $openrflinkCmd->setOrder($order);
            $openrflinkCmd->setName(__($_name, __FILE__));
            $openrflinkCmd->setEqLogic_id($this->id);
            $openrflinkCmd->setEqType('openrflink');
            $openrflinkCmd->setLogicalId($_id);
            $openrflinkCmd->setType('action');
            $openrflinkCmd->setSubType($_subtype);
            if ($_maxslider != '0') {
                $openrflinkCmd->setConfiguration('minValue', 0);
                $openrflinkCmd->setConfiguration('maxValue', $_maxslider);
            }
            $openrflinkCmd->setConfiguration('id',$_cmdid);
            $openrflinkCmd->setConfiguration('request',$_request);
            $openrflinkCmd->save();
        }
    }

    public function checkInstall() {
        $protocol = 'openrflink';
        $id = 'gateway';
        $nodeid = $protocol . '_' . $id;
        $openrflink = self::byLogicalId($nodeid, 'openrflink');
        if (!is_object($openrflink)) {
            $openrflink = new openrflink();
            $openrflink->setEqType_name('openrflink');
            $openrflink->setLogicalId($nodeid);
            $openrflink->setConfiguration('id', $id);
            $openrflink->setConfiguration('protocol',$protocol);
            $openrflink->setName($nodeid);
            $openrflink->save();
        }
        $openrflink->checkActOk('debugon', 'Activer Debug', 'other', 'openrflink', '10;RFUDEBUG=ON;', '0');
        $openrflink->checkActOk('debugoff', 'Désactiver Debug', 'other', 'openrflink', '10;RFUDEBUG=OFF;', '0');
        $openrflink->checkActOk('reboot', 'Reboot', 'other', 'openrflink', '10;REBOOT;', '0');
        openrflink::check('install');
    }

    public function checkHexaCmd($_cmd, $_value) {
        $hexacmd = 'TEMP,BARO,UV,LUX,,RAIN,RAINRATE,WINSP,AWINSP,WINGS,WINCHL,WINTMP,KWATT,WATT';
        if (strpos($hexacmd,$_cmd) !== false) {
            $result = hexdec($_value);
        } else {
            $result = $_value;
        }
        return $result;
        log::add('openrflink', 'debug', 'HexaCmd ' . $_value . ' value ' . $result);
    }

    public function checkNumCmd($_cmd) {
        $numcmd = 'TEMP,HUM,BARO,HSTATUS,BFORECAST,UV,RAIN,RAINRATE,WINSP,AWINSP,WINGS,WINDIR,WINCHL,WINTMP,CHIME,CO2,SOUND,KWATT,WATT,DIST,METER,VOLT;CURRENT';
        if (strpos($numcmd,$_cmd) !== false) {
            $result = 'numeric';
        } else {
            $result = 'string';
        }
        return $result;
    }

    public function checkDivCmd($_cmd, $_value) {
        $divcmd = 'TEMP,RAIN,RAINRATE,RAINTOT,WINSP,WINCHL,WINTMP,AWINSP';
        if (strpos($divcmd,$_cmd) !== false) {
            $result = $_value/10;
        } else {
            $result = $_value;
        }
        return $result;
        log::add('openrflink', 'debug', 'DivCmd ' . $_value . ' value ' . $result);
    }

    public function registerRTS($_cmd, $_value) {
        //checkCmdOk($_id, $_name, $_subtype, $_value)
        //checkActOk($_id, $_name, $_subtype, $_cmdid, $_request, $_maxslider)
        $this->checkCmdOk($_cmd, 'Statut ' . $_cmd, 'string', $_value);
        $this->checkAndUpdateCmd($_cmd, $_value);
        $this->checkActOk('PAIR' . $_cmd, 'Appairement ' . $_cmd, $_cmd, 'PAIR', '0');
        $this->checkActOk('UP' . $_cmd, 'Montée ' . $_cmd, $_cmd, 'UP', 'UP', '0');
        $this->checkActOk('DOWN' . $_cmd, 'Descente ' . $_cmd, $_cmd, 'DOWN', 'DOWN', '0');
        $this->checkActOk('STOP' . $_cmd, 'Arret ' . $_cmd, $_cmd, 'STOP', 'STOP', '0');
    }

    public function registerMilightv1($_cmd, $_value, $_rgbw) {
        //checkCmdOk($_id, $_name, $_subtype, $_value)
        //checkActOk($_id, $_name, $_subtype, $_cmdid, $_request, $_maxslider)
        $this->checkCmdOk($_cmd, 'Etat Lampe ' . $_cmd, 'string', $_value);
        $this->checkAndUpdateCmd($_cmd, $_value);
        $this->checkActOk('ON' . $_cmd, 'On ' . $_cmd, 'other', $_cmd, 'ON', '0');
        $this->checkActOk('ALLON' . $_cmd, 'All On ' . $_cmd, 'other', $_cmd, 'ALLON', '0');
        $this->checkActOk('OFF' . $_cmd, 'Off ' . $_cmd, 'other', $_cmd, 'OFF', '0');
        $this->checkActOk('ALLOFF' . $_cmd, 'All Off ' . $_cmd, 'other', $_cmd, 'ALLOFF', '0');

        $this->checkCmdOk('RGBW' . $_cmd, 'Couleur Lampe ' . $_cmd, 'string', $_rgbw);
        $this->checkAndUpdateCmd('RGBW' . $_cmd, $_rgbw);
        $this->checkCmdOk('color_val' . $_cmd, 'Couleur Valeur ' . $_cmd, 'string', substr($_rgbw, 0, 2));
        $this->checkAndUpdateCmd('color_val' . $_cmd, substr($_rgbw, 0, 2));
        $this->checkActOk('COLOR' . $_cmd, 'Couleur ' . $_cmd, 'slider', $_cmd, 'COLOR', '255');
        $this->checkCmdOk('bright_val' . $_cmd, 'Luminosité Valeur ' . $_cmd, 'string', substr($_rgbw, -2));
        $this->checkAndUpdateCmd('bright_val' . $_cmd, substr($_rgbw, -2));
        $this->checkActOk('BRIGHT' . $_cmd, 'Luminosité ' . $_cmd, 'slider', $_cmd, 'BRIGHT', '32');
    }

    public function setColorMilight($_id, $_logid, $_value) {
        //change value color or brightness with _logid and _id, then change rgbw value
        //then take the request value and replace #color# by rgbw value
        //return rgbw value
        if (strpos($_logid, 'COLOR') !== false) {
            $color = substr(dechex($_value),-2);
            $this->checkAndUpdateCmd('color_val' . $_id, $color);
            $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),'bright_val'.$_id);
            $bright = $openrflinkCmd->getConfiguration('value');
        } else if (strpos($_logid, 'BRIGHT') !== false) {
            $bright = substr(dechex($_value*8),-2);
            $this->checkAndUpdateCmd('bright_val' . $_id, $bright);
            $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),'color_val'.$_id);
            $color = $openrflinkCmd->getConfiguration('value');
        } else {
            $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),'color_val'.$_id);
            $color = $openrflinkCmd->getConfiguration('value');
            $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),'bright_val'.$_id);
            $bright = $openrflinkCmd->getConfiguration('value');
        }
        $this->checkAndUpdateCmd('RGBW' . $_id, $color.$bright);
        $openrflinkCmd = openrflinkCmd::byEqLogicIdAndLogicalId($this->getId(),$_logid);
        $request = $color . $bright . ';' . $openrflinkCmd->getConfiguration('request');
        $this->checkAndUpdateCmd($_id, $openrflinkCmd->getConfiguration('request'));
        log::add('openrflink', 'debug', 'Request Milight : ' . $request);
        return $request;
    }

    public function registerSwitch($_cmd, $_value) {
        //checkCmdOk($_id, $_name, $_subtype, $_value)
        //checkActOk($_id, $_name, $_subtype, $_cmdid, $_request, $_maxslider)
        if ($_cmd[0] == '0' && strlen($_cmd) > 1) {
            //supp les 0 en début de switch
            $_cmd = ltrim($_cmd, "0");
            $_cmd = ($_cmd == '') ? '0' : $_cmd;
        }
        $binary = ($_value == 'OFF') ? '0' : '1';
        $this->checkCmdOk($_cmd, 'Statut ' . $_cmd, 'binary', $binary);
        $this->checkAndUpdateCmd($_cmd, $binary);
        $this->checkActOk($_value . $_cmd, $_value . ' ' . $_cmd, 'other', $_cmd, $_value, '0');
    }

    public function registerDimmer($_cmd, $_value) {
        //checkCmdOk($_id, $_name, $_subtype, $_value)
        //checkActOk($_id, $_name, $_subtype, $_cmdid, $_request, $_maxslider)
        if ($_cmd[0] == '0' && strlen($_cmd) > 1) {
            //supp les 0 en début de switch
            $_cmd = ltrim($_cmd, "0");
            $_cmd = ($_cmd == '') ? '0' : $_cmd;
        }
        $binary = ($_value == 'OFF') ? '0' : '1';
        $logicalCmdId = $_cmd . 'Level';
        $this->checkCmdOk($logicalCmdId, 'Level ' . $_cmd, 'numeric', $_value);
        $this->checkAndUpdateCmd($logicalCmdId, $_value);
        //$this->checkActOk($_value . $logicalCmdId, $_value . ' ' . $logicalCmdId, 'other', $logicalCmdId, $_value, '0');
    }

    public function registerBattery($_value) {
        $battery = ($_value == 'LOW') ? 10 : 100;
        $this->batteryStatus($battery);
        $this->save();
        log::add('openrflink', 'debug', 'Batterie ' . $_value . ' value ' . $battery);
    }

    public function registerInfo($_cmd, $_value) {
        // calcul valeur pour la temp et autres cas particuliers
        log::add('openrflink', 'debug', 'Commande capteur ' . $_cmd . ' value ' . $_value);
        if ($_cmd != '') {
            if ($_cmd == 'TEMP' || $_cmd == 'WINCHL' || $_cmd == 'WINTMP') {
                if (substr($_value,0,1) != 0) {
                    $_value = '-' . hexdec(substr($_value, -3));
                } else {
                    $_value = hexdec(substr($_value, -3));
                }
            } else {
                $_value = $this->checkHexaCmd($_cmd,$_value);
            }
            $_value = $this->checkDivCmd($_cmd,$_value);
            $cmds = $this->getCmd();
            $this->checkCmdOk($_cmd, $_cmd . ' - ' . count($cmds), openrflink::checkNumCmd($_cmd), $_value);
            $this->checkAndUpdateCmd($_cmd, $_value);
        }
    }

    public function setopenrflinkStatus($_data) {
        log::add('openrflink', 'debug', 'Status ' . $_data);
        $datas = explode(";", $_data);
        $i = 0;
        $openrflink = openrflink::byLogicalId('openrflink_gateway','openrflink');
        if (!is_object($openrflink)) {
            return false;
        }
        foreach ($datas as $info) {
            if ($i > 2) {
                if (strpos($info,'=') !== false) {
                    $arg = explode("=", $info);
                    log::add('openrflink', 'debug', 'Status ' . $arg[0] . ' is ' . $arg[1]);
                    $openrflink->checkCmdOk($arg[0], $arg[0], 'string', $arg[1]);
                    $openrflink->checkAndUpdateCmd($arg[0], $arg[1]);
                    $openrflink->checkActOk($arg[0] . 'off', $arg[0] . ' Off', 'other', '0', '10;' . $arg[0] . '=OFF;', '0');
                    $openrflink->checkActOk($arg[0] . 'on', $arg[0] . ' On', 'other', '0', '10;' . $arg[0] . '=ON;', '0');
                }
            }
            $i++;
        }
    }

    public static function receiveData($json) {
        log::add('openrflink', 'debug', 'Body ' . print_r($json,true));
        $body = json_decode($json, true);
        $data = $body['data'];
        if (strpos($data,'DEBUG') !== false) {
            log::add('openrflink', 'debug', 'Trame de debug recue : ' . $data);
            return false;
        }

        $datas = explode(";", $data);

        if (strpos($data,'Nodo RadioFrequencyLink') !== false) {
            config::save('gateLib', $datas[2],  'openrflink');
            return false;
        }

        if ($datas[0] == '10') {
            //envoi de données, on va pas plus loin
            return false;
        }

        $protocol = $datas[2];

        if ($protocol == 'STATUS') {
            //status line need special treatment
            openrflink::setopenrflinkStatus($data);
            return true;
        }

        if (strpos($datas[3],'ID=') !== false) {
            $id = str_replace('ID=', '', $datas[3]);
        } else {
            log::add('openrflink', 'debug', 'Trame non utilisable ' . $data);
            return false;
        }
        //reduire ID sans les 0 de début si plus de 6 caractères
        if ($id[0] == '0' && strlen($id) > 6) {
            $id = ltrim($id, "0");
        }
        $nodeid = $protocol . '_' . $id;
        log::add('openrflink', 'debug', 'Protocole ' . $protocol . ' ID ' . $id);

        $openrflink = self::byLogicalId($nodeid, 'openrflink');
        if (!is_object($openrflink) && config::byKey('include_mode', 'openrflink') == '1') {
            $openrflink = new openrflink();
            $openrflink->setEqType_name('openrflink');
            $openrflink->setLogicalId($nodeid);
            $openrflink->setConfiguration('id', $id);
            $openrflink->setConfiguration('protocol',$protocol);
            $openrflink->setName($nodeid);
            $openrflink->save();
            event::add('openrflink::includeDevice',
            array(
                'state' => 1
            )
        );
    }

    if (!is_object($openrflink)) {
        return false;
    }

    $openrflink = self::byLogicalId($nodeid, 'openrflink');
    $openrflink->setStatus('lastCommunication',date('Y-m-d H:i:s'));
    $openrflink->save();

    $i=0;
    $args = array();
    foreach ($datas as $info) {
        if ($i > 3) {
            if (strpos($info,'=') !== false) {
                $arg = explode("=", $info);

                if(count($arg) > 2)
                {
                    // set_level cmd, CMD=SET_LEVEL=2
                    $args[$arg[0]] = $arg[1] . '=' . $arg[2];
                } else
                {
                    $args[$arg[0]] = $arg[1];
                }
            }
        }
        $i++;
    }
    foreach ($args as $type => $value) {
        log::add('openrflink', 'debug', 'Commande ' . $type . ' value ' . $value);
        switch ($type) {
            case 'SWITCH' :
            switch ($protocol) {
                case 'RTS' :
                $openrflink->registerRTS($value,$args['CMD']);
                break;
                case 'MiLightv1' :
                $openrflink->registerMilightv1($value,$args['CMD'],$args['RGBW']);
                break;
                default :
                if(strpos($args['CMD'], 'SET_LEVEL') !==false)
                {
                    $openrflink->registerDimmer($value, str_replace('SET_LEVEL=', '', $args['CMD']));
                } else
                {
                    $openrflink->registerSwitch($value,$args['CMD']);
                }
                //SWITCH=00;CMD=OFF
                break;
            }
            break;
            case 'CMD' :
            //nothing, it's part of Switch
            break;
            case 'RGBW' :
            //nothing, it's part of Switch
            break;
            case 'BAT' :
            $openrflink->registerBattery($value);
            $openrflink->registerInfo($type,$value);
            break;
            default :
            $openrflink->registerInfo($type,$value);
            break;
        }
    }
}

public static function saveInclude($mode) {
    config::save('include_mode', $mode,  'openrflink');
    $state = 1;
    if ($mode == 1) {
        $state = 0;
    }
    event::add('openrflink::controller.data.controllerState',
    array(
        'state' => $state
    )
);
}

public function preSave() {
    $this->setLogicalId($this->getConfiguration('protocol') . '_' . $this->getConfiguration('id'));
}

public static function deamon_info() {
    $return = array();
    $return['log'] = 'openrflink_node';
    $return['state'] = 'nok';
    $pid = trim( shell_exec ('ps ax | grep "openrflink/resources/openrflink.js" | grep -v "grep" | wc -l') );
    if ($pid != '' && $pid != '0') {
        $return['state'] = 'ok';
    }
    $return['launchable'] = 'ok';
    if (config::byKey('nodeGateway', 'openrflink') == 'none' || config::byKey('nodeGateway','openrflink') == '') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Le port USB n\'est pas configuré', __FILE__);
    }
    if (config::byKey('flashing', 'openrflink') == '1') {
        $return['launchable'] = 'nok';
        $return['launchable_message'] = __('Flash en cours', __FILE__);
    }
    return $return;
}

public static function deamon_start() {
    self::deamon_stop();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
        throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }
    log::add('openrflink', 'info', 'Lancement du démon openrflink');

    if (config::byKey('nodeGateway', 'openrflink') == 'acm') {
        $usbGateway = "/dev/ttyACM0";
    } else if (config::byKey('nodeGateway', 'openrflink') == 'network') {
        $usbGateway = 'network';
    } else {
        $usbGateway = jeedom::getUsbMapping(config::byKey('nodeGateway', 'openrflink'));
    }
    if ($usbGateway == '' ) {
        throw new Exception(__('Le port : n\'existe pas', __FILE__));
    }

    $net = config::byKey('netGateway', 'openrflink', 'none');

    //$url = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/openrflink/core/api/openrflink.php?apikey=' . jeedom::getApiKey('openrflink');
  	$url = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/openrflink/core/api/openrflink.php';
  
  	$sockerPort=config::byKey('socketport', __CLASS__, '8020');

    $log = log::convertLogLevel(log::getLogLevel('openrflink'));

    $sensor_path = realpath(dirname(__FILE__) . '/../../resources');
    if ($usbGateway != "none") {
        exec('sudo chmod -R 777 ' . $usbGateway);
    }
    //$cmdOld = 'nice -n 19 node ' . $sensor_path . '/openrflink.js ' . $url . ' ' . $usbGateway . ' ' . $net . ' ' . $log;
  	//$cmd = 'node ' . $sensor_path . '/openrflink.js ' . $url . ' --gwAddress ' . $usbGateway . ' --socketPort 8022' . ' ' . $log;
  	$cmd = 'nice -n 19 node ' . $sensor_path . '/openrflink.js --callback ' . $url . ' --gwAddress ' . $usbGateway . ' --socketPort '. $sockerPort.' --apiKey '. jeedom::getApiKey('openrflink');

    log::add('openrflink', 'debug', 'Lancement démon openrflink : ' . $cmd);

    $result = exec('nohup ' . $cmd . ' >> ' . log::getPathToLog('openrflink_node') . ' 2>&1 &');
    if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
        log::add('openrflink', 'error', $result);
        return false;
    }

    $i = 0;
    while ($i < 30) {
        $deamon_info = self::deamon_info();
        if ($deamon_info['state'] == 'ok') {
            break;
        }
        sleep(1);
        $i++;
    }
    if ($i >= 30) {
        log::add('openrflink', 'error', 'Impossible de lancer le démon openrflink, vérifiez le port', 'unableStartDeamon');
        return false;
    }
    message::removeAll('openrflink', 'unableStartDeamon');
    log::add('openrflink', 'info', 'Démon openrflink lancé');
    sleep(5);
    openrflink::echoController('10;STATUS;');
    return true;
}

public static function deamon_stop() {
    exec('kill $(ps aux | grep "openrflink/resources/openrflink.js" | awk \'{print $2}\')');
    log::add('openrflink', 'info', 'Arrêt du service openrflink');
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
        sleep(1);
        exec('kill -9 $(ps aux | grep "openrflink/resources/openrflink.js" | awk \'{print $2}\')');
    }
    $deamon_info = self::deamon_info();
    if ($deamon_info['state'] == 'ok') {
        sleep(1);
        exec('sudo kill -9 $(ps aux | grep "openrflink/resources/openrflink.js" | awk \'{print $2}\')');
    }
}

}

class openrflinkCmd extends cmd {

    public function execute($_options = null) {

        switch ($this->getType()) {
            case 'action' :
            $id = $this->getConfiguration('id');
            $request = $this->getConfiguration('request');
            $eqLogic = $this->getEqLogic();

            switch ($this->getSubType()) {
                case 'slider':
                if ($eqLogic->getConfiguration('protocol') == 'MiLightv1') {
                    $request = $eqLogic->setColorMilight($this->getConfiguration('id'),$this->getLogicalId(),$_options['slider']);
                } else {
                    $request = str_replace('#slider#', $_options['slider'], $request);
                }
                break;
                case 'color':
                $request = str_replace('#color#', $_options['color'], $request);
                break;
                case 'message':
                if ($_options != null)  {
                    $replace = array('#title#', '#message#');
                    $replaceBy = array($_options['title'], $_options['message']);
                    if ( $_options['title'] == '') {
                        throw new Exception(__('Le sujet ne peuvent être vide', __FILE__));
                    }
                    $request = str_replace($replace, $replaceBy, $request);
                } else {
                    $request = 1;
                }
                break;
                case 'other':
                if ($eqLogic->getConfiguration('protocol') == 'MiLightv1') {
                    $request = $eqLogic->setColorMilight($this->getConfiguration('id'),$this->getLogicalId(),'other');
                } else if ($eqLogic->getConfiguration('protocol') == 'openrflink') {
                    openrflink::echoController($request);
                    openrflink::echoController('10;STATUS;');
                    return true;
                } else {
                    $request = $request;
                    $binary = ($request == 'OFF' || $request == 'ALLOFF') ? '0' : '1';
                    $eqLogic->checkAndUpdateCmd($id, $binary);
                }
                break;
                default : $request == null ?  1 : $request;
            }

            if ($request != 'PAIR') {
              	// Récupérer le nombre d'exécutions
                $nbExecutions = $this->getEqLogic()->getConfiguration('cfgNbRepeat');
              
              	if ($nbExecutions == '') {
                	$nbExecutions=1;
                }

                // Boucle pour exécuter la commande
              	// $eqLogic->getConfiguration('protocol')== 'RTS'
              	log::add('openrflink', 'debug', '  - execute cmd ' . $nbExecutions. ' fois');
                for ($i = 0; $i < $nbExecutions; $i++) {
					openrflink::sendToController($eqLogic->getConfiguration('protocol') ,$eqLogic->getConfiguration('id') ,$id . ';' . $request );
                  	usleep(500000);
                }
              	
                } else {
                    openrflink::sendToController(
                        $eqLogic->getConfiguration('protocol') ,
                        $eqLogic->getConfiguration('id') ,
                        '0;ON' );

                        $id1 = dechex(hexdec($eqLogic->getConfiguration('id')) + 1);

                        openrflink::sendToController(
                            $eqLogic->getConfiguration('protocol') ,
                            $id1 ,
                            '0123;PAIR' );

                            openrflink::sendToController(
                                $eqLogic->getConfiguration('protocol') ,
                                $id1 ,
                                '0123;0;PAIR' );
                            }
                        }
                        return true;
                    }
                }
