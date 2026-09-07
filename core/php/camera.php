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
include_file('core', 'authentification', 'php');

/* Le widget charge cette URL en relatif depuis une page Jeedom : le cookie de session est
 * donc transmis. L'accès par clef API reste ouvert pour les clients qui n'ont pas de
 * session (application mobile, tuile partagée, scénario). Dans les deux cas, le droit de
 * lecture sur l'équipement est vérifié plus bas, une fois celui-ci résolu. */
$viaSession = isConnect();
$viaApi = !$viaSession
	&& (jeedom::apiAccess(init('apikey')) || jeedom::apiAccess(init('apikey'), 'gds3710'));
if (!$viaSession && !$viaApi) {
	log::add('gds3710', 'error', 'Accès non autorisé à camera.php depuis ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?'));
	header('HTTP/1.1 401 Unauthorized');
	die();
}

log::add('gds3710', 'debug', 'Call to camera.php in progress.');

if(!isset($_GET['id']) || $_GET['id'] == ''){
	log::add('gds3710', 'error', 'No id parameter provided to camera.php.');
	die();
}

$eqId = $_GET['id'];
/* eqLogic::byId() ne prend qu'un parametre : le second, « gds3710 », n'a jamais rien
 * filtre. Le type se verifie donc explicitement, comme le fait le modal d'historique. */
$gds3710 = gds3710::byId($eqId);
if (!is_object($gds3710) || $gds3710->getEqType_name() !== 'gds3710') {
	log::add('gds3710', 'error', 'No GDS3710 equipment with id : '.$eqId);
	die();
}

/* Le bloc A avait ferme l'acces anonyme, mais pas celui d'un utilisateur sans droit sur CET
 * equipement : tout compte Jeedom voyait le flux de toutes les portes de l'installation.
 *
 * Deux facons d'arriver ici, deux facons de verifier le droit :
 *  - par session, c'est l'utilisateur connecte qui compte ;
 *  - par clef API, jeedom::apiAccess() accepte AUSSI la clef personnelle d'un utilisateur
 *    et publie alors son compte dans $_USER_GLOBAL. Sans ce second cas, un utilisateur
 *    restreint contournait le controle avec sa propre clef. Une clef de plugin ou du coeur,
 *    elle, ne designe aucun compte : c'est une autorisation machine, et c'est par la que
 *    passent l'application mobile, la tuile partagee et les scenarios. */
global $_USER_GLOBAL;
$demandeur = null;
if ($viaApi && isset($_USER_GLOBAL) && is_object($_USER_GLOBAL)) {
	$demandeur = $_USER_GLOBAL;
}
$autorise = $viaSession ? $gds3710->hasRight('r')
	: ($demandeur === null ? true : $gds3710->hasRight('r', $demandeur));
if (!$autorise) {
	log::add('gds3710', 'error', 'Accès refusé au flux de ' . $gds3710->getHumanName()
		. ' : pas de droit de lecture sur cet équipement.');
	header('HTTP/1.1 403 Forbidden');
	die();
}
$ip = $gds3710->getConfiguration('ip');
$password = $gds3710->getConfiguration('password');
$mac = $gds3710->getConfiguration('macaddress');
$remote_pin = 'GDS3710lDyTlHwNgZ';
$auth_type = $gds3710->getConfiguration('auth_type');
log::add('gds3710', 'debug', 'Config is : '.$mac.' | '.$ip.' | '.$auth_type);

if($auth_type == 'challenge'){

	$ch = curl_init();
	$optArray = array(
	    CURLOPT_URL => 'https://'.$ip.'/jpeg/stream?type=0&user=admin',
	    CURLOPT_SSL_VERIFYPEER  => false,
	    CURLOPT_SSL_VERIFYHOST => false,
	    CURLOPT_RETURNTRANSFER => true
	);
	curl_setopt_array($ch, $optArray);
	$AuthRequestResponse = curl_exec($ch);
	log::add('gds3710', 'debug', 'Auth Request Response : '.print_r($AuthRequestResponse,true));
	$auth_challenge = gds3710::parseXml($AuthRequestResponse, 'flux MJPEG');
	if ($auth_challenge === null) {
		header('HTTP/1.1 502 Bad Gateway');
		die();
	}
	$ChallengeCode = $auth_challenge->ChallengeCode[0];
	$IDCode = $auth_challenge->IDCode[0];

	$auth_response = md5($ChallengeCode.":".$remote_pin.":".$password);

	$mjpeg_url = 'https://'.$ip.'/jpeg/stream?type=1&user=admin&authcode='.$auth_response.'&idcode='.$IDCode;
	log::add('gds3710', 'debug', 'MJPEG url is : '.gds3710::redact($mjpeg_url));

} elseif ($auth_type == 'basic'){

	/* Les identifiants passaient dans l'URL (https://admin:motdepasse@ip/...). Le
	 * resultat est le meme — le wrapper HTTP de PHP en fait un en-tete Authorization —
	 * mais un echec de fopen() emettait un warning PHP contenant l'URL complete, mot de
	 * passe compris, dans le log du serveur web : hors de portee de redact(), qui ne
	 * couvre que le log du plugin. L'en-tete est donc pose explicitement, et l'URL ne
	 * porte plus aucun secret. */
	$mjpeg_url = 'https://'.$ip.'/jpeg/stream';
	log::add('gds3710', 'debug', 'MJPEG url is : '.$mjpeg_url);

} else {

	die();

}

$headers = "Accept-language: en\r\n" . "Cookie: foo=bar\r\n";
if ($auth_type == 'basic') {
	$headers .= 'Authorization: Basic ' . base64_encode('admin:' . $password) . "\r\n";
}
$opts = array(
	'http'=>array(
			'method'=>"GET",
			'header'=>$headers
		),
			'ssl'=>[
			'verify_peer' => false,
			'verify_peer_name' => false
			]
	);

$context = stream_context_create($opts);
set_time_limit(0);
/* apache_setenv nexiste que sous le SAPI Apache. Sous php-fpm lappel est un fatal,
 * que loperateur @ ne masque pas. */
if (function_exists('apache_setenv')) {
	@apache_setenv('no-gzip', 1);
}
@ini_set('zlib.output_compression', 0);

/* @ : l'echec est gere par la branche else ci-dessous. Sans lui, le warning PHP part
 * dans le log du serveur web — c'est par la que l'URL a identifiants fuyait. */
$fp = @fopen($mjpeg_url, 'r', false, $context);

if ($fp) {
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");
	header("Content-type: multipart/x-mixed-replace; boundary===MJPEGIMAGEBOUNDARY==");
	fpassthru($fp);
	fclose($fp);
} else {
	log::add('gds3710', 'debug', 'Unable to get Camera MJPEG');
	/* Le chemin etait relatif et ce fichier nexiste pas dans le depot : la branche de
	 * repli echouait systematiquement. */
	$fallback = __DIR__ . '/../img/no-image.png';
	$d = file_exists($fallback) ? file_get_contents($fallback) : '';
	Header("Content-Type: image/png");
	Header("Content-Length: ".strlen($d));
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");
	echo $d;
}

?>
