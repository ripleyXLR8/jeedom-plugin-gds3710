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
}

function gds3710_remove() {

}

?>
