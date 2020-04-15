<?php
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
echo '<center><img class="img-responsive" src="' . init('src') . '" /></center>';
?>