<?php

/* gds3710::urlPublique() — traduction d'un chemin disque en URL servie par Jeedom.
 *
 * Le calcul historique, recopie en quatre endroits, etait
 * substr($chemin, strpos($chemin, '/plugins')). La configuration autorisant un
 * repertoire de captures absolu hors de l'arborescence de Jeedom, strpos rendait false,
 * substr($chemin, false) equivalait a substr($chemin, 0), et la commande recevait le
 * chemin disque entier en guise d'URL — widget casse, sans explication.
 *
 * Dans le bac a sable, la racine calculee par la fonction (quatre niveaux au-dessus du
 * fichier de classe) est le bac lui-meme. */

$bac = realpath($GLOBALS['gds3710_bac']);
$dansLaRacine = $bac . '/plugins/gds3710/data';
@mkdir($dansLaRacine, 0777, true);
$capture = $dansLaRacine . '/Portier_2026-09-09_00-11-00-123456.jpg';
file_put_contents($capture, "\xFF\xD8 fausse capture");

Verif::bloc('urlPublique() : capture sous la racine de Jeedom');

$url = gds3710::urlPublique($capture);
Verif::egal('/plugins/gds3710/data/Portier_2026-09-09_00-11-00-123456.jpg', $url,
    'le chemin disque devient une URL relative a la racine');
Verif::vrai(substr($url, 0, 1) === '/', 'l URL commence par une barre');
Verif::absent($bac, $url, 'aucune trace du chemin disque de l hote dans l URL');

Verif::bloc('urlPublique() : capture hors de la racine');

/* C'est le cas que le calcul historique servait en silence : un repertoire de captures
 * absolu, hors de l'arborescence web. Aucune URL ne peut le servir. */
$dehors = sys_get_temp_dir() . '/gds3710-hors-racine-' . getmypid();
@mkdir($dehors, 0777, true);
$captureDehors = $dehors . '/capture.jpg';
file_put_contents($captureDehors, 'x');

log::vider();
Verif::egal('', gds3710::urlPublique($captureDehors),
    'un fichier hors racine ne rend aucune URL');
Verif::egal(1, count(log::messages('warning')),
    'et l administrateur est prevenu, au lieu d une tuile vide inexpliquee');
Verif::present('hors de la racine', implode(' ', log::messages('warning')),
    'le message dit ce qui ne va pas');

Verif::bloc('urlPublique() : entrees invalides');

Verif::egal('', gds3710::urlPublique($bac . '/plugins/gds3710/data/inexistant.jpg'),
    'un fichier absent ne rend aucune URL');
Verif::egal('', gds3710::urlPublique(''), 'un chemin vide ne rend aucune URL');

/* Traversee de repertoire : le chemin est normalise par realpath avant comparaison,
 * un ../ ne peut donc pas faire sortir une URL de la racine. */
$traversee = $bac . '/plugins/gds3710/data/../../../../etc/hostname';
$resolu = realpath($traversee);
if ($resolu !== false) {
    Verif::egal('', gds3710::urlPublique($traversee),
        'une traversee vers l exterieur ne rend aucune URL');
} else {
    /* La cible n'existe pas sur cette machine : realpath rend false, donc chaine vide. */
    Verif::egal('', gds3710::urlPublique($traversee),
        'une traversee vers une cible absente ne rend aucune URL');
}

@unlink($capture);
@unlink($captureDehors);
@rmdir($dehors);
