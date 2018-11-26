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

require_once __DIR__  . '/../../../../core/php/core.inc.php';

set_time_limit(15);

if ((php_sapi_name() != 'cli' || isset($_SERVER['REQUEST_METHOD']) || !isset($_SERVER['argc'])) && (config::byKey('api') != init('api') && init('api') != '')) {
    echo 'Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (jeeTeleinfo)';
    die();
}

if(isset($_GET['mac']) && $_GET['mac'] != ''){ // L'adresse MAC est bien présente dans la requête, on sélectionne l'équipement correspondant
    $mac = strtolower($_GET['mac']);
    $gds3710 = gds3710::byLogicalId($mac, 'gds3710');
    log::add('gds3710','debug','MAC Address detected : '.$mac);
}

if (!is_object($gds3710)) { // Si l'adresse MAC ne correspond à aucun équipement alors on arrête
    log::add('gds3710', 'error', "Aucun équipement trouvé avec l'adresse MAC : " . $mac);
    die();
}

if(isset($_GET['type']) && $_GET['type'] != ''){ // L'adresse MAC est bien présente dans la requête, on sélectionne l'équipement correspondant
    $type = $_GET['type'];
    log::add('gds3710','debug','Action type detected : '.$type);
} else {
    log::add('gds3710', 'error', "No action type detected");
    die();
}

// On récupère la configuration du GDS3710
$password_http = $gds3710->getConfiguration('password');
$ip_http = $gds3710->getConfiguration('ip');
$remote_pin_http = $gds3710->getConfiguration('remote_pin');

function door_api($password, $remote_pin, $ip, $type){    
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

}

door_api($password_http, $remote_pin_http, $ip_http, $type);

?>