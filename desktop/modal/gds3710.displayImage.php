<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
/* La source etait ecrite telle quelle dans l attribut src. Ce modal est charge par
 * jQuery .load(), qui execute le balisage recu : un parametre fabrique suffisait donc a
 * faire executer du script dans la session de qui ouvrait le lien. Deux gardes : la
 * source doit designer le point de telechargement du plugin — le seul que produise la
 * vignette de l historique — et elle est echappee avant d entrer dans l attribut. */
$src = (string) init('src');
if (strpos($src, 'plugins/gds3710/core/php/downloadFile.php?') !== 0) {
	log::add('gds3710', 'error', 'Source d image refusee dans displayImage : ' . substr($src, 0, 100));
	throw new Exception('{{401 - Source d\'image non autorisée}}');
}
echo '<center><img class="img-responsive" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" /></center>';
?>