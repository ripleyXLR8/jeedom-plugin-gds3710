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

    if(isConnect('user') && config::byKey('is_user_allowed_to_delete', 'gds3710') !=1 ){

        throw new Exception(__('401 - Les utilisateurs ne sont pas autorisés à effacer les captures.', __FILE__));

    } elseif(isConnect('restrict') && config::byKey('is_limited_user_allowed_to_delete', 'gds3710') !=1) {

        throw new Exception(__('401 - Les utilisateurs limités ne sont pas autorisés à effacer les captures.', __FILE__));

    }
    
    ajax::init();

    if (init('action') == 'removeRecord') {
		$file = init('file');
        $file_cleaned = str_replace('..', '', $file);
		$record_dir = calculPath(config::byKey('recdir', 'gds3710'));
		shell_exec('rm -rf ' . $record_dir . '/' . $file_cleaned);

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
            $lastest_snapshot->event(realpath($output_file));
            $lastest_snapshot_URL->event(substr($output_file, strpos($output_file, '/plugins')));
            log::add('gds3710', 'debug','New value of lastest_snapshot is :'.realpath($output_file));
            log::add('gds3710', 'debug','New value of lastest_snapshot_URL is :'.substr($output_file, strpos($output_file, '/plugins')));
        } else {
            log::add('gds3710', 'debug','No more files in the directory.');
            $lastest_snapshot_URL->event('');
            $lastest_snapshot->event('');
        }
    }
}