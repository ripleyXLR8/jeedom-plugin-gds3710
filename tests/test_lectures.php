<?php

/* Mutualisation des lectures de configuration.
 *
 * refreshSettings() et refreshStates() lisaient chacun les sections de leur propre table.
 * Or « audio », « event » et « sch_open_door » figurent dans les deux : ces trois sections
 * partaient donc deux fois a chaque passage du cron, sur un appareil qui ne tolere qu une
 * session administrateur. Le cron fait desormais une passe unique et transmet le resultat.
 *
 * Deux proprietes rendent cette fusion sure, et sont verifiees ici :
 *  - les P-values des deux tables sont disjointes, donc array_merge ne peut pas ecraser
 *    une valeur au profit d une autre ;
 *  - un tableau fourni est bel et bien utilise, sans retour au reseau. */

Verif::bloc('sectionsDe() : dedoublonnage');

Verif::egal(array('a'), gds3710::sectionsDe(array(
    'x' => array('section' => 'a'),
    'y' => array('section' => 'a'),
)), 'deux entrees d une meme section ne donnent qu une lecture');

Verif::egal(array('a', 'b'), gds3710::sectionsDe(array(
    'x' => array('section' => 'a'),
    'y' => array('section' => 'b'),
    'z' => array('section' => 'a'),
)), 'l ordre de premiere apparition est conserve');

Verif::egal(array(), gds3710::sectionsDe(array()), 'une table vide ne demande aucune lecture');
Verif::egal(array(), gds3710::sectionsDe(array('x' => array('p' => 'P1'))),
    'une entree sans section est ignoree, au lieu de demander la lecture de « » ');

Verif::bloc('sections reellement lues par le cron');

$sectionsReglages = gds3710::sectionsDe(gds3710::get_setting_list());
$sectionsEtats = gds3710::sectionsDe(gds3710::get_state_list());
$union = array_unique(array_merge($sectionsReglages, $sectionsEtats));

$avant = count($sectionsReglages) + count($sectionsEtats);
$apres = count($union);
Verif::vrai($apres < $avant,
    'la passe unique lit moins de sections que les deux passes separees ('
    . $apres . ' contre ' . $avant . ')');

$partagees = array_values(array_intersect($sectionsReglages, $sectionsEtats));
sort($partagees);
Verif::egal(array('audio', 'event', 'sch_open_door'), $partagees,
    'les trois sections partagees sont bien celles attendues');

Verif::bloc('les P-values des deux tables sont disjointes');

/* C'est la propriete qui autorise la fusion. Si un jour un reglage et un etat visaient la
 * meme P-value, array_merge en perdrait un — silencieusement. Ce controle l'interdit. */
$pReglages = array();
foreach (gds3710::get_setting_list() as $lid => $def) {
    $pReglages[$def['p']] = $lid;
}
$pEtats = array();
foreach (gds3710::get_state_list() as $lid => $def) {
    $pEtats[$def['p']] = $lid;
}
$collisions = array();
foreach (array_intersect_key($pReglages, $pEtats) as $p => $lidReglage) {
    $collisions[] = $p . ' (reglage ' . $lidReglage . ' et etat ' . $pEtats[$p] . ')';
}
Verif::egal(array(), $collisions,
    'aucune P-value n est declaree a la fois comme reglage et comme etat');

/* Au sein d une meme table, deux entrees sur la meme P-value seraient tout aussi
 * douteuses : deux commandes afficheraient la meme valeur sous deux noms. */
Verif::egal(count(gds3710::get_setting_list()), count($pReglages),
    'chaque reglage vise une P-value distincte');
Verif::egal(count(gds3710::get_state_list()), count($pEtats),
    'chaque etat vise une P-value distincte');

Verif::bloc('un tableau fourni evite le retour au reseau');

/* L'equipement n'a ni adresse ni mot de passe : toute lecture reelle echouerait, et les
 * deux methodes rendraient false. Qu'elles rendent true prouve qu'elles se sont servies
 * du tableau transmis. */
$eq = new gds3710();

$etats = gds3710::get_state_list();
$unEtat = key($etats);
Verif::vrai($eq->refreshStates(array($etats[$unEtat]['p'] => '1')) === true,
    'refreshStates() se contente du tableau fourni');

$reglages = gds3710::get_setting_list();
$unReglage = key($reglages);
Verif::vrai($eq->refreshSettings(array($reglages[$unReglage]['p'] => '5')) === true,
    'refreshSettings() se contente du tableau fourni');

/* Un tableau vide reste un echec : c'est ainsi que le cron distingue un portier muet
 * d'une lecture reussie. */
Verif::vrai($eq->refreshStates(array()) === false, 'un tableau vide rend false');
Verif::vrai($eq->refreshSettings(array()) === false, 'un tableau vide rend false aussi pour les reglages');

/* Sans argument, la methode retourne au reseau : sans adresse configuree, elle echoue.
 * C'est ce que doivent faire les appels qui suivent une ecriture — relire l appareil pour
 * confirmer que la valeur a ete prise. */
Verif::vrai($eq->refreshStates() === false,
    'sans argument, la lecture part vers l appareil et echoue faute de configuration');
