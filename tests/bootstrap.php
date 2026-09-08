<?php

/* Prepare le chargement de gds3710.class.php hors d'une installation Jeedom.
 *
 * La classe ouvre sur require_once __DIR__ . '/../../../../core/php/core.inc.php'. Ce
 * chemin est fige et relatif au fichier : impossible de le detourner par un include_path
 * ou un autoloader. On fabrique donc l'arborescence qu'il attend dans un repertoire
 * temporaire :
 *
 *   <bac>/core/php/core.inc.php          <- les doublures
 *   <bac>/plugins/gds3710/core/class/    <- une copie du fichier de classe
 *
 * Une copie, et non un lien symbolique : les liens demandent des privileges sous Windows,
 * ou le mainteneur travaille. Le fichier est recopie a chaque execution, il ne peut donc
 * pas devenir obsolete. */

define('GDS3710_RACINE', dirname(__DIR__));

function gds3710_preparer_bac_a_sable() {
    $bac = sys_get_temp_dir() . '/gds3710-tests-' . getmypid();
    $classes = $bac . '/plugins/gds3710/core/class';
    $coeur = $bac . '/core/php';

    foreach (array($classes, $coeur) as $dossier) {
        if (!is_dir($dossier) && !mkdir($dossier, 0777, true) && !is_dir($dossier)) {
            fwrite(STDERR, "Impossible de creer le bac a sable : " . $dossier . "\n");
            exit(1);
        }
    }

    copy(__DIR__ . '/doublures/core.inc.php', $coeur . '/core.inc.php');
    copy(GDS3710_RACINE . '/core/class/gds3710.class.php', $classes . '/gds3710.class.php');

    return $bac;
}

function gds3710_nettoyer_bac_a_sable($_bac) {
    if (!is_dir($_bac)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($_bac, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($_bac);
}

$GLOBALS['gds3710_bac'] = gds3710_preparer_bac_a_sable();
require_once $GLOBALS['gds3710_bac'] . '/plugins/gds3710/core/class/gds3710.class.php';

/* ------------------------------------------------------------------ *
 *  Assertions                                                         *
 * ------------------------------------------------------------------ */

class Verif {
    public static $total = 0;
    public static $echecs = array();
    private static $bloc = '';

    public static function bloc($_titre) {
        self::$bloc = $_titre;
        echo "\n-- " . $_titre . "\n";
    }

    private static function noter($_ok, $_intitule, $_detail) {
        self::$total++;
        if ($_ok) {
            echo "   ok   " . $_intitule . "\n";
            return;
        }
        echo "  ECHEC " . $_intitule . "\n";
        foreach (explode("\n", $_detail) as $ligne) {
            echo "        " . $ligne . "\n";
        }
        self::$echecs[] = self::$bloc . ' / ' . $_intitule;
    }

    public static function egal($_attendu, $_obtenu, $_intitule) {
        self::noter($_attendu === $_obtenu, $_intitule,
            'attendu : ' . var_export($_attendu, true) . "\nobtenu  : " . var_export($_obtenu, true));
    }

    public static function vrai($_condition, $_intitule) {
        self::noter($_condition === true, $_intitule, 'condition fausse');
    }

    public static function nul($_valeur, $_intitule) {
        self::noter($_valeur === null, $_intitule, 'obtenu : ' . var_export($_valeur, true));
    }

    /* Le coeur des tests de masquage : la chaine sensible ne doit apparaitre nulle part
     * dans le texte journalise. */
    public static function absent($_secret, $_texte, $_intitule) {
        self::noter(strpos($_texte, $_secret) === false, $_intitule,
            'le secret « ' . $_secret . ' » subsiste dans : ' . $_texte);
    }

    public static function present($_attendu, $_texte, $_intitule) {
        self::noter(strpos($_texte, $_attendu) !== false, $_intitule,
            '« ' . $_attendu . ' » absent de : ' . $_texte);
    }
}
