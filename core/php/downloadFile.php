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

 try {
 	require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
 	include_file('core', 'authentification', 'php');
	if (!isConnect() && !jeedom::apiAccess(init('apikey'))) {
		throw new Exception(__('401 - Accès non autorisé1', __FILE__), 401);
	}
	$pathfile = calculPath(urldecode(init('pathfile')));
	if(strpos($pathfile,'*') !== false){

	}else{
		$pathfile = realpath($pathfile);
	}

	if ($pathfile === false) {
		throw new Exception(__('401 - Accès non autorisé2', __FILE__), 401);
	}
	if (strpos($pathfile, '.php') !== false) {
		throw new Exception(__('401 - Accès non autorisé3', __FILE__), 401);
	}

	$rootPath = realpath(dirname(__FILE__) . '/../../');

	if (strpos($pathfile, $rootPath) === false) {
		if (config::byKey('recdir', 'gds3710') != '' && substr(config::byKey('recdir', 'gds3710'), 0, 1) == '/') {
			$cameraPath = realpath(config::byKey('recdir', 'gds3710'));
			if (strpos($pathfile, $cameraPath) === false) {
				throw new Exception(__('401 - Accès non autorisé4', __FILE__), 401);
			}
		} else {
			throw new Exception(__('401 - Accès non autorisé5', __FILE__), 401);
		}
	}
	if (!isConnect('admin')) {
		$adminFiles = array('log', 'backup', '.sql', 'scenario', '.tar', '.gz');
		foreach ($adminFiles as $adminFile) {
			if (strpos($pathfile, $adminFile) !== false) {
				throw new Exception(__('401 - Accès non autorisé6', __FILE__), 401);
			}
		}
	}
	// CAS FICHIER UNIQUE
	if (strpos($pathfile, '*') === false) {
		if (!file_exists($pathfile)) {
			throw new Exception(__('Fichier non trouvé : ', __FILE__) . $pathfile, 404);
		}
	} else {
		/* Cas dun motif : « <dossier>/* » (tout le dossier) ou « <dossier>/<prefixe>* »
		 * (une journee). Ces deux branches construisaient la commande tar par concatenation,
		 * et le chemin nayant pas ete normalise par realpath() dans ce cas precis, tout
		 * metacaractere du shell present dans le parametre etait interprete. On developpe
		 * desormais le motif avec glob(), on revalide chaque resultat par son chemin reel,
		 * et on echappe chaque argument. */
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé7', __FILE__), 401);
		}

		$dir = realpath(dirname($pathfile));
		if ($dir === false) {
			throw new Exception(__('401 - Accès non autorisé9', __FILE__), 401);
		}

		$allowedRoots = array(realpath(dirname(__FILE__) . '/../../'));
		if (config::byKey('recdir', 'gds3710') != '' && substr(config::byKey('recdir', 'gds3710'), 0, 1) == '/') {
			$allowedRoots[] = realpath(config::byKey('recdir', 'gds3710'));
		}
		$insideAllowedRoot = false;
		foreach ($allowedRoots as $root) {
			if ($root !== false && strpos($dir . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) === 0) {
				$insideAllowedRoot = true;
				break;
			}
		}
		if (!$insideAllowedRoot) {
			throw new Exception(__('401 - Accès non autorisé10', __FILE__), 401);
		}

		$args = array();
		foreach ((array) glob($dir . '/' . basename($pathfile)) as $match) {
			$real = realpath($match);
			if ($real !== false && is_file($real) && strpos($real, $dir . DIRECTORY_SEPARATOR) === 0) {
				$args[] = escapeshellarg(basename($real));
			}
		}
		if (count($args) === 0) {
			throw new Exception(__('Aucun fichier a telecharger.', __FILE__), 404);
		}

		$archive = jeedom::getTmpFolder('downloads') . '/archive.tar.gz';
		system('cd ' . escapeshellarg($dir) . ' && tar cfz ' . escapeshellarg($archive)
			. ' ' . implode(' ', $args) . ' > /dev/null 2>&1');
		$pathfile = $archive;
	}
	$path_parts = pathinfo($pathfile);
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . $path_parts['basename']);
	readfile($pathfile);
	if (file_exists(jeedom::getTmpFolder('downloads') . '/archive.tar.gz')) {
		unlink(jeedom::getTmpFolder('downloads') . '/archive.tar.gz');
	}
	exit;
 } catch (Exception $e) {
 	/* Le refus repondait 200 avec le message dans le corps : pour un navigateur, pour
 	 * une balise <img> et pour tout appelant qui lit le statut, un acces refuse
 	 * ressemblait donc a un telechargement reussi. On rend le code qui convient.
 	 * Le code porte par l'exception evite de deduire le statut du message, qui est
 	 * traduit et changerait donc selon la langue. */
 	$codes = array(401 => 'Unauthorized', 404 => 'Not Found');
 	$code = (int) $e->getCode();
 	if (!isset($codes[$code])) {
 		$code = 400;
 		$codes[400] = 'Bad Request';
 	}
 	if (!headers_sent()) {
 		header('HTTP/1.1 ' . $code . ' ' . $codes[$code]);
 		header('Content-Type: text/plain; charset=UTF-8');
 	}
 	echo $e->getMessage();
 }