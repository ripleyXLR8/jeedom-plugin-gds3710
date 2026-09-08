<?php

/* Reprises de mise a jour (plugin_info/install.php).
 *
 * gds3710_update() rejouait toutes ses reprises a chaque mise a jour du plugin — dont un
 * parcours de l'integralite des commandes de chaque equipement. Idempotent, mais la liste
 * ne peut que s'allonger, et rien ne distinguait une reparation permanente d'une reprise
 * ponctuelle. Un numero de schema, retenu dans la configuration, tranche la question. */

Verif::bloc('table des reprises : forme');

$migrations = gds3710_migrations();
Verif::vrai(count($migrations) > 0, 'la table n est pas vide');

$manquantes = array();
foreach ($migrations as $numero => $fonction) {
    if (!function_exists($fonction)) {
        $manquantes[] = $numero . ' => ' . $fonction;
    }
}
Verif::egal(array(), $manquantes, 'chaque reprise designe une fonction qui existe');

$numeros = array_keys($migrations);
Verif::egal($numeros, array_unique($numeros), 'les numeros sont uniques');
$tries = $numeros;
sort($tries);
Verif::egal($tries, $numeros, 'la table est declaree dans l ordre croissant');
Verif::egal(range(1, count($numeros)), $numeros,
    'les numeros se suivent depuis 1, sans trou : un trou ferait douter d une reprise perdue');

$doublons = array_diff_assoc($migrations, array_unique($migrations));
Verif::egal(array(), $doublons, 'aucune fonction n est declaree deux fois');

Verif::bloc('une installation neuve ne rejoue rien');

config::$valeurs = array();
gds3710_install();
Verif::egal(max($numeros), gds3710_niveau_de_schema(),
    'l installation se declare au dernier niveau : rien a reprendre sur des commandes neuves');
Verif::egal(1, config::byKey('password_protection', 'gds3710'),
    'et la protection par mot de passe est active par defaut');

log::vider();
gds3710_update();
Verif::egal(0, count(log::messages('info')),
    'la mise a jour qui suit n applique aucune reprise');

Verif::bloc('une installation ancienne rattrape son retard');

/* Niveau 0 : l'installation n'a jamais vu de numero de schema, elle est anterieure a ce
 * mecanisme. Toutes les reprises doivent s'appliquer, une seule fois. */
config::$valeurs = array();
log::vider();
gds3710_update();
Verif::egal(max($numeros), gds3710_niveau_de_schema(), 'le niveau atteint le maximum');
Verif::egal(count($migrations), count(log::messages('info')),
    'chaque reprise est appliquee et journalisee');

log::vider();
gds3710_update();
Verif::egal(0, count(log::messages('info')),
    'une seconde mise a jour ne rejoue rien');

Verif::bloc('une reprise interrompue redemarre ou elle s est arretee');

/* Le niveau est enregistre apres CHAQUE etape, et non a la fin : une mise a jour
 * interrompue ne rejoue pas ce qui etait deja passe, et ne saute pas ce qui restait. */
config::$valeurs = array();
config::save('schema', 1, 'gds3710');
log::vider();
gds3710_update();
Verif::egal(count($migrations) - 1, count(log::messages('info')),
    'seules les reprises posterieures au niveau atteint sont jouees');
Verif::egal(max($numeros), gds3710_niveau_de_schema(), 'et le niveau est mis a jour');

Verif::bloc('reprise 1 : la protection reste au choix des installations existantes');

/* Activer la protection d'office couperait la remontee d'evenements de toutes les
 * installations qui ne l'ont pas configuree — au moment meme de la mise a jour, sans
 * que personne ne fasse le lien. */
config::$valeurs = array();
gds3710_migration_1_protection_optionnelle();
Verif::egal(0, config::byKey('password_protection', 'gds3710'),
    'une installation sans reglage reste sans protection, plutot que de se couper');

config::$valeurs = array();
config::save('password_protection', 1, 'gds3710');
gds3710_migration_1_protection_optionnelle();
Verif::egal(1, config::byKey('password_protection', 'gds3710'),
    'un reglage deja pose n est pas ecrase');

Verif::bloc('la reparation de l URL du flux n est pas une reprise');

/* Elle doit tourner a chaque mise a jour : la valeur se perd des qu'un vidage de cache
 * remet les commandes a blanc, ce qui n'a rien a voir avec la version du plugin. */
Verif::vrai(function_exists('gds3710_reparer_url_du_flux'),
    'la reparation est une fonction distincte des reprises');
Verif::vrai(!in_array('gds3710_reparer_url_du_flux', $migrations, true),
    'et elle ne figure pas dans la table des reprises');
Verif::egal(0, gds3710_reparer_url_du_flux(),
    'sans equipement, elle ne republie rien et ne se plaint pas');
