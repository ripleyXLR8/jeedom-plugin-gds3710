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

function getTypeFromLogicalID($type_requested){
    $cmd_array_gds = gds3710::get_GDS3710_event_list();

    foreach ($cmd_array_gds as $row){
        if($row['type'] == $type_requested){
            log::add('gds3710','debug','Type : '.$type_requested.' is match the logicalID '.$row['short_name']);
            return $row['short_name'];
        }
    }
}

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

    foreach ($action_list as $action) { // On va itérer sur la liste des commandes présentes dans la configuration

        try {
            $cmd = cmd::byId(str_replace('#', '', $action['cmd']));
            if (is_object($cmd) && $this->getId() == $cmd->getEqLogic_id()) {
                continue;
            }
            $options = array();
            if (isset($action['options'])) { 
                $options = $action['options'];

                if(isset($action['options']['scenario_id'])){
                    $options['tags'] = $options['tags'].' mac="'.$_POST['mac'].'" content="'.$_POST['content'].'" type="'.$_POST['type'].'" warning="'.$_POST['warning'].'" date="'.$_POST["date"].'" card="'.$_POST['card'].'" sip="'.$_POST['sip'].'"';
                    
                }
                foreach ($options as $key => $value) {
                    log::add('gds3710','debug',$key.' : '.$value);
                }
            }
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