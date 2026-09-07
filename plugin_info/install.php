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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

/* Ces fonctions doivent porter le nom du plugin pour que le coeur les appelle.
 * Elles s'appelaient template_*() depuis la creation du plugin et n'ont donc
 * jamais ete executees. */

function gds3710_install() {
    /* Nouvelle installation : la protection par mot de passe de l'endpoint
     * d'evenements est active par defaut. Les installations existantes passent par
     * gds3710_update() et conservent leur reglage, pour ne pas voir leur remontee
     * d'evenements s'interrompre a la mise a jour. */
    config::save('password_protection', 1, 'gds3710');
}

function gds3710_update() {
    if (config::byKey('password_protection', 'gds3710', '') === '') {
        config::save('password_protection', 0, 'gds3710');
    }

    /* La valeur de la commande « Stream MJPEG » nest ecrite que par postSave(). Elle se
     * perd des quun evenement la vide — notamment un vidage de cache, qui remet a blanc
     * toutes les valeurs de commandes de linstallation. Le widget affichait alors une
     * image vide et le log crachait « No id parameter provided to camera.php », le seul
     * remede connu etant de re-sauvegarder chaque equipement a la main. On republie ici,
     * et le cron repare aussi en continu. */
    foreach (eqLogic::byType('gds3710') as $eq) {
        $cmd = $eq->getCmd('info', 'stream_mjpeg');
        if (is_object($cmd)) {
            $cmd->event('/plugins/gds3710/core/php/camera.php?id=' . $eq->getId());
        }
    }

    gds3710_clean_html_values();
    gds3710_restore_ldc_commands();
}

/* Les commandes LDC avaient ete retirees a tort. P10573 n est renvoye par aucune section
 * de configuration du portier, et la relecture ajoutee en meme temps l avait donc classe
 * « inconnu de l appareil ». Il est en realite bien accepte et applique — mais seulement
 * au demarrage suivant, ce qui rendait le bouton muet a l usage. Constate sur un GDS3710
 * en 1.0.13.15 : l effet est apparu apres un redemarrage. On les recree pour les
 * installations ou la mise a jour precedente les a supprimees. */
function gds3710_restore_ldc_commands() {
    $recrees = 0;
    foreach (eqLogic::byType('gds3710') as $eq) {
        foreach (array('ldc_ON' => __('LDC - ON', __FILE__), 'ldc_off' => __('LDC - OFF', __FILE__)) as $lid => $nom) {
            if (is_object($eq->getCmd('action', $lid))) {
                continue;
            }
            $cmd = new gds3710Cmd();
            $cmd->setName($nom);
            $cmd->setEqLogic_id($eq->getId());
            $cmd->setLogicalId($lid);
            $cmd->setType('action');
            $cmd->setSubType('other');
            $cmd->setIsVisible(1);
            $cmd->save();
            $recrees++;
        }
    }
    if ($recrees > 0) {
        log::add('gds3710', 'info', $recrees . ' commande(s) LDC recreee(s) : le reglage existe '
            . 'bien, son effet apparait apres un redemarrage du portier.');
    }
    return $recrees;
}

/* Jusquen mars 2023, le tableau des commandes ouvrait sa zone de saisie avec une balise
 * mal formee (« readonly=true/></textarea> »). Le HTML de la ligne se retrouvait alors
 * enregistre dans configuration.value, et y restait : le correctif de lepoque a stoppe la
 * creation de nouvelles corruptions mais na jamais nettoye celles deja en base. Elles
 * survivent donc sur toutes les installations anterieures. */
function gds3710_clean_html_values() {
    $cleaned = 0;
    foreach (eqLogic::byType('gds3710') as $eq) {
        foreach ($eq->getCmd() as $cmd) {
            $value = (string) $cmd->getConfiguration('value');
            if ($value === '') {
                continue;
            }
            /* « Array » est le resultat d une conversion tableau-vers-chaine de
             * l ancien code : la colonne affichait ce mot au lieu de la charge utile. */
            if ($value === 'Array') {
                $cmd->setConfiguration('value', '');
                $cmd->save();
                $cleaned++;
                continue;
            }
            if (strpos($value, '<') === false) {
                continue;
            }
            /* On ne vise que le balisage de linterface, jamais une valeur legitime :
             * les evenements du portier sont du JSON et ne contiennent pas ces balises. */
            if (!preg_match('/<\/?(td|tr|tbody|table|span|label|input|textarea)\b/i', $value)) {
                continue;
            }
            $cmd->setConfiguration('value', '');
            $cmd->save();
            $cleaned++;
            log::add('gds3710', 'info', 'Valeur corrompue nettoyee sur la commande '
                . $cmd->getHumanName() . ' (' . strlen($value) . ' octets de balisage).');
        }
    }
    if ($cleaned > 0) {
        log::add('gds3710', 'info', $cleaned . ' commande(s) nettoyee(s) dune valeur heritee corrompue.');
    }
    return $cleaned;
}

function gds3710_remove() {

}

?>
