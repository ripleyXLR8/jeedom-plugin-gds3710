<?php

/* Lanceur des tests du plugin.
 *
 *   php tests/run.php            tout
 *   php tests/run.php catalogue  seulement les fichiers dont le nom contient « catalogue »
 *
 * Aucune dependance : ni composer, ni PHPUnit. Le plugin n'en a aucune par ailleurs, et
 * en ajouter une pour les tests obligerait a la resoudre sur chaque box Jeedom — alors
 * que ces tests ne tournent que chez le mainteneur et en integration continue. */

require_once __DIR__ . '/bootstrap.php';

$filtre = isset($argv[1]) ? $argv[1] : '';

$fichiers = glob(__DIR__ . '/test_*.php');
sort($fichiers);
if ($filtre !== '') {
    $fichiers = array_values(array_filter($fichiers, function ($f) use ($filtre) {
        return strpos(basename($f), $filtre) !== false;
    }));
}

if (count($fichiers) === 0) {
    echo "Aucun fichier de test ne correspond a « " . $filtre . " ».\n";
    exit(1);
}

echo "== tests du plugin gds3710 (PHP " . PHP_VERSION . ") ==\n";

foreach ($fichiers as $fichier) {
    echo "\n=== " . basename($fichier) . " ===\n";
    /* Chaque fichier est execute dans la portee globale : les tests partagent le
     * bootstrap et les doublures, deja chargees. */
    require $fichier;
}

gds3710_nettoyer_bac_a_sable($GLOBALS['gds3710_bac']);

echo "\n" . str_repeat('-', 60) . "\n";
if (count(Verif::$echecs) === 0) {
    echo Verif::$total . " verification(s), tout est vert.\n";
    exit(0);
}
echo Verif::$total . " verification(s), " . count(Verif::$echecs) . " ECHEC(S) :\n";
foreach (Verif::$echecs as $e) {
    echo "  - " . $e . "\n";
}
exit(1);
