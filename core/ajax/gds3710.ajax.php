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

    ajax::init();

    /* Un administrateur peut toujours supprimer. Pour les autres profils la permission
     * dépend de la configuration du plugin. L'admin est écarté en premier car
     * isConnect('user') est également vrai pour lui : sans cela, un administrateur se
     * retrouvait bloqué dès que l'option « autoriser les utilisateurs » était décochée. */
    if (!isConnect('admin')) {
        $allowed = isConnect('user')
            ? config::byKey('is_user_allowed_to_delete', 'gds3710') == 1
            : config::byKey('is_limited_user_allowed_to_delete', 'gds3710') == 1;
        if (!$allowed) {
            throw new Exception(__('401 - Suppression des captures non autorisée pour ce profil.', __FILE__));
        }
    }

    if (init('action') == 'checkRecordDir') {
        /* Contrôle des droits du répertoire des captures, depuis la page de configuration.
         * Jusqu'ici le problème n'était signalé qu'au moment d'une capture, c'est-à-dire
         * précisément quand personne ne regarde.
         *
         * Réservé aux administrateurs : ce point d'entrée renseigne sur le système de
         * fichiers du serveur, et le garde en tête de ce fichier laisse passer d'autres
         * profils quand la suppression de captures leur est ouverte. */
        if (!isConnect('admin')) {
            throw new Exception(__('401 - Contrôle réservé aux administrateurs.', __FILE__));
        }

        $saisi = trim((string) init('recdir'));
        if ($saisi === '') {
            $saisi = (string) config::byKey('recdir', 'gds3710');
        }
        if ($saisi === '') {
            throw new Exception(__('Aucun répertoire n\'est renseigné.', __FILE__));
        }

        /* On contrôle ce qui est saisi à l'écran, pas seulement ce qui est enregistré :
         * l'intérêt est de valider un chemin avant de le sauvegarder. */
        $chemin = calculPath($saisi);
        $reel = realpath($chemin);
        $etat = array('chemin' => $reel !== false ? $reel : $chemin, 'ok' => false);

        if ($reel === false) {
            /* Le répertoire n'existe pas encore : le plugin le créera à la première
             * capture, à condition de pouvoir écrire dans le parent. */
            $parent = realpath(dirname($chemin));
            $etat['existe'] = false;
            $etat['parent'] = $parent !== false ? $parent : dirname($chemin);
            $etat['ok'] = $parent !== false && is_dir($parent) && is_writable($parent);
            $etat['message'] = $etat['ok']
                ? __('Le répertoire n\'existe pas encore, mais il pourra être créé : son parent est accessible en écriture.', __FILE__)
                : __('Le répertoire n\'existe pas et son parent n\'est pas accessible en écriture : aucune capture ne pourra être enregistrée.', __FILE__);
        } elseif (!is_dir($reel)) {
            $etat['existe'] = true;
            $etat['message'] = __('Ce chemin existe mais n\'est pas un répertoire.', __FILE__);
        } else {
            $etat['existe'] = true;
            $etat['inscriptible'] = is_writable($reel);
            $etat['droits'] = substr(sprintf('%o', fileperms($reel)), -4);
            if (function_exists('posix_getpwuid')) {
                $u = @posix_getpwuid(fileowner($reel));
                $g = @posix_getgrgid(filegroup($reel));
                $etat['proprietaire'] = (is_array($u) ? $u['name'] : fileowner($reel))
                    . ':' . (is_array($g) ? $g['name'] : filegroup($reel));
            }
            $etat['captures'] = count((array) glob($reel . '/*/*.jpg'));
            $etat['ok'] = $etat['inscriptible'];
            $etat['message'] = $etat['inscriptible']
                ? __('Le répertoire existe et le serveur web peut y écrire.', __FILE__)
                : __('Le répertoire existe mais le serveur web ne peut pas y écrire. Il doit appartenir à l\'utilisateur du serveur web.', __FILE__);
        }
        ajax::success($etat);
    }

    if (init('action') == 'getSipConfig') {
        /* Sert la configuration SIP au widget. Elle contient le mot de passe du compte,
         * elle ne transite donc pas par la valeur de la commande mais par cet appel,
         * soumis a la session Jeedom et aux droits sur l equipement. */
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getEqType_name() !== 'gds3710') {
            throw new Exception(__('Equipement GDS3710 introuvable.', __FILE__));
        }
        if (!$eqLogic->hasRight('r')) {
            throw new Exception(__('401 - Acces non autorise a cet equipement.', __FILE__));
        }
        if (!$eqLogic->isSipConfigured()) {
            throw new Exception(__('Client SIP non configure : renseignez au moins l adresse du serveur et l URI du client dans la configuration de l equipement.', __FILE__));
        }
        ajax::success($eqLogic->getSipConfig());
    }

    if (init('action') == 'getCmdValues') {
        /* Valeurs courantes des commandes, pour la page de configuration. La colonne
         * lisait auparavant un champ de configuration que plus rien n alimente. */
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getEqType_name() !== 'gds3710') {
            throw new Exception(__('Equipement GDS3710 introuvable.', __FILE__));
        }
        if (!$eqLogic->hasRight('r')) {
            throw new Exception(__('401 - Acces non autorise a cet equipement.', __FILE__));
        }
        $valeurs = array();
        foreach ($eqLogic->getCmd('info') as $cmd) {
            $valeurs[$cmd->getId()] = array(
                'value' => (string) $cmd->execCmd(),
                'date'  => (string) $cmd->getCollectDate(),
            );
        }
        ajax::success($valeurs);
    }

    if (init('action') == 'callAnswered') {
        /* Le widget signale qu il a decroche : il n y a donc pas d appel manque. */
        $eqLogic = eqLogic::byId(init('id'));
        if (!is_object($eqLogic) || $eqLogic->getEqType_name() !== 'gds3710') {
            throw new Exception(__('Equipement GDS3710 introuvable.', __FILE__));
        }
        if (!$eqLogic->hasRight('r')) {
            throw new Exception(__('401 - Acces non autorise a cet equipement.', __FILE__));
        }
        $eqLogic->cancelMissedCallReport();
        ajax::success();
    }

    if (init('action') == 'removeRecord') {
        $file = init('file');
        $record_dir = realpath(calculPath(config::byKey('recdir', 'gds3710')));

        if ($record_dir === false) {
            throw new Exception(__('Répertoire des captures introuvable.', __FILE__));
        }
        /* $file est un motif : « <id>/<fichier> », « <id>/<nom>_<date>* » pour une journée,
         * ou « <id>/* » pour tout le répertoire. On refuse d'emblée ce qui pourrait sortir
         * de l'arborescence, puis on revalide chaque résultat de glob() par son chemin réel.
         *
         * L'octet nul s'écrit "\0", jamais littéralement : un octet nul brut dans le source
         * fait classer le fichier en binaire par Git — plus aucun diff, plus aucun git grep —
         * et le premier outil qui le normalise transforme le garde en strpos($file, ""),
         * qui vaut 0 en PHP 8 et refuse alors toutes les suppressions. */
        if ($file === '' || strpos($file, '..') !== false || strpos($file, "\0") !== false) {
            throw new Exception(__('401 - Chemin de capture non autorisé.', __FILE__));
        }

        $prefix = $record_dir . DIRECTORY_SEPARATOR;
        $removed = 0;
        foreach ((array) glob($prefix . $file, GLOB_NOSORT) as $match) {
            $real = realpath($match);
            if ($real === false || strpos($real, $prefix) !== 0 || !is_file($real)) {
                continue;
            }
            if (unlink($real)) {
                $removed++;
            }
        }
        log::add('gds3710', 'debug', 'Suppression de captures : ' . $removed . ' fichier(s) pour le motif ' . $file);

        get_last_snapshot_url_gds($file);

        ajax::success();
    }

    throw new Exception(__('Aucune méthode correspondante à : ', __FILE__) . init('action'));
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayException($e), $e->getCode());
}

function get_last_snapshot_url_gds($file_name){
    $gds_id = explode("/", $file_name)[0];
    $gds3710 = eqLogic::byId($gds_id);
    $lastest_snapshot_URL = $gds3710->getCmd(null, 'Lastest_Snapshot_URL');
    $lastest_snapshot = $gds3710->getCmd(null, 'Lastest_Snapshot_Path');
    $FilePath = $lastest_snapshot->execCmd();

    if(!file_exists($FilePath)){
        log::add('gds3710', 'debug','Lastest snapshot has been deleted - changing value of lastest_snapshot and lastest_snapshot_URL');
        $record_dir = calculPath(config::byKey('recdir', 'gds3710'));
        $files = scandir($record_dir . '/' .$gds_id.'/', SCANDIR_SORT_DESCENDING);
        if(count($files) > 2 ){
            $output_file = $record_dir . '/' .$gds_id.'/'.$files[0];
            $url = gds3710::urlPublique($output_file);
            $lastest_snapshot->event(realpath($output_file));
            $lastest_snapshot_URL->event($url);
            log::add('gds3710', 'debug','New value of lastest_snapshot is :'.realpath($output_file));
            log::add('gds3710', 'debug','New value of lastest_snapshot_URL is :'.$url);
        } else {
            log::add('gds3710', 'debug','No more files in the directory.');
            $lastest_snapshot_URL->event('');
            $lastest_snapshot->event('');
        }
    }
}