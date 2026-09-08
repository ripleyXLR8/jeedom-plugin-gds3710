<?php

/* Garde-fous sur le source lui-meme.
 *
 * Trois defauts de ce plugin ne se voient dans aucune valeur de retour : ils tiennent a
 * la forme d'un appel. Une fois corriges, rien n'empeche de les reintroduire — sauf ces
 * controles. Ils lisent les fichiers du depot, pas ceux du bac a sable. */

/* gds3710_fichiers_php() et gds3710_code_seul() vivent dans bootstrap.php : plusieurs
 * fichiers de test s'en servent, et l'ordre alphabetique d'execution deciderait sinon de
 * leur disponibilite. */
$fichiers = gds3710_fichiers_php();

Verif::bloc('source : getCmd() filtre toujours par type');

/* La colonne logicalId est en collation utf8mb3_unicode_ci : MySQL ne distingue pas la
 * commande action « reboot » de la commande info « Reboot ». Sans filtre de type,
 * getCmd() rendait l'une ou l'autre selon l'ordre des lignes — c'est le bug « Une
 * commande portant ce nom (Reboot) existe deja », qui rendait l'equipement
 * insauvegardable. Le premier argument doit donc toujours nommer un type. */
$sansType = array();
foreach ($fichiers as $nom => $contenu) {
    foreach (explode("\n", $contenu) as $i => $ligne) {
        if (preg_match('/getCmd\(\s*(null|NULL)\s*,/', $ligne)) {
            $sansType[] = $nom . ':' . ($i + 1) . ' -> ' . trim($ligne);
        }
    }
}
Verif::egal(array(), $sansType,
    'aucun getCmd(null, ...) : le type doit etre nomme, sinon action et info se confondent');

Verif::bloc('source : jamais de cache::flush()');

/* cache::flush() vide le cache de valeurs de TOUTE l'installation, pas seulement celui du
 * plugin : 293 commandes s'etaient retrouvees sans valeur, toutes integrations confondues.
 * L'invalidation doit rester ciblee (cache::set sur une clef precise). */
$flush = array();
foreach ($fichiers as $nom => $contenu) {
    foreach (explode("\n", $contenu) as $i => $ligne) {
        if (strpos($ligne, 'cache::flush') !== false) {
            $flush[] = $nom . ':' . ($i + 1);
        }
    }
}
Verif::egal(array(), $flush, 'aucun appel a cache::flush(), qui viderait toute l installation');

Verif::bloc('source : les ecritures de configuration partent en POST');

/* Le portier decode l'encodage pourcent PUIS redecoupe sa propre chaine de requete sur
 * les « & » : une valeur qui en contient — le gabarit d'URL en compte sept — est tronquee
 * au premier, en silence, avec un ResCode 0 qui annonce le succes. Toute ecriture doit
 * donc passer par httpPostConfig(). */
$enGet = array();
foreach ($fichiers as $nom => $contenu) {
    foreach (explode("\n", $contenu) as $i => $ligne) {
        if (preg_match('#goform/config\?cmd=set#', $ligne)) {
            $enGet[] = $nom . ':' . ($i + 1) . ' -> ' . trim($ligne);
        }
    }
}
Verif::egal(array(), $enGet,
    'aucune ecriture de configuration en GET : la valeur serait tronquee au premier &');

Verif::bloc('source : le mot de passe ne voyage pas dans une URL');

/* Une URL a identifiants (https://admin:motdepasse@hote/...) finit dans le log du serveur
 * web des qu'une fonction PHP echoue dessus — hors de portee de redact(), qui ne couvre
 * que le log du plugin. Les identifiants passent par un en-tete. */
$urlIdentifiants = array();
foreach ($fichiers as $nom => $contenu) {
    foreach (explode("\n", $contenu) as $i => $ligne) {
        if (preg_match('#[\'"]https?://[^\'"]*\'\s*\.\s*\$password#', $ligne)
            || preg_match('#https?://admin:#', $ligne)) {
            $urlIdentifiants[] = $nom . ':' . ($i + 1) . ' -> ' . trim($ligne);
        }
    }
}
Verif::egal(array(), $urlIdentifiants,
    'aucune URL ne porte d identifiants : ils passent par un en-tete Authorization');

Verif::bloc('source : les captures sont verifiees avant publication');

/* Le portier repond 200 avec son XML d'erreur quand l'authentification echoue : le corps
 * etait enregistre tel quel en .jpg puis publie comme « dernier snapshot ». */
$classe = $fichiers['core/class/gds3710.class.php'];
Verif::present("\\xFF\\xD8", $classe,
    'take_snapshot() controle l en-tete JPEG avant de publier la capture');
