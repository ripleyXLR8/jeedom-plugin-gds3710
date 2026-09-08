<?php

/* Coherence des tables de declaration du plugin.
 *
 * Ces tables sont la source de verite de tout le reste : les onglets de la page
 * d'equipement, les commandes creees par postSave(), les actions rattachees aux
 * evenements. Deux defauts reels sont nes d'une incoherence entre leurs entrees :
 *
 *  - « Sécurité » ecrit sans accent sur deux types ajoutes plus tard, d'ou DEUX onglets
 *    securite sur la page d'equipement (corrige le 08/09/2026) ;
 *  - la commande action « reboot » et la commande info « Reboot », indistinguables pour
 *    MySQL dont la collation ignore la casse, d'ou l'equipement insauvegardable
 *    (« Une commande portant ce nom (Reboot) existe deja »).
 *
 * Aucun de ces deux defauts n'etait visible a la lecture. Ils le sont ici. */

/* Retire accents et casse : deux libelles qui se ressemblent a ce point sont une faute
 * de frappe, pas deux intentions distinctes. */
function gds3710_normaliser($_texte) {
    $de = array('à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç',
                'À','Â','Ä','É','È','Ê','Ë','Î','Ï','Ô','Ö','Ù','Û','Ü','Ç');
    $vers = array('a','a','a','e','e','e','e','i','i','o','o','u','u','u','c',
                  'a','a','a','e','e','e','e','i','i','o','o','u','u','u','c');
    return strtolower(str_replace($de, $vers, $_texte));
}

$catalogue = gds3710::get_GDS3710_event_list();

Verif::bloc('catalogue d evenements : structure');

Verif::vrai(count($catalogue) > 0, 'le catalogue n est pas vide');

$champs = array('section', 'section_icon', 'type', 'short_name', 'message', 'use_case');
$incomplets = array();
foreach ($catalogue as $clef => $row) {
    foreach ($champs as $champ) {
        if (!isset($row[$champ]) || $row[$champ] === '') {
            $incomplets[] = $clef . ' (' . $champ . ')';
        }
    }
}
Verif::egal(array(), $incomplets, 'chaque entree porte les six champs attendus');

$clefsIncoherentes = array();
foreach ($catalogue as $clef => $row) {
    if ((string) $clef !== (string) $row['type']) {
        $clefsIncoherentes[] = $clef . ' porte le type ' . $row['type'];
    }
}
Verif::egal(array(), $clefsIncoherentes, 'la clef du tableau est le type de l evenement');

Verif::bloc('catalogue d evenements : unicite');

/* Le short_name devient le logicalId d'une commande info : deux entrees identiques
 * ecriraient dans la meme commande, et l'une des deux serait muette. */
$vus = array();
$doublons = array();
foreach ($catalogue as $row) {
    $n = gds3710_normaliser($row['short_name']);
    if (isset($vus[$n])) {
        $doublons[] = $row['short_name'] . ' (deja pris par le type ' . $vus[$n] . ')';
    }
    $vus[$n] = $row['type'];
}
Verif::egal(array(), $doublons, 'les short_name sont uniques, casse ignoree');

$types = array();
foreach ($catalogue as $row) { $types[] = (int) $row['type']; }
Verif::egal(count($types), count(array_unique($types)), 'les types sont uniques');

Verif::bloc('catalogue d evenements : sections');

/* LE test du defaut du 08/09 : « Sécurité » et « Securite » produisaient deux onglets. */
$sections = array();
foreach ($catalogue as $row) {
    $sections[gds3710_normaliser($row['section'])][$row['section']] = true;
}
$variantes = array();
foreach ($sections as $normalisee => $formes) {
    if (count($formes) > 1) {
        $variantes[] = implode(' / ', array_keys($formes));
    }
}
Verif::egal(array(), $variantes,
    'aucune section ecrite de deux facons (accents ou casse) : sinon la page affiche deux onglets');

/* Une section, une icone : deux icones pour un meme onglet donneraient un affichage
 * dependant de l'ordre des lignes. */
$icones = array();
foreach ($catalogue as $row) {
    $icones[$row['section']][$row['section_icon']] = true;
}
$iconesMultiples = array();
foreach ($icones as $section => $liste) {
    if (count($liste) > 1) {
        $iconesMultiples[] = $section . ' : ' . implode(', ', array_keys($liste));
    }
}
Verif::egal(array(), $iconesMultiples, 'chaque section porte une seule icone');

Verif::bloc('catalogue d evenements : listes derivees');

/* Ces trois listes designent des types par leur numero. Un numero absent du catalogue
 * serait du code mort silencieux : l'evenement ne serait jamais reconnu comme une
 * entree, une alerte de securite ou une sonnerie. */
$connus = array_map('intval', array_keys($catalogue));
foreach (array(
    'get_entry_event_types'    => gds3710::get_entry_event_types(),
    'get_security_event_types' => gds3710::get_security_event_types(),
    'get_ring_event_types'     => gds3710::get_ring_event_types(),
) as $nom => $liste) {
    $inconnus = array_values(array_diff($liste, $connus));
    Verif::egal(array(), $inconnus, $nom . '() ne designe que des types du catalogue');
    Verif::egal(count($liste), count(array_unique($liste)), $nom . '() est sans doublon');
}

/* Un type ne peut pas etre a la fois une entree identifiee et une alerte de securite :
 * dispatchEventDetails() ecrirait « derniere personne entree » sur une tentative
 * d'effraction. Le type 102 (tentative non autorisee) doit etre du seul cote securite. */
$croisement = array_intersect(gds3710::get_entry_event_types(), gds3710::get_security_event_types());
Verif::egal(array(), array_values($croisement),
    'aucun type n est a la fois une entree identifiee et une alerte de securite');

Verif::bloc('etats et reglages : declarations');

$etats = gds3710::get_state_list();
$reglages = gds3710::get_setting_list();

$sansSection = array();
foreach (array('etat' => $etats, 'reglage' => $reglages) as $genre => $table) {
    foreach ($table as $lid => $def) {
        if (!isset($def['section']) || $def['section'] === '' || !isset($def['p']) || $def['p'] === '') {
            $sansSection[] = $genre . ' ' . $lid;
        }
    }
}
Verif::egal(array(), $sansSection, 'chaque etat et chaque reglage nomme sa section et sa P-value');

/* Les bornes servent au controle des ecritures : un min superieur au max rendrait le
 * curseur inutilisable et refuserait toute valeur. */
$bornes = array();
foreach ($reglages as $lid => $def) {
    if (!isset($def['min']) || !isset($def['max']) || $def['min'] >= $def['max']) {
        $bornes[] = $lid;
    }
}
Verif::egal(array(), $bornes, 'chaque reglage a des bornes coherentes');

Verif::bloc('identifiants de commandes : collisions');

/* Les logicalId d'un meme type doivent rester distincts casse ignoree : la colonne est
 * en collation utf8mb3_unicode_ci, donc MySQL ne distingue pas « ldc_ON » de « ldc_on ».
 * On croise ici les tables qui produisent des commandes info. */
$infos = array();
foreach (array_keys($etats) as $lid) { $infos[] = $lid; }
foreach (array_keys($reglages) as $lid) { $infos[] = $lid; }
foreach (array_keys(gds3710::get_event_detail_list()) as $lid) { $infos[] = $lid; }
foreach ($catalogue as $row) { $infos[] = $row['short_name']; }

$vus = array();
$collisions = array();
foreach ($infos as $lid) {
    $n = gds3710_normaliser($lid);
    if (isset($vus[$n])) {
        $collisions[] = $lid . ' et ' . $vus[$n];
    }
    $vus[$n] = $lid;
}
Verif::egal(array(), $collisions,
    'aucune collision entre identifiants de commandes info, casse et accents ignores');

/* Chaque reglage engendre aussi une commande action « <lid>_set ». Elle ne doit heurter
 * aucun identifiant info existant. */
$heurts = array();
foreach (array_keys($reglages) as $lid) {
    $n = gds3710_normaliser($lid . '_set');
    if (isset($vus[$n])) {
        $heurts[] = $lid . '_set';
    }
}
Verif::egal(array(), $heurts, 'les curseurs de reglage n entrent en collision avec aucune commande info');
