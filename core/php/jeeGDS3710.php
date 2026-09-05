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

// ---------------------------------------------------------------------------
// Authentification de l'appelant
// ---------------------------------------------------------------------------

$realm = 'GDS3710 Jeedom Plugin Restricted area';

/* La protection par mot de passe reste optionnelle : la rendre obligatoire d'office
 * couperait la remontée d'évènements de toutes les installations existantes au moment
 * de la mise à jour. Elle est en revanche activée par défaut sur toute nouvelle
 * installation (voir plugin_info/install.php), et son absence est signalée à
 * l'administrateur au lieu d'être passée sous silence. */
if (config::byKey('password_protection', 'gds3710', 0) == 1) {

    $digest = getDigest();
    if ($digest === null) {
        requireLogin($realm);
    }

    $digestParts = digestParse($digest);
    if ($digestParts === false) {
        log::add('gds3710', 'error', 'En-tete Digest incomplet recu depuis ' . gds3710_client_ip());
        requireLogin($realm);
    }

    /* Le nonce doit etre un nonce que NOUS avons emis, encore valide, et dont le compteur
     * progresse. Sans ce controle la reponse calculee l'etait a partir du nonce fourni par
     * le client lui-meme : rejouer une requete capturee suffisait a s'authentifier. */
    if (!gds3710_consume_nonce($digestParts['nonce'], $digestParts['nc'])) {
        log::add('gds3710', 'error', 'Nonce Digest inconnu, expire ou rejoue depuis ' . gds3710_client_ip());
        requireLogin($realm);
    }

    $validUser = (string) config::byKey('login', 'gds3710');
    $validPass = (string) config::byKey('password', 'gds3710');
    $A1 = md5($validUser . ':' . $realm . ':' . $validPass);
    $A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $digestParts['uri']);
    $validResponse = md5($A1 . ':' . $digestParts['nonce'] . ':' . $digestParts['nc'] . ':'
                       . $digestParts['cnonce'] . ':' . $digestParts['qop'] . ':' . $A2);

    if (!hash_equals($validResponse, (string) $digestParts['response'])
        || !hash_equals($validUser, (string) $digestParts['username'])) {
        /* Ne jamais journaliser $_SERVER ici : l'en-tete Authorization s'y trouve. */
        log::add('gds3710', 'error', 'Authentification refusee depuis ' . gds3710_client_ip());
        requireLogin($realm);
    }

} else {
    gds3710_warn_unprotected();
}

/* La clef API reste acceptee comme second mode. Le test d'origine ne se declenchait que
 * si un parametre « api » etait fourni : ne pas en envoyer suffisait a le contourner. */
$apiKey = init('api');
if ($apiKey !== '' && !hash_equals((string) config::byKey('api'), (string) $apiKey)) {
    log::add('gds3710', 'error', 'Clef API invalide depuis ' . gds3710_client_ip());
    header('HTTP/1.1 401 Unauthorized');
    echo 'Clef API non valide : action non autorisee (jeeGDS3710)';
    die();
}

function gds3710_client_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
}

/* Emet un nonce a usage unique et le memorise pour la duree de vie du defi. */
function gds3710_issue_nonce() {
    $nonce = md5(uniqid('', true) . mt_rand());
    cache::set('gds3710::nonce::' . $nonce, 0, 300);
    return $nonce;
}

/* Valide puis consomme un nonce. Retourne false s'il n'a pas ete emis par nous, s'il a
 * expire, ou si le compteur nc ne progresse pas (rejeu). */
function gds3710_consume_nonce($nonce, $nc) {
    if (!is_string($nonce) || $nonce === '' || !ctype_xdigit($nonce)) {
        return false;
    }
    $key = 'gds3710::nonce::' . $nonce;
    $seen = cache::byKey($key)->getValue(null);
    if ($seen === null) {
        return false;
    }
    $counter = is_string($nc) && ctype_xdigit($nc) ? hexdec($nc) : 0;
    if ($counter <= (int) $seen) {
        return false;
    }
    cache::set($key, $counter, 300);
    return true;
}

/* Signale une installation qui accepte les evenements sans authentification. Le message
 * au centre de messages est limite a un par jour pour ne pas le noyer. */
function gds3710_warn_unprotected() {
    log::add('gds3710', 'warning', 'Evenement accepte sans authentification. Activez la protection par mot de passe dans la configuration du plugin.');
    if (cache::byKey('gds3710::unprotected_notified')->getValue(0) == 1) {
        return;
    }
    cache::set('gds3710::unprotected_notified', 1, 86400);
    message::add('gds3710', __('Le portier publie ses evenements sans authentification : tout appareil du reseau peut declencher vos scenarios. Activez la protection par mot de passe dans la configuration du plugin.', __FILE__));
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

function requireLogin($realm) {
    $nonce = gds3710_issue_nonce();
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

$mac = '';
$gds3710 = null;

if (isset($_POST['mac']) && $_POST['mac'] != '') { // L'adresse MAC est bien présente dans la requête, on sélectionne l'équipement correspondant
    $mac = $_POST['mac'];
    $gds3710 = gds3710::byLogicalId($mac, 'gds3710');
    log::add('gds3710','debug','MAC Address detected : '.$mac);
}

if (!is_object($gds3710)) { // Si l'adresse MAC ne correspond à aucun équipement alors on arrête
    log::add('gds3710', 'error', "Aucun équipement trouvé avec l'adresse MAC : " . $mac);
    die();
}

/* Contrôle d'origine. L'adresse MAC est inscrite sur le portier et sert d'identifiant,
 * pas de secret : la connaître suffisait à publier un faux évènement et à déclencher les
 * scénarios associés, ouverture de porte comprise. On vérifie donc que la requête provient
 * bien de l'adresse IP configurée pour cet équipement. Ce n'est pas de la cryptographie,
 * mais cela impose d'usurper l'adresse du portier au lieu de simplement lire son étiquette,
 * et cela protège les installations qui n'ont pas activé la protection par mot de passe.
 * L'option de contournement existe pour les installations derrière un NAT ou un proxy. */
$expectedIp = trim((string) $gds3710->getConfiguration('ip'));
$clientIp   = gds3710_client_ip();

if (filter_var($expectedIp, FILTER_VALIDATE_IP)
    && $clientIp !== '?'
    && $clientIp !== $expectedIp
    && config::byKey('skip_source_ip_check', 'gds3710', 0) != 1) {
    log::add('gds3710', 'error', 'Evenement refuse : recu depuis ' . $clientIp . ' alors que le portier '
        . $mac . ' est configure sur ' . $expectedIp
        . '. Si votre Jeedom est derriere un NAT ou un proxy, desactivez le controle d origine dans la configuration du plugin.');
    header('HTTP/1.1 403 Forbidden');
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
                    $options['tags'] = $options['tags'].' mac="'.$_POST['mac'].'" content="'.$_POST['content'].'" type="'.$_POST['type'].'" date="'.$_POST["date"].'" card="'.$_POST['card'].'" sip="'.$_POST['sip'].'" username="'.$_POST['username'].'" doornum="'.$_POST['doornum'].'"';
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
