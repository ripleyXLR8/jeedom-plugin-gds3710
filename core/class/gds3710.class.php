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

    /* Masque les secrets avant journalisation. Les logs de ce plugin sont regulierement
     * colles tels quels sur le forum de la communaute : ils ne doivent contenir ni mot de
     * passe, ni jeton de session, ni code d authentification. */
    public static function redact($_text) {
        $text = is_string($_text) ? $_text : print_r($_text, true);
        $text = preg_replace('/(authcode=)[^&\s]+/i', '$1***', $text);
        $text = preg_replace('/(idcode=)[^&\s]+/i', '$1***', $text);
        $text = preg_replace('/((?:mjpeg_)?sess(?:ion)?=)[^;\s]+/i', '$1***', $text);
        $text = preg_replace('/(:\/\/[^:\/\s]+:)[^@\s]+@/', '$1***@', $text);
        return $text;
    }

    /* Parse une reponse du portier. Auparavant le constructeur SimpleXMLElement etait
     * appele directement sur le retour de curl : il levait une exception non rattrapee des
     * que le portier etait injoignable ou repondait autre chose que du XML, soit une erreur
     * 500 cote Jeedom au lieu dune ligne de log. */
    public static function parseXml($_raw, $_context = '') {
        $where = $_context === '' ? '' : ' (' . $_context . ')';
        if (!is_string($_raw) || trim($_raw) === '') {
            log::add('gds3710', 'error', 'Portier injoignable ou reponse vide' . $where);
            return null;
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($_raw);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($xml === false) {
            log::add('gds3710', 'error', 'Reponse non XML du portier' . $where . ' : '
                . gds3710::redact(substr($_raw, 0, 200)));
            return null;
        }
        return $xml;
    }

    /* Purge quotidienne des captures. Le repertoire nen avait aucune : il grossissait
     * indefiniment (670 fichiers et 51 Mo sur linstallation de reference). Une retention
     * a 0 desactive la purge, ce qui reste le comportement historique. */
    public static function cronDaily() {
        $days = (int) config::byKey('snapshot_retention_days', 'gds3710', 0);
        if ($days <= 0) {
            return;
        }
        $root = realpath(calculPath(config::byKey('recdir', 'gds3710')));
        if ($root === false) {
            log::add('gds3710', 'error', 'Purge annulee : repertoire des captures introuvable.');
            return;
        }
        $limit = time() - ($days * 86400);
        $prefix = $root . DIRECTORY_SEPARATOR;
        $removed = 0;
        $freed = 0;

        foreach (eqLogic::byType('gds3710') as $eq) {
            $dir = realpath($root . '/' . $eq->getId());
            if ($dir === false || strpos($dir . DIRECTORY_SEPARATOR, $prefix) !== 0) {
                continue;
            }
            foreach ((array) glob($dir . '/*') as $file) {
                $real = realpath($file);
                if ($real === false || !is_file($real) || strpos($real, $prefix) !== 0) {
                    continue;
                }
                if (filemtime($real) >= $limit) {
                    continue;
                }
                $size = filesize($real);
                if (@unlink($real)) {
                    $removed++;
                    $freed += $size;
                }
            }
            /* Si la derniere capture connue vient detre purgee, les deux commandes qui la
             * referencent pointent dans le vide : on les remet a jour. */
            $path = $eq->getCmd('info', 'Lastest_Snapshot_Path');
            if (is_object($path) && $path->execCmd() != '' && !file_exists((string) $path->execCmd())) {
                $files = glob($dir . '/*.jpg');
                $url = $eq->getCmd('info', 'Lastest_Snapshot_URL');
                if (is_array($files) && count($files) > 0) {
                    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
                    $path->event($files[0]);
                    if (is_object($url)) { $url->event(substr($files[0], strpos($files[0], '/plugins'))); }
                } else {
                    $path->event('');
                    if (is_object($url)) { $url->event(''); }
                }
            }
        }

        if ($removed > 0) {
            log::add('gds3710', 'info', 'Purge des captures : ' . $removed . ' fichier(s) supprime(s), '
                . round($freed / 1048576, 1) . ' Mo liberes (retention ' . $days . ' jours).');
        }
    }

    /* ------------------------------------------------------------------ *
     *  Acces HTTP au portier                                              *
     * ------------------------------------------------------------------ */

    private static function httpGet($_url, $_cookie = '') {
        $ch = curl_init();
        $opt = array(
            CURLOPT_URL => $_url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        );
        if ($_cookie !== '') {
            $opt[CURLOPT_COOKIE] = $_cookie;
        }
        curl_setopt_array($ch, $opt);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /* Ouvre une session sur le portier et renvoie la chaine de cookies a rejouer.
     *
     * ATTENTION : le GDS3710 nadmet quUNE SEULE session administrateur. Chaque
     * authentification invalide la precedente — y compris celle dun humain en train
     * dutiliser linterface web du portier. Cest pourquoi le releve des capteurs
     * tourne sur cron15 et non sur cron, et pourquoi la session est mise en cache
     * pour etre reutilisee entre deux appels rapproches. */
    public function openSession() {
        $ip = trim((string) $this->getConfiguration('ip'));
        $password = (string) $this->getConfiguration('password');
        if ($ip === '' || $password === '') {
            return null;
        }

        $cached = cache::byKey('gds3710::session::' . $this->getId())->getValue(null);
        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        $xml = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/login?cmd=login&user=admin&type=0'), 'defi de session');
        if ($xml === null) {
            return null;
        }
        $challenge = (string) $xml->ChallengeCode[0];
        if ($challenge === '') {
            return null;
        }
        $authcode = md5($challenge . ':GDS3710lZpRsFzCbM:' . $password);
        $res = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/login?cmd=login&user=admin&authcode=' . $authcode), 'ouverture de session');
        if ($res === null || (string) $res->ResCode[0] !== '0') {
            log::add('gds3710', 'error', 'Authentification refusee par le portier ' . $this->getHumanName()
                . '. Verifiez le mot de passe administrateur.');
            return null;
        }
        $cookie = 'session=' . $authcode . ';uname=admin;level=1';
        /* Duree volontairement courte : la session du portier expire delle-meme entre
         * 90 et 300 secondes dinactivite. */
        cache::set('gds3710::session::' . $this->getId(), $cookie, 90);
        return $cookie;
    }

    /* Lit une section de configuration du portier et la renvoie sous forme de tableau. */
    public function readConfigSection($_type) {
        $cookie = $this->openSession();
        if ($cookie === null) {
            return null;
        }
        $ip = trim((string) $this->getConfiguration('ip'));
        $xml = gds3710::parseXml(self::httpGet('https://' . $ip . '/goform/config?cmd=get&type=' . urlencode($_type), $cookie), 'lecture ' . $_type);
        if ($xml === null) {
            /* La session a peut-etre ete invalidee entre-temps : on la jette. */
            cache::set('gds3710::session::' . $this->getId(), '', 1);
            return null;
        }
        $out = array();
        foreach ($xml->children() as $key => $value) {
            $out[(string) $key] = (string) $value;
        }
        return $out;
    }

    /* ------------------------------------------------------------------ *
     *  Capteurs remontes par cmd=get&type=sysinfo                         *
     * ------------------------------------------------------------------ */

    public static function get_sensor_list() {
        return array(
            'di0'                   => array('name' => 'Entrée digitale 1',   'subType' => 'binary'),
            'di1'                   => array('name' => 'Entrée digitale 2',   'subType' => 'binary'),
            'do'                    => array('name' => 'Sortie digitale',     'subType' => 'binary'),
            'atp_in'                => array('name' => 'Anti-arrachement',    'subType' => 'binary'),
            'doorctrl0'             => array('name' => 'Relais porte 1',      'subType' => 'string'),
            'doorctrl1'             => array('name' => 'Relais porte 2',      'subType' => 'string'),
            'systemp'               => array('name' => 'Température carte',   'subType' => 'numeric', 'unite' => '°C', 'historized' => 1),
            'sensortemp'            => array('name' => 'Température capteur', 'subType' => 'numeric', 'unite' => '°C', 'historized' => 1),
            'P15009'                => array('name' => 'Uptime',              'subType' => 'string'),
            'P70'                   => array('name' => 'Version firmware',    'subType' => 'string'),
            'Pfw_available_version' => array('name' => 'Mise à jour dispo',   'subType' => 'string'),
        );
    }

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
            
            "1101" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1101, "short_name" => "SystemUp", "message" => "System up", "use_case" => "Indicates that the system is UP."),
            "1102" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1102, "short_name" => "Reboot", "message" => "Reboot", "use_case" => "Indicates that the GDS unit has been rebooted."),
            "1103" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1103, "short_name" => "ResetClearAllData", "message" => "Reset (Clear All Data) ", "use_case" => "Factory reset (clear all data) has been performed."),
            "1104" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1104, "short_name" => "ResetRetainNetworkDataOnly", "message" => "Reset (Retain Network Data Only)", "use_case" => "Factory reset (Retain Network Data Only) has been performed."),
            "1105" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1105, "short_name" => "ResetRetainOnlyCardInformation)", "message" => "Reset (Retain Only Card Information)", "use_case" => "Factory reset (Retain Only Card Information) has been performed."),
            "1106" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1106, "short_name" => "ResetRetainNetworkDataAndCardInformation)", "message" => "Reset (Retain Network Data and Card Information)", "use_case" => "Factory reset (Retain Network Data and Card Information) has been performed."),
            "1107" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1107, "short_name" => "ResetWiegand", "message" => "Reset (Wiegand)", "use_case" => "Factory reset using Wiegand module has been performed on the unit"),
            "1108" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1108, "short_name" => "ConfigUpdate", "message" => "Config Update", "use_case" => "Indicates that the system’s configuration has been updated."),
            "1109" => array("section" =>'Surveillance Logiciel', 'section_icon'=>'fas fa-exclamation-triangle', "type" => 1109, "short_name" => "FirmwareUpdate", "message" => "Firmware Update (1.0.0.0)", "use_case" => "Indicates that the system’s firmware has been upgraded."),
            
            "1400" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1400, "short_name" => "MainboardTemperatureNormal", "message" => "Mainboard Temperature(32°C) Normal", "use_case" => "Indicates that device’s mainboard temperature is normal, (around 32°C)."),
            "1401" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1401, "short_name" => "MainboardTemperatureTooLow", "message" => "Mainboard Temperature(32°C) Too Low", "use_case" => "Indicates that device’s mainboard temperature is too low."),
            "1402" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1402, "short_name" => "MainboardTemperatureTooHigh", "message" => "Mainboard Temperature(32°C) Too High", "use_case" => "Indicates that device’s mainboard temperature is too high."),
            "1403" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1403, "short_name" => "SensorTemperatureNormal", "message" => "Sensor Temperature(32°C) Normal", "use_case" => "Indicates that device's sensor temperature is normal, (around 32°C)."),
            "1404" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1404, "short_name" => "SensorTemperatureTooLow", "message" => "Sensor Temperature(32°C) Too Low", "use_case" => "Indicates that device's sensor temperature is normal too low."),
            "1405" => array("section" =>'Surveillance Matériel', 'section_icon'=>'fas fa-thermometer-full', "type" => 1405, "short_name" => "SensorTemperatureTooHigh", "message" => "Sensor Temperature(32°C) Too High", "use_case" => "Indicates that device's sensor temperature is normal too high."),
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

    //public function parseCommaString($stringToParse){
        //$resultArray = explode(',', $stringToParse);
        
    //}

    public function postSave() {

        // On vérifie que la clef secrète à bien été créée sinon, on la génère.
        $KEY = $this->getConfiguration('secretkey');
        if($KEY == ''){
            log::add('gds3710', 'debug', 'No secretkey has been detected... creating a new one.');
            $this->setConfiguration('secretkey',md5(microtime().rand()));
        }

        // On utilise la MAC pour créer le logical ID de l'équipement
        $MAC = $this->getConfiguration('macaddress');
        $this->setLogicalId(strtolower($MAC));
        $this->save(true);
       
        /* Le type est obligatoire dans chaque getCmd() ci-dessous. La colonne logicalId est
         * en collation utf8mb3_unicode_ci, donc insensible a la casse : la commande action
         * 'reboot' et la commande info 'Reboot' (evenement 1102 du catalogue) sont
         * indistinguables pour MySQL. Sans filtre de type, getCmd() renvoyait l'une ou
         * l'autre selon l'ordre des lignes, et sur une reinstallation le code tentait de
         * renommer la commande info en « Reboot », nom deja porte par la commande action.
         * Jeedom refusait, postSave() avortait, et l'equipement devenait insauvegardable.
         * C'est le bug « Une commande portant ce nom (Reboot) existe deja ». */

        // Création de la commande reboot si elle n'existe pas dèjà
        $reboot = $this->getCmd('action', 'reboot');
        if (!is_object($reboot)) {
            $reboot = new gds3710Cmd();
        }
        $reboot->setName(__('Reboot', __FILE__));
        $reboot->setEqLogic_id($this->getId());
        $reboot->setLogicalId('reboot');
        $reboot->setType('action');
        $reboot->setSubType('other');
        $reboot->setIsVisible(1);
        $reboot->save();
            
        // Création de la commande open si elle n'existe pas dèjà
        $open = $this->getCmd('action', 'open');
        if (!is_object($open)) {
            $open = new gds3710Cmd();
        }
        $open->setName(__('Ouvrir la porte', __FILE__));
        $open->setEqLogic_id($this->getId());
        $open->setLogicalId('open');
        $open->setType('action');
        $open->setSubType('other');
        $open->setIsVisible(1);
        $open->save();

        // Création de la commande open2 si elle n'existe pas dèjà
        $open2 = $this->getCmd('action', 'open2');
        if (!is_object($open2)) {
            $open2 = new gds3710Cmd();
        }
        $open2->setName(__('Ouvrir la porte 2', __FILE__));
        $open2->setEqLogic_id($this->getId());
        $open2->setLogicalId('open2');
        $open2->setType('action');
        $open2->setSubType('other');
        $open2->setIsVisible(1);
        $open2->save();
        
        // Création de la commande close si elle n'existe pas dèjà
        $close = $this->getCmd('action', 'close');
        if (!is_object($close)) {
            $close = new gds3710Cmd();
        }
        $close->setName(__('Fermer la porte', __FILE__));
        $close->setEqLogic_id($this->getId());
        $close->setLogicalId('close');
        $close->setType('action');
        $close->setSubType('other');
        $close->setIsVisible(0);
        $close->save();

        // Création de la commande close2 si elle n'existe pas dèjà
        $close2 = $this->getCmd('action', 'close2');
        if (!is_object($close2)) {
            $close2 = new gds3710Cmd();
        }
        $close2->setName(__('Fermer la porte 2', __FILE__));
        $close2->setEqLogic_id($this->getId());
        $close2->setLogicalId('close2');
        $close2->setType('action');
        $close2->setSubType('other');
        $close2->setIsVisible(0);
        $close2->save();

        // Création de la commande snapshot
        $snapshot = $this->getCmd('action', 'snapshot');
        if (!is_object($snapshot)) {
            $snapshot = new gds3710Cmd();
        }
        $snapshot->setName(__('Prendre un snapshot', __FILE__));
        $snapshot->setEqLogic_id($this->getId());
        $snapshot->setLogicalId('snapshot');
        $snapshot->setType('action');
        $snapshot->setSubType('other');
        $snapshot->setDisplay('icon', '<i class="fa fa-image"></i>');
        $snapshot->setTemplate('dashboard', '');
        $snapshot->setIsVisible(1);
        $snapshot->save();

        // Création de la commande Modify Config
        $modifyconfig = $this->getCmd('action', 'modifyConfig');
        if (!is_object($modifyconfig)) {
            $modifyconfig = new gds3710Cmd();
        }
        $modifyconfig->setName(__('Modifier la configuration', __FILE__));
        $modifyconfig->setType('action');
        $modifyconfig->setLogicalId('modifyConfig');
        $modifyconfig->setEqLogic_id($this->getId());
        $modifyconfig->setSubType('message');
        $modifyconfig->setIsVisible(0);
        $modifyconfig->setDisplay('title_placeholder', __('ID de la commande à modifier', __FILE__));
        $modifyconfig->setDisplay('message_placeholder', __('Valeur', __FILE__));
        $modifyconfig->setDisplay('message_cmd_type', 'action');
        $modifyconfig->setDisplay('message_cmd_subtype', 'message');
        $modifyconfig->save();

        // Création de la commande Send SnapShot
        $sendSnapshot = $this->getCmd('action', 'sendSnapshot');
        if (!is_object($sendSnapshot)) {
            $sendSnapshot = new gds3710Cmd();
        }
        $sendSnapshot->setName(__('Envoyer un snapshot', __FILE__));
        $sendSnapshot->setConfiguration('request', '-');
        $sendSnapshot->setType('action');
        $sendSnapshot->setLogicalId('sendSnapshot');
        $sendSnapshot->setEqLogic_id($this->getId());
        $sendSnapshot->setSubType('message');
        $sendSnapshot->setIsVisible(0);
        $sendSnapshot->setDisplay('title_placeholder', __('Nombre captures ou options', __FILE__));
        $sendSnapshot->setDisplay('message_placeholder', __('Commande message d\'envoi des captures', __FILE__));
        $sendSnapshot->setDisplay('message_cmd_type', 'action');
        $sendSnapshot->setDisplay('message_cmd_subtype', 'message');
        $sendSnapshot->save();

        // Création de la commande d'historique
        $history = $this->getCmd('action', 'Open_Snapshots_Folder');
        if (!is_object($history)) {
            $history = new gds3710Cmd();
        }
        $history->setName(__('Ouvrir le dossier des captures', __FILE__));
        $history->setEqLogic_id($this->getId());
        $history->setLogicalId('Open_Snapshots_Folder');
        $history->setType('action');
        $history->setSubType('other');
        $history->setTemplate('dashboard', 'snapshot_folder');
        $history->setIsVisible(1);
        $history->save();

        // Création de la commande de récupération du dernier snapshot
        $lastest_snapshot = $this->getCmd('info', 'Lastest_Snapshot_Path');
        if (!is_object($lastest_snapshot)) {
            $lastest_snapshot = new gds3710Cmd();
        }
        $lastest_snapshot->setName(__('Chemin du dernier snapshot', __FILE__));
        $lastest_snapshot->setEqLogic_id($this->getId());
        $lastest_snapshot->setLogicalId('Lastest_Snapshot_Path');
        $lastest_snapshot->setType('info');
        $lastest_snapshot->setSubType('string');
        $lastest_snapshot->setIsVisible(0);
        $lastest_snapshot->save();

        $lastest_snapshot_URL = $this->getCmd('info', 'Lastest_Snapshot_URL');
        if (!is_object($lastest_snapshot_URL)) {
            $lastest_snapshot_URL = new gds3710Cmd();
        }
        $lastest_snapshot_URL->setName(__('Dernier snapshot', __FILE__));
        $lastest_snapshot_URL->setEqLogic_id($this->getId());
        $lastest_snapshot_URL->setLogicalId('Lastest_Snapshot_URL');
        $lastest_snapshot_URL->setType('info');
        $lastest_snapshot_URL->setSubType('string');
        $lastest_snapshot_URL->setTemplate('dashboard', 'lastsnapshot');
        $lastest_snapshot_URL->setTemplate('mobile', 'lastsnapshot');
        $lastest_snapshot_URL->setIsVisible(0);
        $lastest_snapshot_URL->save();

        // Création de la commande LDC ON
        $ldc_ON = $this->getCmd('action', 'ldc_ON');
        if (!is_object($ldc_ON)) {
            $ldc_ON = new gds3710Cmd();
        }
        $ldc_ON->setName(__('LDC - ON', __FILE__));
        $ldc_ON->setEqLogic_id($this->getId());
        $ldc_ON->setLogicalId('ldc_ON');
        $ldc_ON->setType('action');
        $ldc_ON->setSubType('other');
        $ldc_ON->setIsVisible(1);
        $ldc_ON->save();

        // Création de la commande LDC OFF
        $ldc_OFF = $this->getCmd('action', 'ldc_off');
        if (!is_object($ldc_OFF)) {
            $ldc_OFF = new gds3710Cmd();
        }
        $ldc_OFF->setName(__('LDC - OFF', __FILE__));
        $ldc_OFF->setEqLogic_id($this->getId());
        $ldc_OFF->setLogicalId('ldc_off');
        $ldc_OFF->setType('action');
        $ldc_OFF->setSubType('other');
        $ldc_OFF->setIsVisible(1);
        $ldc_OFF->save();

        // Création de CMOS Normal
        $cmos_NORMAL = $this->getCmd('action', 'cmos_normal');
        if (!is_object($cmos_NORMAL)) {
            $cmos_NORMAL = new gds3710Cmd();
        }
        $cmos_NORMAL->setName(__('CMOS - Normal', __FILE__));
        $cmos_NORMAL->setEqLogic_id($this->getId());
        $cmos_NORMAL->setLogicalId('cmos_normal');
        $cmos_NORMAL->setType('action');
        $cmos_NORMAL->setSubType('other');
        $cmos_NORMAL->setIsVisible(1);
        $cmos_NORMAL->save();

        // Création de CMOS Low Light
        $cmos_LOWLIGHT = $this->getCmd('action', 'cmos_lowlight');
        if (!is_object($cmos_LOWLIGHT)) {
            $cmos_LOWLIGHT = new gds3710Cmd();
        }
        $cmos_LOWLIGHT->setName(__('CMOS - Low Light', __FILE__));
        $cmos_LOWLIGHT->setEqLogic_id($this->getId());
        $cmos_LOWLIGHT->setLogicalId('cmos_lowlight');
        $cmos_LOWLIGHT->setType('action');
        $cmos_LOWLIGHT->setSubType('other');
        $cmos_LOWLIGHT->setIsVisible(1);
        $cmos_LOWLIGHT->save();

        // Création de CMOS WDR
        $cmos_WDR = $this->getCmd('action', 'cmos_wdr');
        if (!is_object($cmos_WDR)) {
            $cmos_WDR = new gds3710Cmd();
        }
        $cmos_WDR->setName(__('CMOS - WDR', __FILE__));
        $cmos_WDR->setEqLogic_id($this->getId());
        $cmos_WDR->setLogicalId('cmos_wdr');
        $cmos_WDR->setType('action');
        $cmos_WDR->setSubType('other');
        $cmos_WDR->setIsVisible(1);
        $cmos_WDR->save();

        // Création de la commande stream_mjpeg
        $stream_mjpeg = $this->getCmd('info', 'stream_mjpeg');
        if (!is_object($stream_mjpeg)) {
            $stream_mjpeg = new gds3710Cmd();
        }
        $stream_mjpeg->setName(__('Stream MJPEG', __FILE__));
        $stream_mjpeg->setEqLogic_id($this->getId());
        $stream_mjpeg->setLogicalId('stream_mjpeg');
        $stream_mjpeg->setType('info');
        $stream_mjpeg->setSubType('string');
        $stream_mjpeg->setTemplate('dashboard', 'mjpegstream');
        $stream_mjpeg->setTemplate('mobile', 'mjpegstream');
        $stream_mjpeg->setIsVisible(1);
        $stream_mjpeg->save();
        $stream_mjpeg->event('/plugins/gds3710/core/php/camera.php?id='.$this->getId());

        // Création de la commande last event
        $info = $this->getCmd('info', 'Last event');
        if (!is_object($info)) {
            $info = new gds3710Cmd();
        }  
        $info->setName(__('Last event', __FILE__));
        $info->setType('info');
        $info->setSubType('string');
        $info->setLogicalId('Last event');
        $info->setIsVisible(0);
        $info->setEqLogic_id($this->getId());
        $info->save();

        // Création des autres commandes du portier
        $cmd_array = gds3710::get_GDS3710_event_list();
        foreach ($cmd_array as $row){
            $info = $this->getCmd('info', $row['short_name']);
            if (!is_object($info)) {
                $info = new gds3710Cmd();
            }
            $info->setName(__($row['type'], __FILE__));
            $info->setType('info');
            $info->setSubType('string');
            $info->setIsVisible(0);
            $info->setLogicalId($row['short_name']);
            $info->setEqLogic_id($this->getId());
            $info->save(); 
        }

        /* Capteurs releves par cmd=get&type=sysinfo. Le plugin nappelait jamais cette
         * requete alors quelle expose gratuitement les entrees/sorties digitales, letat
         * des relais, deux temperatures, luptime et la version de firmware. */
        foreach (gds3710::get_sensor_list() as $lid => $def) {
            $cmd = $this->getCmd('info', $lid);
            if (!is_object($cmd)) {
                $cmd = new gds3710Cmd();
                $cmd->setIsVisible(0);
            }
            $cmd->setName(__($def['name'], __FILE__));
            $cmd->setType('info');
            $cmd->setSubType($def['subType']);
            $cmd->setLogicalId($lid);
            $cmd->setEqLogic_id($this->getId());
            if (isset($def['unite'])) {
                $cmd->setUnite($def['unite']);
            }
            if (isset($def['historized'])) {
                $cmd->setIsHistorized($def['historized']);
            }
            $cmd->save();
        }

        return;
    }

    /* Releve periodique des capteurs et reparation de lURL du flux.
     *
     * cron15 et non cron : chaque authentification invalide la session administrateur
     * en cours sur le portier, y compris celle dun humain devant son interface web.
     * Les valeurs concernees (temperatures, uptime, firmware) evoluent lentement ;
     * les entrees digitales rapides passent de toute facon par les evenements. */
    public static function cron15() {
        foreach (eqLogic::byType('gds3710', true) as $eq) {

            /* LURL du flux se perd des quun vidage de cache remet les valeurs a blanc.
             * On la republie sans dependre du portier. */
            $stream = $eq->getCmd('info', 'stream_mjpeg');
            if (is_object($stream) && (string) $stream->execCmd() === '') {
                $stream->event('/plugins/gds3710/core/php/camera.php?id=' . $eq->getId());
                log::add('gds3710', 'info', 'URL du flux MJPEG republiee pour ' . $eq->getHumanName() . '.');
            }

            if (config::byKey('poll_sensors', 'gds3710', 1) != 1) {
                continue;
            }

            $info = $eq->readConfigSection('sysinfo');
            if ($info === null) {
                continue;
            }
            foreach (gds3710::get_sensor_list() as $lid => $def) {
                if (!array_key_exists($lid, $info)) {
                    continue;
                }
                $cmd = $eq->getCmd('info', $lid);
                if (!is_object($cmd)) {
                    continue;
                }
                $value = trim($info[$lid]);
                if ($def['subType'] === 'binary') {
                    $value = ($value === '' || $value === '0') ? 0 : 1;
                } elseif ($def['subType'] === 'numeric') {
                    if (!is_numeric($value)) { continue; }
                    $value = (float) $value;
                } elseif ($lid === 'Pfw_available_version') {
                    $value = ($value === '') ? __('à jour', __FILE__) : $value;
                }
                $eq->checkAndUpdateCmd($cmd, $value);
            }
        }
    }

    public function preUpdate() {
        $this->setCategory('security', 1);

        // Seul le champs adresse MAC est obligatoire.
        if ($this->getConfiguration('macaddress') == '') {
            throw new Exception(__('L\'adresse MAC du portier ne peut être vide', __FILE__));
        }
    }

    public function postUpdate() {
        
    }

    public function preRemove() {
        
    }

    public function postRemove() {
        
    }

    // public function toHtml($_version = 'dashboard') {
    //      $replace = $this->preToHtml($_version);
    //      if (!is_array($replace)) {
    //          return $replace;
    //      }
    //      $version = jeedom::versionAlias($_version);
    //      if ($this->getDisplay('hideOn' . $version) == 1) {
    //          return '';
    //      }
    //     /* ------------ Ajouter votre code ici ------------*/

    //     $replace['#MAC#'] = $this->getLogicalId();

    //     foreach ($this->getCmd('info') as $cmd) {

    //         //return $cmd->getLogicalId();
    //         // $replace['#' . $cmd->getLogicalId() . '_history#'] = '';
    //         // $replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
    //         // $replace['#' . $cmd->getLogicalId() . '#'] = $cmd->execCmd();
    //         // $replace['#' . $cmd->getLogicalId() . '_collect#'] = $cmd->getCollectDate();
    //         // if ($cmd->getLogicalId() == 'encours'){
    //         //     $replace['#thumbnail#'] = $cmd->getDisplay('icon');
    //         // }
    //         // if ($cmd->getIsHistorized() == 1) {
    //         //     $replace['#' . $cmd->getLogicalId() . '_history#'] = 'history cursor';
    //         // }
    //     }

    //     foreach ($this->getCmd('action') as $cmd) {
    //         //$replace['#' . $cmd->getLogicalId() . '_id#'] = $cmd->getId();
    //     }
    //     /* ------------ N'ajouter plus de code apres ici------------ */

    //      return $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'gds3710', 'gds3710')));
    // }
     

    
     /* Non obligatoire mais ca permet de déclencher une action après modification de variable de configuration
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
        log::add('gds3710', 'info', 'Requesting door opening 1 or closing 2 of type : '.$type);
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
        log::add('gds3710', 'debug', 'curl options are : '.gds3710::redact($optArray));
        $auth_challenge = gds3710::parseXml(curl_exec($ch), 'ouverture porte');
        if ($auth_challenge === null) {
            return;
        }
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

    private function open_door_2($type){
        log::add('gds3710', 'info', 'Requesting door opening 2 or closing 2 of type : '.$type);
        $gds3710 = eqLogic::byId($this->getEqLogic_id());

        $ip = $gds3710->getConfiguration('ip');
        $remote_pin = $gds3710->getConfiguration('remote_pin_2');
        $password = $gds3710->getConfiguration('password');

        $ch = curl_init();
        $optArray = array(
            CURLOPT_URL => 'https://'.$ip.'/goform/apicmd?cmd=0&user=admin',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $auth_challenge = gds3710::parseXml(curl_exec($ch), 'ouverture porte');
        if ($auth_challenge === null) {
            return;
        }
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

    private function setConfig($id, $parameter_value){

        if( $id == '' || $parameter_value == ''){
            log::add('gds3710', 'error', 'Parameter error. Aborting.');
            return;
        }

        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        $cookies = $this->getAuthCookies($gds3710);
        log::add('gds3710', 'debug', 'Auth cookies is : '.gds3710::redact($cookies));

        $cookie_string = "";
        foreach ($cookies as $key => $value) {
            $cookie_string.=$key."=".$value.";";
        }

        $ip = $gds3710->getConfiguration('ip');
        $password = $gds3710->getConfiguration('password');
        $salt = "GDS3710lZpRsFzCbM";

        $ch = curl_init();
        $url = 'https://'.$ip.'/goform/config?cmd=set&'.$id.'='.$parameter_value;

        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookie_string
        );

        log::add('gds3710', 'debug', 'Calling url : '.$url);

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);

        $result = gds3710::parseXml(curl_exec($ch), 'requete configuration');
        log::add('gds3710', 'debug', 'Result is : '. print_r($result, true));
    }
   
    private function reboot(){
        log::add('gds3710', 'info', 'Requesting reboot');

        $gds3710 = eqLogic::byId($this->getEqLogic_id());
        $cookies = $this->getAuthCookies($gds3710);
        log::add('gds3710', 'debug', 'Auth cookies is : '.gds3710::redact($cookies));

        $cookie_string = "";
        foreach ($cookies as $key => $value) {
            $cookie_string.=$key."=".$value.";";
        }

        $ip = $gds3710->getConfiguration('ip');
        $password = $gds3710->getConfiguration('password');
        $salt = "GDS3710lZpRsFzCbM";

        $ch = curl_init();

        $url = 'https://'.$ip.'/goform/config?cmd=reboot';

        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookie_string
        );

        log::add('gds3710', 'debug', 'Calling url : '.$url);

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);

        $result = gds3710::parseXml(curl_exec($ch), 'requete configuration');
        log::add('gds3710', 'debug', 'Result is : '. print_r($result, true));

    }

    private function getAuthCookies($gds){

        $ip = $gds->getConfiguration('ip');
        $password = $gds->getConfiguration('password');
        $salt = "GDS3710lZpRsFzCbM";

        $ch = curl_init();

        $optArray = array(
            CURLOPT_URL => 'https://'.$ip.'/goform/login?cmd=login&user=admin&type=0',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true
        );

        curl_setopt_array($ch, $optArray);
        $auth_challenge = gds3710::parseXml(curl_exec($ch), 'ouverture de session');
        if ($auth_challenge === null) {
            return array();
        }

        $ChallengeCode = $auth_challenge->ChallengeCode[0];
        $IDCode = $auth_challenge->IDCode[0];

        $auth_response = md5($ChallengeCode.":"."GDS3710lZpRsFzCbM".":".$password);
        $url = 'https://'.$ip.'/goform/login?cmd=login&user=admin&authcode='.$auth_response;

        $optArray = array(
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true
        );
        $ch = curl_init();
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
        
        return $cookies;

    }

    private function ldc_ON(){
        log::add('gds3710', 'info', 'Requesting LDC ON');
        $this->setConfig('P10573', '1');
    }

    private function ldc_OFF(){
        log::add('gds3710', 'info', 'Requesting LDC OFF');
        $this->setConfig('P10573', '0');
    }

    private function cmos_normal(){
        log::add('gds3710', 'info', 'Requesting CMOS NORMAL');
        $this->setConfig('P10572', '1');
    }

    private function cmos_lowlight(){
        log::add('gds3710', 'info', 'Requesting CMOS LOW LIGHT');
        $this->setConfig('P10572', '2');
    }

    private function cmos_wdr(){
        log::add('gds3710', 'info', 'Requesting CMOS WDR');
        $this->setConfig('P10572', '3');
    }


    private function take_snapshot(){
        log::add('gds3710', 'debug', 'Snapshot has been requested');

        $gds3710 = eqLogic::byId($this->getEqLogic_id());

        $ip = $gds3710->getConfiguration('ip');
        $password = $gds3710->getConfiguration('password');
        $salt = 'GDS3710lDyTlHwNgZ';

        $ch = curl_init();

        $optArray = array(
            CURLOPT_URL => 'https://'.$ip.'/goform/login?cmd=login&user=admin&type=1',   
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true
        );

        log::add('gds3710', 'debug', 'URL array : '.gds3710::redact($optArray));

        curl_setopt_array($ch, $optArray);
        $data = curl_exec($ch);

        log::add('gds3710', 'debug', 'URL return : '.gds3710::redact($data));

        $auth_challenge = gds3710::parseXml($data, 'capture');
        if ($auth_challenge === null) {
            return null;
        }
        $ChallengeCode = $auth_challenge->ChallengeCode[0];
        $string_to_be_hashed = $ChallengeCode.":".$salt.":".$password;

        /* Ne jamais journaliser cette chaine : elle contient le mot de passe en clair. */
        log::add('gds3710', 'debug', 'Challenge recu : '.$ChallengeCode);

        $auth_response = md5($string_to_be_hashed);
        $url ='https://'.$ip.'/goform/login?cmd=login&user=admin&authcode='.$auth_response.'&type=1';

        $optArray = array(
            CURLOPT_URL => $url,         
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true
        );

        log::add('gds3710', 'debug', 'URL array : '.gds3710::redact($optArray));

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);
        $data = curl_exec($ch);

        log::add('gds3710', 'debug', 'URL return : '.gds3710::redact($data));

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $data, $matches);
        $cookies = array();
        foreach($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }

        $cookies_string = '';
        foreach($cookies as $key => $value){
            $cookies_string=$cookies_string.$key."=".$value.";";
        }
        $cookies_string = rtrim($cookies_string,';');

        $url ='https://'.$ip.'/snapshot/view0.jpg';
        
        $now = new DateTime();
        $filename=$gds3710->getName()."_".$now->format("Y-m-d_H-i-s").'-'.mt_rand(100000, 999999);

        $dir = calculPath(config::byKey('recdir', 'gds3710')) . '/' . $gds3710->getId();

        /* Les echecs decriture etaient silencieux : mkdir() et fopen() netaient pas
         * verifies, la capture ne se creait pas et rien ne lexpliquait. Le mode 0777
         * est aussi remplace par 0775, suffisant pour lutilisateur du serveur web. */
        if (!file_exists($dir)) {
            log::add('gds3710', 'debug', "Directory doesn't exist, creating : ".$dir);
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                log::add('gds3710', 'error', 'Impossible de creer le repertoire des captures : ' . $dir
                    . '. Verifiez le chemin configure et les droits du parent.');
                return null;
            }
        }

        if (!is_writable($dir)) {
            log::add('gds3710', 'error', 'Repertoire des captures non accessible en ecriture : ' . $dir
                . '. Il doit appartenir a lutilisateur du serveur web.');
            return null;
        }

        $output_file = $dir.'/'.$filename.'.jpg';
        $fp = @fopen($output_file, 'x');
        if ($fp === false) {
            log::add('gds3710', 'error', 'Impossible de creer le fichier de capture : ' . $output_file);
            return null;
        }
        log::add('gds3710', 'debug', 'Trying to create the capture under : '.$output_file);

        $optArray = array(
            CURLOPT_URL => $url,         
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookies_string,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_FILE => $fp
        );

        log::add('gds3710', 'debug', 'URL array : '.gds3710::redact($optArray));

        $ch = curl_init();
        curl_setopt_array($ch, $optArray);

        $data = curl_exec($ch);
        log::add('gds3710', 'debug', 'URL return : '.gds3710::redact($data));

        curl_close ($ch);
        fclose($fp);
        log::add('gds3710', 'debug', 'Closing the file');

        log::add('gds3710', 'debug', "Registering path to lastest picture");
        $eqLogic = $this->getEqLogic();
        $lastest_snapshot = $eqLogic->getCmd('info', 'Lastest_Snapshot_Path');
        $lastest_snapshot->event(realpath($output_file));
        $lastest_snapshot->save();

        log::add('gds3710', 'debug', "Registering URL to the lastest snapshot");
        $eqLogic = $this->getEqLogic();
        $lastest_snapshot_URL = $eqLogic->getCmd('info', 'Lastest_Snapshot_URL');
        $lastest_snapshot_URL->event(substr($output_file, strpos($output_file, '/plugins')));
        $lastest_snapshot_URL->save();

        return $output_file;
    }

    // Take a number of snapshot and send them to a command.
    private function send_snapshot($nbsnap, $sendto){

        log::add('gds3710', 'debug', 'Starting sending '.$nbsnap.' snapshot(s) to command(s) '.$sendto);

        if ($nbsnap == '' || $nbsnap == 0) {
            log::add('gds3710', 'debug', 'Number of snapshot is zero. Aborting');
            return;
        }

        if ($sendto == '') {
            log::add('gds3710', 'debug', 'No command to send to. Aborting');
            return;
        }

        $files =array();

        for ($i = 1; $i <= $nbsnap; $i++) {
            $shot = $this->take_snapshot();
            if ($shot === null) { // portier injoignable : inutile denvoyer une image vide
                log::add('gds3710', 'error', 'Capture ' . $i . ' sur ' . $nbsnap . ' echouee, envoi interrompu.');
                break;
            }
            array_push($files, $shot);
        }

        if (count($files) === 0) {
            log::add('gds3710', 'error', 'Aucune capture disponible, rien a envoyer.');
            return;
        }

        $options = array();
        $options['files'] = $files;

        $cmds = explode('&&',  $sendto);

        foreach ($cmds as $id) {
            $cmd = cmd::byId(str_replace('#', '', $id));
            if (!is_object($cmd)) {
                log::add('gds3710', 'error', 'Error while sending snapshot, '.$cmd.' is not a cmd');
                continue;
            }
            try {
                $cmd->execCmd($options);
            } catch (Exception $e) {
                log::add('gds3710', 'error', __('[gds3710/send_snapshot] Erreur lors de l\'envoi des images : ', __FILE__) . $cmd->getHumanName() . ' => ' . log::exception($e));
            }
        }

    }

    /*     * *********************Methode d'instance************************* */

    /*
     * Non obligatoire permet de demander de ne pas supprimer les commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
      public function dontRemoveCmd() {
      return true;
      }
     */

    public function execute($_options = array()) {

        $eqLogic = $this->getEqLogic();

        switch ($this->getLogicalId()) {
            case 'open':
                $this->open_door('1');
                break;
            case 'close':
                $this->open_door('2');
                break;
            case 'open2':
                $this->open_door_2('1');
                break;
            case 'close2':
                $this->open_door_2('2');
                break;
            case 'ldc_off':
                $this->ldc_OFF();
                break;
            case 'ldc_ON':
                $this->ldc_ON();
                break;
            case 'cmos_normal':
                $this->cmos_normal();
                break;
            case 'cmos_lowlight':
                $this->cmos_lowlight();
                break;
            case 'cmos_wdr':
                $this->cmos_wdr();
                break;
            case 'snapshot':
                $this->take_snapshot();
                break;
            case 'reboot':
                $this->reboot();
                break;
            case 'sendSnapshot':
                if (!isset($_options['title'])) {
                    $_options['title'] = '';
                }
                if (!isset($_options['message'])) {
                    $_options['message'] = '';
                }
                $this->send_snapshot($_options['title'], $_options['message']);
                break;
            case 'modifyConfig':
                if (isset($_options['title']) && isset($_options['message'])) {
                    log::add('gds3710', 'debug', 'Trying to set config variable '.$_options['title'].' with value '.$_options['message']);
                    $this->setConfig($_options['title'], $_options['message']);
                }
                break;
        }
    }

    /*     * **********************Getteur Setteur*************************** */
}
