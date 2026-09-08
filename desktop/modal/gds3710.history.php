<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* Ce controle etait commente : la bibliotheque de captures s'ouvrait a tout compte
 * connecte, y compris un utilisateur restreint sans le moindre droit sur le portier.
 * Exiger « admin » serait trop dur — un utilisateur normal doit pouvoir consulter ses
 * captures. Le droit de lecture sur l'equipement est verifie plus bas, une fois
 * celui-ci resolu. */
if (!isConnect()) {
	throw new Exception('{{401 - Accès non autorisé}}');
}

if (init('eqlogic') == '' && init('id') == '') {
	throw new Exception(__('L\'id et l\'eqlogic ne peuvent etre vide en même temps', __FILE__));
}

if(init('eqlogic') == ''){
	$cmd = cmd::byId(init('id'));
	/* Un id de commande inconnu donnait false, et l'appel de methode suivant un fatal. */
	if (!is_object($cmd)) {
		throw new Exception(__('La commande est introuvable : ', __FILE__) . init('id'));
	}
	$gds3710 = gds3710::byId($cmd->getEqLogic_id());
} else {
	$gds3710 = gds3710::byId(init('eqlogic'));
}

if (!is_object($gds3710)) {
	throw new Exception(__('L\'équipement est introuvable : ', __FILE__) . init('id'));
}

if ($gds3710->getEqType_name() != 'gds3710') {
	throw new Exception(__('Cet équipement n\'est pas de type gds3710 : ', __FILE__) . $gds3710->getEqType_name());
}

/* Meme regle que pour le flux video : les captures d'une porte ne se consultent qu'avec
 * un droit de lecture sur l'equipement qui les a prises. */
if (!$gds3710->hasRight('r')) {
	throw new Exception(__('401 - Accès non autorisé à cet équipement', __FILE__));
}

$dir = calculPath(config::byKey('recdir', 'gds3710')) . '/' . $gds3710->getId();

$files = array();

foreach (ls($dir, '*') as $file) {

	if ($file == 'movie_temp/' || strpos($file, '.mkv')) {
		continue;
	}
	$date = explode('_', str_replace('.mp4', '', str_replace('.jpg', '', $file)));
	log::add('gds3710', 'debug', print_r($date,true));
	$time = $date[count($date) - 1];
	$date = $date[count($date) - 2];
	if ($date == '') {
		continue;
	}
	if (!isset($files[$date])) {
		$files[$date] = array();
	}
	$files[$date][$time] = $file;
}
krsort($files);

log::add('gds3710', 'debug', print_r($files, true));
?>
<div id='div_gds3710RecordAlert' style="display: none;"></div>
<?php
echo '<a class="btn btn-danger bt_removeSnapshotGDS3710File pull-right" data-all="1" data-filename="' . $gds3710->getId() . '/*"><i class="fas fa-trash"></i> {{Tout supprimer}}</a>';
echo '<a class="btn btn-success  pull-right" target="_blank" href="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/*') . '" ><i class="fas fa-download"></i> {{Tout télécharger}}</a>';
?>
<?php
$i = 0;
foreach ($files as $date => &$file) {
	/* Le motif d'une journée s'ancre sur la date, pas sur le nom de l'équipement. Il était
	   construit avec les espaces du nom remplacées par des tirets, alors que la capture est
	   écrite avec le nom brut : pour un équipement « Portier Entrée », le motif ne
	   correspondait à aucun fichier et les deux boutons de la journée ne faisaient rien,
	   sans le moindre message. La date suffit à identifier les captures du jour, et ne
	   dépend plus de la façon dont l'équipement est nommé ni renommé. */
	$motifJour = '*_' . $date . '_*';
	echo '<div class="div_dayContainer">';
	echo '<legend>';
	echo '<a class="btn btn-xs btn-danger bt_removeSnapshotGDS3710File" data-day="1" data-filename="' . $gds3710->getId() . '/' . $motifJour . '"><i class="fas fa-trash"></i> {{Supprimer}}</a> ';
	echo '<a class="btn btn-xs btn-success" target="_blank"  href="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/' . $motifJour) . '" ><i class="fas fa-download"></i> {{Télécharger}}</a> ';
	echo '<span class="cameraHistoryDate">'.$date.'</span>';
	echo ' <a class="btn btn-xs btn-default toggleList"><i class="fa fa-chevron-down"></i></a> ';
	echo '</legend>';
	echo '<div class="gds3710ThumbnailContainer">';
	krsort($file);
	foreach ($file as $time => $filename) {
		$fontType = 'fas-camera';
		if (strpos($filename, '.mp4')) {
			$fontType = 'fas-video-camera';
			$i++;
		}
		echo '<div class="cameraDisplayCard" style="padding:5px;height:170px;">';
		echo '<center><i class="fa ' . $fontType . ' pull-right"></i>  ' . str_replace('-', ':', $time) . '</center>';
		if (strpos($filename, '.mp4')) {
			echo '<video class="displayVideo" width="150" height="100" controls loop data-src="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/' . $filename) . '" style="cursor:pointer"><source src="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/' . $filename) . '">Your browser does not support the video tag.</video>';
		} else {
			/* Chargement direct avec lazy natif du navigateur. Le plugin jQuery lazyload
			 * dont dependait cette vignette nest plus charge par le coeur : les images
			 * restaient bloquees sur leur image de remplacement. Au passage, lattribut
			 * style netait pas referme, ce qui avalait la fermeture de la balise. */
			echo '<center><img class="img-responsive cursor displayImage" loading="lazy" src="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/' . $filename) . '" width="150" style="max-height:80px; padding-bottom:5px;" /></center>';
		}
		echo '<center style="margin-top:5px;"><a target="_blank" href="plugins/gds3710/core/php/downloadFile.php?pathfile=' . urlencode($dir . '/' . $filename) . '" class="btn btn-success btn-xs" style="color : white"><i class="fa fa-download"></i></a>';
		echo ' <a class="btn btn-danger bt_removeSnapshotGDS3710File btn-xs" style="color : white" data-filename="' . $gds3710->getId() . '/' . $filename . '"><i class="fas fa-trash"></i></a></center>';
		echo '</div>';
	}
	echo '</div>';
	echo '</div>';
}
?>

<script>
	$('.gds3710ThumbnailContainer').packery({gutter : 5});
	$('.displayImage').on('click', function() {
        $('#md_modal2').dialog({title: "Image"});
        $('#md_modal2').load('index.php?v=d&plugin=gds3710&modal=gds3710.displayImage&src='+ $(this).attr('src')).dialog('open');
    });

	$(".gds3710ThumbnailContainer").slideToggle(1);

    $(".gds3710ThumbnailContainer").eq(0).slideToggle(1);

    $('.toggleList').on('click', function() {
        $(this).closest('.div_dayContainer').find(".gds3710ThumbnailContainer").slideToggle("slow");
    });

  	$('.bt_removeSnapshotGDS3710File').on('click', function() {

		var filename = $(this).attr('data-filename');
		var card = $(this).closest('.cameraDisplayCard');
		if($(this).attr('data-day') == 1){
			card = $(this).closest('.div_dayContainer');
		}
		if($(this).attr('data-all') == 1){
			card = $('.div_dayContainer');
		}
		$.ajax({
			type: "POST",
			url: "plugins/gds3710/core/ajax/gds3710.ajax.php",
			data: {
				action: "removeRecord",
				file: filename,
			},
			dataType: 'json',
			error: function(request, status, error) {
				handleAjaxError(request, status, error,$('#div_gds3710RecordAlert'));
			},
			success: function(data) {
				if (data.state != 'ok') {
					$('#div_gds3710RecordAlert').showAlert({message: data.result, level: 'danger'});
					return;
				}
				card.remove();
				$(".gds3710ThumbnailContainer").slideToggle(1);
				$('.gds3710ThumbnailContainer').packery({gutter : 5});
				$(".gds3710ThumbnailContainer").slideToggle(1);
			}
		});
	});


</script>