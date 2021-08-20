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


// Avant tout on s'occupe de l'authentification du client.

require_once __DIR__  . '/../../../../core/php/core.inc.php';

set_time_limit(15);

// Si la vérification du mot de passe est activée
if(config::byKey('password_protection', 'gds3710', 'jeedom') == 1){
    $realm = 'GDS3710 Jeedom Plugin Restricted area';
    $nonce = uniqid();
    $digest = getDigest();

    if (is_null($digest)){
        requireLogin($realm,$nonce);
    }

    $digestParts = digestParse($digest);

    $validUser = config::byKey('login', 'gds3710', 'jeedom');
    $validPass = config::byKey('password', 'gds3710', 'jeedom');
    $A1 = md5("{$validUser}:{$realm}:{$validPass}");
    $A2 = md5("{$_SERVER['REQUEST_METHOD']}:{$digestParts['uri']}");

    $validResponse = md5("{$A1}:{$digestParts['nonce']}:{$digestParts['nc']}:{$digestParts['cnonce']}:{$digestParts['qop']}:{$A2}");

    if ($digestParts['response']!=$validResponse){
        log::add('gds3710', 'error', 'Authentification failed with data :'.print_r($_SERVER, true));
        requireLogin($realm,$nonce);
    } 
}

if ((php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) && (config::byKey('api') != init('api') && init('api') != '')) {
    echo 'Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (jeeGDS3710)';
    die();
}

function getDigest() {
    if (isset($_SERVER['PHP_AUTH_DIGEST'])) {
        $digest = $_SERVER['PHP_AUTH_DIGEST'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (strpos(strtolower($_SERVER['HTTP_AUTHORIZATION']),'digest')===0)
            $digest = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
    }
    if(isset($digest)){
        return $digest;
    }
}

function requireLogin($realm,$nonce) {
    header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . $nonce . '",opaque="' . md5($realm) . '"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Vous n\'êtes pas autorisé à effectuer cette action (jeeGDS3710)';
    die();
}

function digestParse($digest) {
    // protect against missing data
    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
    $data = array();

    preg_match_all('@(\w+)=(?:(?:")([^"]+)"|([^\s,$]+))@', $digest, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $data[$m[1]] = $m[2] ? $m[2] : $m[3];
        unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
}

function getTypeFromLogicalID($type_requested){
    $cmd_array_gds = gds3710::get_GDS3710_event_list();

    foreach ($cmd_array_gds as $row){
        if($row['type'] == $type_requested){
            log::add('gds3710','debug','Type : '.$type_requested.' is match the logicalID '.$row['short_name']);
            return $row['short_name'];
        }
    }
}

$temp = "";
foreach ($_POST as $key => $value){
    $temp = $key.":".$value." | ".$temp;
}
$temp = "Event received : ".$temp;
log::add('gds3710','info',$temp);

if(isset($_POST['mac']) && $_POST['mac'] != ''){ // L'adresse MAC est bien présente dans la requête, on sélectionne l'équipement correspondant
    $mac = $_POST['mac'];
    $gds3710 = gds3710::byLogicalId($mac, 'gds3710');
    log::add('gds3710','debug','MAC Address detected : '.$mac);
}

if (!is_object($gds3710)) { // Si l'adresse MAC ne correspond à aucun équipement alors on arrête
    log::add('gds3710', 'error', "Aucun équipement trouvé avec l'adresse MAC : " . $mac);
    die();
}

if(isset($_POST['type']) && $_POST['type'] != ''){ // On vérifie qu'un type a bien été envoyé dand la requête sinon on stop.
    $type = $_POST['type']; // on récupère le type 
    log::add('gds3710','debug','Type detected : '.$type);
    $logical_id = getTypeFromLogicalID($type);
    log::add('gds3710','debug','Logical ID is : '.$logical_id);
    $data = json_encode($_POST);
    log::add('gds3710','debug','Data is : '.$data);

    $CMD = $gds3710->getCmd('info', $logical_id);
    $CMD->setConfiguration('value', $data);
    $CMD->event($data);
    $CMD->save();
    log::add('gds3710', 'debug', "Commande ".$logical_id." - ".$type." set to : " .$data);

    $CMD = $gds3710->getCmd('info', 'Last event');
    $CMD->setConfiguration('value', $data);
    $CMD->event($data);
    $CMD->save();
    log::add('gds3710', 'debug', "Last event set to : ".$data);

    $action_list = $gds3710->getConfiguration($logical_id); // On récupère la configuration à partir du type
    log::add('gds3710', 'debug', "Action list for the command has been retrieved");
    log::add('gds3710', 'debug', "Action list is : ".print_r($action_list, true));
    foreach ($action_list as $action) { // On va itérer sur la liste des commandes présentes dans la configuration
    	log::add('gds3710', 'debug', "Trying to execute action : ".print_r($action, true));
        try {
            $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
            $options = array();
            if (isset($action['options'])) { 
                $options = $action['options'];

                if(isset($action['options']['scenario_id'])){
                    $options['tags'] = $options['tags'].' mac="'.$_POST['mac'].'" content="'.$_POST['content'].'" type="'.$_POST['type'].'" date="'.$_POST["date"].'" card="'.$_POST['card'].'" sip="'.$_POST['sip'].'" user="'.$_POST['user']'" door="'.$_POST['door'].'"';
                }

            }
            log::add('gds3710', 'debug', "with options : ".print_r($options, true));
            scenarioExpression::createAndExec('action', $action['cmd'], $options);
            log::add('gds3710', 'debug', "Commande ".print_r($action['cmd'],true)." has been executed with option.".print_r($options, true));

        } catch (Exception $e) {
            log::add('gds3710', 'error', $this->getHumanName() . __(' : Erreur lors de l\'éxecution de ', __FILE__) . $action['cmd'] . __('. Détails : ', __FILE__) . $e->getMessage());   
        }

    }

} else {
    log::add('gds3710','error','no type detected');
    die();
}

?>
