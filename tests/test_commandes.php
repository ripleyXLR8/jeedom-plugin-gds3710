<?php

/* Table des commandes fixes de l'equipement.
 *
 * Ces 23 commandes etaient creees par autant de blocs recopies dans postSave(). Les
 * decrire dans une table retire plus de 400 lignes, mais fait courir un risque precis :
 * qu'une commande disparaisse ou change d'attribut au passage, et donc du tableau de bord
 * de l'utilisateur — ou pire, qu'un identifiant change et qu'un scenario cesse de
 * fonctionner sans un mot.
 *
 * La liste ci-dessous est relevee sur le code d'avant la table. Elle est figee : elle ne
 * doit etre modifiee que pour une raison enoncee, et jamais pour faire passer un test. */

$attendu = array(
    'ldc_ON'                => array('action', 'other',   1),
    'ldc_off'               => array('action', 'other',   1),
    'reboot'                => array('action', 'other',   1),
    'open'                  => array('action', 'other',   1),
    'open2'                 => array('action', 'other',   1),
    'close'                 => array('action', 'other',   0),
    'close2'                => array('action', 'other',   0),
    'snapshot'              => array('action', 'other',   1),
    'modifyConfig'          => array('action', 'message', 0),
    'sendSnapshot'          => array('action', 'message', 0),
    'Open_Snapshots_Folder' => array('action', 'other',   1),
    'Lastest_Snapshot_Path' => array('info',   'string',  0),
    'Lastest_Snapshot_URL'  => array('info',   'string',  0),
    'cmos_normal'           => array('action', 'other',   1),
    'cmos_lowlight'         => array('action', 'other',   1),
    'cmos_wdr'              => array('action', 'other',   1),
    'stream_mjpeg'          => array('info',   'string',  1),
    'Last event'            => array('info',   'string',  0),
    'sip_client'            => array('info',   'string',  0),
    'backlight_schedule'    => array('info',   'binary',  0),
    'backlight_hours'       => array('info',   'string',  0),
    'backlight_hours_set'   => array('action', 'message', 0),
    'configureDoorbell'     => array('action', 'other',   0),
);

$table = gds3710::get_command_list();

Verif::bloc('aucune commande perdue ni ajoutee par megarde');

Verif::egal(array_keys($attendu), array_keys($table),
    'la table decrit exactement les memes commandes, dans le meme ordre');

Verif::bloc('aucun attribut modifie au passage');

$ecarts = array();
foreach ($attendu as $lid => $ref) {
    if (!isset($table[$lid])) { continue; }
    $def = $table[$lid];
    if ($def['type'] !== $ref[0]) {
        $ecarts[] = $lid . ' : type ' . $def['type'] . ' au lieu de ' . $ref[0];
    }
    if ($def['subType'] !== $ref[1]) {
        $ecarts[] = $lid . ' : subType ' . $def['subType'] . ' au lieu de ' . $ref[1];
    }
    $visible = isset($def['visible']) ? (int) $def['visible'] : 0;
    if ($visible !== $ref[2]) {
        $ecarts[] = $lid . ' : visible ' . $visible . ' au lieu de ' . $ref[2];
    }
}
Verif::egal(array(), $ecarts, 'type, sous-type et visibilite par defaut sont inchanges');

Verif::bloc('gabarits et affichages preserves');

/* Un gabarit perdu ne casse rien de visible tout de suite : la commande s'affiche avec le
 * widget par defaut, et c'est l'utilisateur qui finit par le signaler. */
$gabarits = array(
    'snapshot'              => array('dashboard' => ''),
    'Open_Snapshots_Folder' => array('dashboard' => 'snapshot_folder'),
    'Lastest_Snapshot_URL'  => array('dashboard' => 'lastsnapshot', 'mobile' => 'lastsnapshot'),
    'stream_mjpeg'          => array('dashboard' => 'mjpegstream', 'mobile' => 'mjpegstream'),
    'sip_client'            => array('dashboard' => 'sipclient'),
);
foreach ($gabarits as $lid => $attendus) {
    $pose = isset($table[$lid]['template']) ? $table[$lid]['template'] : array();
    Verif::egal($attendus, $pose, $lid . ' conserve son gabarit');
}

/* Les commandes « message » ont besoin de leurs libelles de saisie : sans eux, l'ecran
 * presente deux champs vides sans indiquer ce qu'on attend. */
foreach (array('modifyConfig', 'sendSnapshot', 'backlight_hours_set') as $lid) {
    $display = isset($table[$lid]['display']) ? $table[$lid]['display'] : array();
    Verif::vrai(isset($display['title_placeholder']) && isset($display['message_placeholder']),
        $lid . ' conserve les libelles de ses deux champs');
}
Verif::vrai(isset($table['snapshot']['display']['icon']), 'snapshot conserve son icone');
Verif::vrai(isset($table['configureDoorbell']['display']['icon']), 'configureDoorbell conserve son icone');
Verif::egal('-', $table['sendSnapshot']['configuration']['request'],
    'sendSnapshot conserve sa configuration');

Verif::bloc('la table est bien formee');

$incomplets = array();
foreach ($table as $lid => $def) {
    foreach (array('name', 'type', 'subType') as $champ) {
        if (!isset($def[$champ]) || $def[$champ] === '') {
            $incomplets[] = $lid . ' (' . $champ . ')';
        }
    }
    if (!in_array($def['type'], array('action', 'info'), true)) {
        $incomplets[] = $lid . ' (type inconnu : ' . $def['type'] . ')';
    }
}
Verif::egal(array(), $incomplets, 'chaque entree porte un nom, un type et un sous-type valides');

/* Les identifiants ne doivent heurter ni ceux des etats, ni ceux des reglages, ni les
 * curseurs qu'ils engendrent — la collation de MySQL ignore la casse. */
$autres = array();
foreach (array_keys(gds3710::get_state_list()) as $lid) { $autres[strtolower($lid)] = 'etat'; }
foreach (gds3710::get_setting_list() as $lid => $def) {
    $autres[strtolower($lid)] = 'reglage';
    $autres[strtolower($lid . '_set')] = 'curseur de reglage';
}
foreach (array_keys(gds3710::get_event_detail_list()) as $lid) { $autres[strtolower($lid)] = 'detail d evenement'; }

$heurts = array();
foreach ($table as $lid => $def) {
    $n = strtolower($lid);
    if (isset($autres[$n])) {
        $heurts[] = $lid . ' heurte un ' . $autres[$n];
    }
}
Verif::egal(array(), $heurts, 'aucun identifiant ne heurte une commande engendree ailleurs');

Verif::bloc('les choix de l utilisateur ne sont plus ecrases');

/* Vingt de ces commandes reimposaient leur visibilite a CHAQUE enregistrement de
 * l'equipement — « Ouvrir la porte 2 » comprise, alors que la documentation du client SIP
 * promet que la masquer retire son bouton de la fenetre d'appel. La visibilite n'est
 * desormais qu'une valeur de creation, comme le nom. */
$postSave = '';
foreach (gds3710_fichiers_php() as $nom => $contenu) {
    if (strpos($nom, 'gds3710.class.php') !== false) {
        $i = strpos($contenu, 'function postSave()');
        $j = strpos($contenu, 'function lierCommandesAuxEtats');
        $postSave = substr($contenu, $i, $j - $i);
    }
}
Verif::vrai($postSave !== '', 'le corps de postSave() a bien ete retrouve');

/* Tout setName() y est precede de son garde de nom vide. Le seul autre cas admis est le
 * renommage des commandes d'evenement, qui remplace l'ancien libelle purement numerique
 * et porte son propre garde. */
$sansGarde = array();
$offset = 0;
while (($k = strpos($postSave, '->setName(', $offset)) !== false) {
    $avant = substr($postSave, max(0, $k - 300), min(300, $k));
    if (strpos($avant, "getName()) === ''") === false && strpos($avant, "getName() === (string)") === false) {
        $sansGarde[] = trim(substr($postSave, $k, 60));
    }
    $offset = $k + 10;
}
Verif::egal(array(), $sansGarde,
    'aucun nom n est repose sans verifier d abord qu il est vide');

Verif::bloc('aucune variable orpheline dans postSave()');

/* Retirer un bloc de creation laisse orpheline la variable qu'il posait, si une boucle
 * plus bas s'en sert encore — c'est arrive en ecrivant cette table meme : le bloc du
 * planning de retroeclairage supprime, la boucle suivante appelait toujours
 * $backlight->getId(). PHP ne s'en plaint qu'a l'execution, et l'equipement devient
 * insauvegardable : postSave() est appele a chaque enregistrement.
 *
 * On verifie donc que toute variable dereferencee dans postSave() y recoit une valeur
 * auparavant. Les variables de boucle foreach et $this comptent comme definies. */
$definies = array('this' => true);
foreach (array('/\$(\w+)\s*=(?!=)/', '/as\s+\$(\w+)\s*=>\s*\$(\w+)/', '/as\s+\$(\w+)\s*\)/') as $motif) {
    if (preg_match_all($motif, $postSave, $m)) {
        foreach ($m[1] as $v) { $definies[$v] = true; }
        if (isset($m[2])) {
            foreach ($m[2] as $v) { $definies[$v] = true; }
        }
    }
}
$orphelines = array();
if (preg_match_all('/\$(\w+)->/', $postSave, $m)) {
    foreach (array_unique($m[1]) as $v) {
        if (!isset($definies[$v])) {
            $orphelines[] = '$' . $v;
        }
    }
}
Verif::egal(array(), $orphelines,
    'toute variable utilisee dans postSave() y est definie avant emploi');
