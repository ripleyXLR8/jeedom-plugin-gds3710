<?php
/**
 * Publie sur le market Jeedom le plugin TEL QU'IL EST INSTALLE sur cette Jeedom.
 *
 * Le market ne va pas chercher le code sur GitHub : repo_market::save() archive le
 * contenu de plugins/<id>/ et l'envoie. Ce qui est publie est donc exactement ce qui
 * tourne ici — d'ou le controle prealable, et le mode rapport par defaut.
 *
 * Usage, depuis l'hote :
 *   docker exec jeedom php /var/www/html/plugins/gds3710/tools/publier_market.php
 *   docker exec jeedom php /var/www/html/plugins/gds3710/tools/publier_market.php --publier
 *
 * Sans option : ne publie rien, se contente de rapporter ce qui serait envoye.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

$PLUGIN = 'gds3710';
$publier = in_array('--publier', $argv, true);

function ligne($cle, $valeur) {
    printf("  %-26s %s\n", $cle, $valeur);
}

echo "== connexion au market ==\n";
ligne('adresse', config::byKey('market::address'));
ligne('compte', config::byKey('market::username'));
try {
    $t = repo_market::test();
    $ok = is_array($t) ? in_array('ok', $t, true) : (bool) $t;
    ligne('test', $ok ? 'ok' : 'ECHEC');
    if (!$ok) { exit(1); }
} catch (Exception $e) {
    ligne('test', 'ECHEC : ' . $e->getMessage());
    exit(1);
}

echo "\n== ce qui serait envoye ==\n";
$dir = realpath(dirname(__FILE__) . '/..');
/* Les memes exclusions que repo_market::save(), plus data/ qu'il retire ensuite. */
$exclus = array('tmp', '.git', '.DStore', 'data');
$n = 0; $octets = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $premier = explode('/', str_replace($dir . '/', '', $f->getPathname()))[0];
    if (in_array($premier, $exclus, true)) { continue; }
    $n++; $octets += $f->getSize();
}
ligne('repertoire', $dir);
ligne('fichiers', $n . ' (' . round($octets / 1024) . ' Ko)');
ligne('exclus', implode(', ', $exclus));

$u = update::byLogicalId($PLUGIN);
if (is_object($u)) {
    echo "\n== versions ==\n";
    ligne('installee ici', $u->getLocalVersion());
    ligne('publiee sur le market', $u->getRemoteVersion());
    ligne('canal', $u->getConfiguration('version'));
}

if (!$publier) {
    echo "\nMode rapport : rien n'a ete publie. Ajoutez --publier pour envoyer.\n";
    exit(0);
}

echo "\n== publication ==\n";
$r = repo_market::byLogicalIdAndType($PLUGIN, 'plugin');
if (!is_object($r)) {
    echo "  fiche market introuvable pour " . $PLUGIN . "\n";
    exit(1);
}
try {
    $r->save();
    echo "  envoye.\n";
} catch (Exception $e) {
    echo "  ECHEC : " . $e->getMessage() . "\n";
    exit(1);
}

$u = update::byLogicalId($PLUGIN);
if (is_object($u)) {
    $u->checkUpdate();
    $u = update::byLogicalId($PLUGIN);
    echo "\n== apres publication ==\n";
    ligne('installee ici', $u->getLocalVersion());
    ligne('publiee sur le market', $u->getRemoteVersion());
    ligne('statut', $u->getStatus());
}
