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

if(!isset($_GET['mac']) || $_GET['mac'] == ''){
	die();
}

$mac = $_GET['mac'];
$gds3710 = gds3710::byLogicalId($mac, 'gds3710');
$ip = $gds3710->getConfiguration('ip');
$password = $gds3710->getConfiguration('password');
$remote_pin = 'GDS3710lDyTlHwNgZ';
$auth_type = $gds3710->getConfiguration('auth_type');

if($auth_type == 'challenge'){

	$ch = curl_init();
	$optArray = array(
	    CURLOPT_URL => 'https://'.$ip.'/jpeg/stream?type=0&user=admin',
	    CURLOPT_SSL_VERIFYPEER  => false,
	    CURLOPT_SSL_VERIFYHOST => false,
	    CURLOPT_RETURNTRANSFER => true
	);
	curl_setopt_array($ch, $optArray);
	$auth_challenge = @simplexml_load_string(curl_exec($ch));
	$ChallengeCode = $auth_challenge->ChallengeCode[0];
	$IDCode = $auth_challenge->IDCode[0];

	$auth_response = md5($ChallengeCode.":".$remote_pin.":".$password);

	$mjpeg_url = 'https://'.$ip.'/jpeg/stream?type=1&user=admin&authcode='.$auth_response.'&idcode='.$IDCode;

} elseif ($auth_type == 'basic'){

	$mjpeg_url = 'https://admin:'.$password.'@'.$ip.'/jpeg/stream';

} else {

	die();

}

$opts = array(
	'http'=>array(
			'method'=>"GET",
			'header'=>"Accept-language: en\r\n" .
			"Cookie: foo=bar\r\n"
		),
			'ssl'=>[
			'verify_peer' => false,
			'verify_peer_name' => false
			]
	);

$context = stream_context_create($opts);
set_time_limit(0);
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);

$fp = fopen($mjpeg_url, 'r', false, $context);

if ($fp) {
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");
	header("Content-type: multipart/x-mixed-replace; boundary===MJPEGIMAGEBOUNDARY==");
	fpassthru($fp);
	fclose($fp);
} else {
	$d = file_get_contents("no-image-noir.png");
	Header("Content-Type: image/png");
	Header("Content-Length: ".strlen($d));
	header("Cache-Control: no-cache");
	header("Cache-Control: private");
	header("Pragma: no-cache");
	echo $d;
}

?>
