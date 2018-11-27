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

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class gds3710 extends eqLogic {
    /*     * *************************Attributs****************************** */
    
    /*     * ***********************Methode static*************************** */

    /*
     * Fonction exécutée automatiquement toutes les minutes par Jeedom
      public static function cron() {

      }
     */


    /*
     * Fonction exécutée automatiquement toutes les heures par Jeedom
      public static function cronHourly() {

      }
     */

    /*
     * Fonction exécutée automatiquement tous les jours par Jeedom
      public static function cronDaily() {

      }
     */

    public static function get_GDS3710_event_list()
    {
        $return = array (
            "100" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 100, "short_name" => "OpenDoorViaCard", "message" => "Open Door via Card", "use_case" => "Indicates that someone opens the door via card or key fob."),
            "101" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 101, "short_name" => "OpenDoorViaCardOverWiegand", "message" => "Open Door via Card (over Wiegand)", "use_case" => "Indicates that someone opens the door via card or key fob using Wiegand interface connected to GDS."),
            "200" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 200, "short_name" => "VisitingLog", "message" => "Visiting Log", "use_case" => "Indicates that door has been opened for visitor which pressed door bell button."),
            "300" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 300, "short_name" => "OpenDoorViaUniversalPIN", "message" => "Open Door via Universal PIN", "use_case" => "Indicates that door has been opened successfully using local PIN code via GDS keypad."),
            "301" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 301, "short_name" => "OpenDoorViaPrivatePIN", "message" => "Open Door via Private PIN", "use_case" => "Indicates that someone opened the door successfully using their private PIN code via GDS keypad."),
            "302" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 302, "short_name" => "OpenDoorViaGuestPIN", "message" => "Open Door via Guest PIN", "use_case" => "Indicates that a guest used “Guest PIN” code to open the door using GDS keypad."),
            "600" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 600, "short_name" => "OpenDoorViaCardandPIN", "message" => "Open Door via Card and PIN", "use_case" => "Indicates that someone used his RFID card or key fob, plus his own private password to authenticate and open the door."),
            "400" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 400, "short_name" => "OpenDoorViaDI", "message" => "Open Door via DI", "use_case" => "Indicates that door has been opened using DI (Digital Input) Signal, such as using a push button."),
            "700" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 700, "short_name" => "OpenDoorViaRemotePIN", "message" => "Open Door via Remote PIN", "use_case" => "Indicates that someone did send remote PIN code to open the door using GDS manager tool for example."),
            "800" => array("section" =>'Ouverture porte', 'section_icon'=>'jeedom-porte-ferme', "type" => 800, "short_name" => "HttpAPIOpenDoor", "message" => "HTTP API Open Door", "use_case" => "Indicates that someone did send remote PIN code to open the door HTTP API command."),
            
            "500" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 500, "short_name" => "CallOutLog", "message" => "Call Out Log", "use_case" => "Indicates the GDS unit initiated a call out, for example when someone uses the keypad to dial a number or press door bell button which preconfigured destination number."),
            "501" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 501, "short_name" => "CallInLog", "message" => "Call In Log", "use_case" => "Indicates that call has been received by the GDS unit."),
            "504" => array("section" =>'Appel', 'section_icon'=>'techno-phone16', "type" => 504, "short_name" => "CallLogDoorBellCall", "message" => "Call Log (Door Bell Call)", "use_case" => "Indicates that someone has initiated a call using door bell button."),
            
            "601" => array("section" =>'Maintient ouverture porte', 'section_icon'=>'jeedom-porte-ouverte', "type" => 601, "short_name" => "KeepDoorOpenImmediately", "message" => "Keep Door Open (Immediately)", "use_case" => "Key door Open (immediately) action has been performed from the web Interface."),
            "602" => array("section" =>'Maintient ouverture porte', 'section_icon'=>'jeedom-porte-ouverte', "type" => 602, "short_name" => "KeepDoorOpenScheduled", "message" => "Keep Door Open (Scheduled)", "use_case" => "Key door Open (immediately) action has been set from the web Interface and the event is triggered."),

            "900" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 900, "short_name" => "MotionDetection", "message" => "Motion Detection", "use_case" => "Indicates that motion detection is triggered."),
            "1000" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1000, "short_name" => "DIAlarm", "message" => "DI Alarm", "use_case" => "Indicates that alarm IN is triggered."),
            "1100" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1100, "short_name" => "DismantleByForce", "message" => "Dismantle by Force", "use_case" => "Indicates that the unit has been dismantled by force."),
            "1200" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1200, "short_name" => "HostageAlarm", "message" => "Hostage Alarm", "use_case" => "Indicates that someone has entered the hostage alarm PIN code to open the door."),
            "1300" => array("section" =>'Sécurité', 'section_icon'=>'securite-key1', "type" => 1300, "short_name" => "InvalidPassword", "message" => "Invalid Password", "use_case" => "Indicates that someone has entered wrong password PIN code to open the door for 5 attempts and corresponding alarm action has been triggered."),
            
            "1101" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1101, "short_name" => "SystemUp", "message" => "System up", "use_case" => "Indicates that the system is UP."),
            "1102" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1102, "short_name" => "Reboot", "message" => "Reboot", "use_case" => "Indicates that the GDS unit has been rebooted."),
            "1103" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1103, "short_name" => "ResetClearAllData", "message" => "Reset (Clear All Data) ", "use_case" => "Factory reset (clear all data) has been performed."),
            "1104" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1104, "short_name" => "ResetRetainNetworkDataOnly", "message" => "Reset (Retain Network Data Only)", "use_case" => "Factory reset (Retain Network Data Only) has been performed."),
            "1105" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1105, "short_name" => "ResetRetainOnlyCardInformation)", "message" => "Reset (Retain Only Card Information)", "use_case" => "Factory reset (Retain Only Card Information) has been performed."),
            "1106" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1106, "short_name" => "ResetRetainNetworkDataAndCardInformation)", "message" => "Reset (Retain Network Data and Card Information)", "use_case" => "Factory reset (Retain Network Data and Card Information) has been performed."),
            "1107" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1107, "short_name" => "ResetWiegand", "message" => "Reset (Wiegand)", "use_case" => "Factory reset using Wiegand module has been performed on the unit"),
            "1108" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1108, "short_name" => "ConfigUpdate", "message" => "Config Update", "use_case" => "Indicates that the system’s configuration has been updated."),
            "1109" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fa-exclamation-triangle', "type" => 1109, "short_name" => "FirmwareUpdate", "message" => "Firmware Update (1.0.0.0)", "use_case" => "Indicates that the system’s firmware has been upgraded."),
            
            "1400" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1400, "short_name" => "MainboardTemperatureNormal", "message" => "Mainboard Temperature(32°C) Normal", "use_case" => "Indicates that device’s mainboard temperature is normal, (around 32°C)."),
            "1401" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1401, "short_name" => "MainboardTemperatureTooLow", "message" => "Mainboard Temperature(32°C) Too Low", "use_case" => "Indicates that device’s mainboard temperature is too low."),
            "1402" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1402, "short_name" => "MainboardTemperatureTooHigh", "message" => "Mainboard Temperature(32°C) Too High", "use_case" => "Indicates that device’s mainboard temperature is too high."),
            "1403" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1403, "short_name" => "SensorTemperatureNormal", "message" => "Sensor Temperature(32°C) Normal", "use_case" => "Indicates that device's sensor temperature is normal, (around 32°C)."),
            "1404" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1404, "short_name" => "SensorTemperatureTooLow", "message" => "Sensor Temperature(32°C) Too Low", "use_case" => "Indicates that device's sensor temperature is normal too low."),
            "1405" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fa-thermometer-full', "type" => 1405, "short_name" => "SensorTemperatureTooHigh", "message" => "Sensor Temperature(32°C) Too High", "use_case" => "Indicates that device's sensor temperature is normal too high."),
        );
        return $return;
    }

    /*     * *********************Méthodes d'instance************************* */

    public function preInsert() {
        
    }

    public function postInsert() {
        
    }

    public function preSave() {
        
    }

    public function postSave() {
        // Création de la commande open
        $open = $this->getCmd(null, 'open');
        if (!is_object($open)) {
            $open = new gds3710Cmd();
            $open->setName(__('Ouvrir la porte', __FILE__));
        }
        $open->setEqLogic_id($this->getId());
        $open->setLogicalId('open');
        $open->setType('action');
        $open->setSubType('other');
        $open->setIsVisible(1);
        $open->save();

        // Création de la commande close
        $close = $this->getCmd(null, 'close');
        if (!is_object($close)) {
            $close = new gds3710Cmd();
            $close->setName(__('Fermer la porte', __FILE__));
        }
        $close->setEqLogic_id($this->getId());
        $close->setLogicalId('close');
        $close->setType('action');
        $close->setSubType('other');
        $close->setIsVisible(0);
        $close->save();


        // Création de la commande last event
        $info = $this->getCmd('info', 'Last event');
        if (!is_object($info)) {
            $info = new gds3710Cmd();   
            $info->setName(__('Last event', __FILE__));
            $info->setType('info');
            $info->setSubType('string');
            $info->setLogicalId('Last event');
            $info->setIsVisible(0);
            $info->setEqLogic_id($this->getId());
            $info->save();
        }

        $cmd_array = gds3710::get_GDS3710_event_list();
        
        foreach ($cmd_array as $row){
            $info = $this->getCmd('info', $row['short_name']);
            if (!is_object($info)) {
                $info = new gds3710Cmd();
                $info->setName(__($row['type'], __FILE__));
                $info->setType('info');
                $info->setSubType('string');
                $info->setIsVisible(0);
                $info->setLogicalId($row['short_name']);
                $info->setEqLogic_id($this->getId());
                $info->save();
            } 
        }

        $MAC = $this->getConfiguration('macaddress');
        if($MAC != ''){
            $this->setLogicalId(strtolower($MAC));
            $this->save(true);
        }


        return;
    }

    public function preUpdate() {
        
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    /*
     * Non obligatoire mais permet de modifier l'affichage du widget si vous en avez besoin
      public function toHtml($_version = 'dashboard') {

      }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
    public static function postConfig_<Variable>() {
    }
     */

    /*
     * Non obligatoire mais ca permet de déclencher une action avant modification de variable de configuration
    public static function preConfig_<Variable>() {
    }
     */

    /*     * **********************Getteur Setteur*************************** */
}

class gds3710Cmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */

    private function open_door($type){
        log::add('gds3710', 'info', 'Requesting door opening type : '.$type);
        $gds3710 = eqLogic::byId($this->getEqLogic_id());

        $ip = $gds3710->getConfiguration('ip');
        $remote_pin = $gds3710->getConfiguration('remote_pin');
        $password = $gds3710->getConfiguration('password');

        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => 'https://'.$ip.'/goform/apicmd?cmd=0&user=admin',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $auth_challenge = new SimpleXMLElement(curl_exec($ch));
        $ChallengeCode = $auth_challenge->ChallengeCode[0];
        $IDCode = $auth_challenge->IDCode[0];

        $auth_response = md5($ChallengeCode.":".$remote_pin.":".$password);

        $optArray = array(
            CURLOPT_URL => 'https://'.$ip.'/goform/apicmd?cmd=1&user=admin&authcode='.$auth_response.'&idcode='.$IDCode.'&type='.$type,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true
        );

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);
        $data = curl_exec($ch);
        log::add('gds3710', 'debug', 'result : '.print_r($data, true));
    }
    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {
        $eqlogic = $this->getEqLogic(); //récupère l'éqlogic de la commande $this
        switch ($this->getLogicalId()) {    //vérifie le logicalid de la commande           
            case 'open': // LogicalId de la commande rafraîchir que l’on a créé dans la méthode Postsave de la classe vdm . 
                $this->open_door('1');
                break;
            case 'close': // LogicalId de la commande rafraîchir que l’on a créé dans la méthode Postsave de la classe vdm . 
                $this->open_door('2');
                break;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}


