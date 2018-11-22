<?php
  if (!isConnect('admin')) {
  	throw new Exception('{{401 - Accès non autorisé}}');
  }
  $plugin = plugin::byId('gds3710');
  sendVarToJS('eqType', $plugin->getId());
  $eqLogics = eqLogic::byType($plugin->getId());
?>

<?php include_file('desktop', 'SIPml-api', 'js', 'gds3710');?>

<div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un GDS3710}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
                <?php
                  foreach ($eqLogics as $eqLogic) {
                  	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
                  	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity .'"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
                  }
		            ?>
           </ul>
       </div>
   </div>

   <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
    <legend>{{Mes GDS 3710}}</legend>
  <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
  <div class="eqLogicThumbnailContainer">
      <div class="cursor eqLogicAction" data-action="add" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
        <i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
        <br>
        <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02">{{Ajouter}}</span>
    </div>
      <div class="cursor eqLogicAction" data-action="gotoPluginConf" style="text-align: center; background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
      <i class="fa fa-wrench" style="font-size : 6em;color:#767676;"></i>
    <br>
    <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676">{{Configuration}}</span>
  </div>
  </div>
  <legend><i class="fa fa-table"></i> {{Liste des équipements}}</legend>
<div class="eqLogicThumbnailContainer">

<?php
  foreach ($eqLogics as $eqLogic) {
  	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
  	echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="text-align: center; background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
  	echo '<img src="' . $plugin->getPathImgIcon() . '" height="105" width="95" />';
  	echo "<br>";
  	echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;">' . $eqLogic->getHumanName(true, true) . '</span>';
  	echo '</div>';
  }
?>
</div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
	<a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
  <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
  <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
  <ul class="nav nav-tabs" role="tablist">
    <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
    <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Configuration}}</a></li>
    <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>

    <?php
      function array_unique_multidimensional($input)
      {
          $serialized = array_map('serialize', $input);
          $unique = array_unique($serialized);
          return array_intersect_key($input, $unique);
      }

      $gds3710EventList = gds3710::get_GDS3710_event_list();
      $stack_section = array(); 
      foreach ($gds3710EventList as $row) {
        array_push($stack_section, array('section'=> $row['section'],'section_icon' => $row['section_icon']));
      }
      $stack_section = array_unique_multidimensional($stack_section);
      $output = '';
      //print_r($stack_section);
      foreach ($stack_section as $row_main) {
        ///echo('test');
        $output = $output.'<li role="presentation"><a href="#'.str_replace(' ', '',strtolower($row_main['section'])).'actiontab" aria-controls="profile" role="tab" data-toggle="tab"><i class="'.$row_main['section_icon'].'"></i> {{'.ucfirst($row_main['section']).'}}</a></li>';
      }
      echo($output);
    ?>

  </ul>
  <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
    <div role="tabpanel" class="tab-pane active" id="eqlogictab">
      <br/>
    <form class="form-horizontal">
        <fieldset>
            <div class="form-group">
                <label class="col-sm-3 control-label">{{Nom du GDS3710}}</label>
                <div class="col-sm-3">
                    <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                    <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement template}}"/>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-3 control-label" >{{Objet parent}}</label>
                <div class="col-sm-3">
                    <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                        <option value="">{{Aucun}}</option>
                        <?php
                          foreach (object::all() as $object) {
                          	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                          }
                        ?>
                   </select>
               </div>
           </div>
	   <div class="form-group">
                <label class="col-sm-3 control-label">{{Catégorie}}</label>
                <div class="col-sm-9">
                 <?php
                    foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                    echo '</label>';
                    }
                  ?>
               </div>
           </div>
	<div class="form-group">
		<label class="col-sm-3 control-label"></label>
		<div class="col-sm-9">
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
			<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
		</div>
	</div>

   <div class="form-group">
    <label class="col-sm-3 control-label">{{Adresse MAC}}</label>
    <div class="col-sm-3">
        <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="macaddress" placeholder="Adresse MAC"/>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">{{Adresse IP}}</label>
    <div class="col-sm-3">
        <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="ip" placeholder="Adresse IP"/>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">{{Mot de passe}}</label>
    <div class="col-sm-3">
        <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="password" placeholder="Mot-de-passe"/>
    </div>
  </div>

  <div class="form-group">
    <label class="col-sm-3 control-label">{{Remote PIN}}</label>
    <div class="col-sm-3">
        <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="remote_pin" placeholder="Remote PIN"/>
    </div>
  </div>

</fieldset>
</form>
</div>
<div role="tabpanel" class="tab-pane" id="commandtab">
  <a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fa fa-plus-circle"></i> {{Commandes}}</a><br/><br/>
  <table id="table_cmd" class="table table-bordered table-condensed">
      <thead>
          <tr>
              <th style="width:250px;">{{Type d'évènement}}</th><th style="width:250px;">{{Type}}</th><th>{{Dernier évènement}}</th><th style="width:150px;">{{Action}}</th>
          </tr>
      </thead>
      <tbody>
      </tbody>
  </table>
</div>

<?php
  $gds3710EventList = gds3710::get_GDS3710_event_list();
  $stack_section = array(); 
  foreach ($gds3710EventList as $row) {
    array_push($stack_section, $row['section']);
  }
  $stack_section = array_unique($stack_section); 

  $output = '';
  foreach ($stack_section as $row_main) {
    $output = $output.'<div class="tab-pane" id="'.str_replace(' ', '',strtolower($row_main)).'actiontab">';
    $output = $output.'<br/>';
    foreach ($gds3710EventList as $row_second) {
      if($row_main == $row_second['section']){
        $output = $output.'<form class="form-horizontal">';
        $output = $output.'<fieldset>';
        $output = $output.'<legend>';
        $output = $output.'{{'.(string)($row_second['type']).' - '.$row_second['message'].' :}}';
        $output = $output.'<a class="btn btn-success btn-xs pull-right addAction" data-type="'.(string)($row_second['type']).'" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>';
        $output = $output.'</legend>';
        $output = $output.'<div id="div_'.(string)($row_second['type']).'">';
        $output = $output.'</div>'; 
        $output = $output.'</fieldset>';
        $output = $output.'</form>';
      }  
    }
    $output = $output.'</div>';
  }
  echo($output);
?>

<!-- <div class="tab-pane" id="oncallactiontab">
  <br/>
  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 500 - Actions lors d'un appel sortant :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="500" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_500">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 501 - Actions lors d'un appel entrant :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="501" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_501">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 504 - Actions lors d'un appel initié par la sonnette :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="504" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_504">

          </div>
      </fieldset>
  </form>
</div>

<div class="tab-pane" id="onopenactiontab">
  <br/>
  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 100 - Actions lors d'une ouverture par carte :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="100" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_100">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 101 - Actions lors d'une ouverture par carte via Wiegand :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="101" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_101">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 300 - Actions lors d'une ouverture par PIN universel :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="300" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_300">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 301 - Actions lors d'une ouverture par PIN privé :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="301" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_301">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 302 - Actions lors d'une ouverture par PIN invité :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="302" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_302">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 600 - Actions lors d'une ouverture par carte et PIN :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="600" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_600">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 400 - Actions lors d'une ouverture par DI :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="400" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_400">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 700 - Actions lors d'une ouverture par PIN à distance :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="700" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_700">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 800 - Actions lors d'une ouverture par l'API Http :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="800" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_800">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 200 - Actions lors d'un évènemtn Visiting :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="200" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_200">

          </div>
      </fieldset>
  </form>

</div>

<div class="tab-pane" id="onkeepopenactiontab">
    <br />
    <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 601 - Actions lors d'un maintient d'ouverture immédiat :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="601" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_601">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 602 - Actions lors d'un maintient d'ouverture programmé :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="602" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_602">

          </div>
      </fieldset>
  </form>
</div>

<div class="tab-pane" id="onsecurityactiontab">
  <br />
  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1100 - Actions lors d'une alarme sabotage :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1100" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1100">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1200 - Actions lors de la saisie d'un code otage :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1200" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1200">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1300 - Actions lors de la saisie d'un code invalide :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1300" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1300">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1000 - Actions lors d'une alarme DI :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1000" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1000">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 900 - Actions lors d'une détection de mouvement :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="900" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_900">

          </div>
      </fieldset>
  </form>
</div>

<div class="tab-pane" id="onhardwareactiontab">
  <br />
  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1400 - Actions lors de détection d'une température normale de la carte mère (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1400" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1400">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1401 - Actions lors de détection d'une température trop basse de la carte mère (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1401" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1401">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1402 - Actions lors de détection d'une température trop haute de la carte mère (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1402" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1402">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1403 - Actions lors de détection d'une température normale par le capteur de température (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1403" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1403">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1404 - Actions lors de détection d'une température trop basse par le capteur de température (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1404" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1404">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1405 - Actions lors de détection d'une température trop haute par le capteur de température (32°) :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1405" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1405">

          </div>
      </fieldset>
  </form>
</div>

<div class="tab-pane" id="onsoftwareactiontab">
  <br />
  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1001 - Actions lors d'un démarrage :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1001" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1001">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1002 - Actions lors d'un redémarrage :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1002" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1002">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1003 - Actions lors d'un reset complet :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1003" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1003">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1004 - AActions lors d'un reset avec conservation des données réseaux :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1004" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1004">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1005 - Actions lors d'un reset avec conservation des données des cartes :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1005" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1005">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1006 - Actions lors d'un reset avec conservation des données réseaux et des cartes :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1006" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1006">

          </div>
      </fieldset>
  </form>

    <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1007 - Actions lors d'un reset Wiegand :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1007" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1007">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1008 - Actions lors de la modfication de la configuration :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1008" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1008">

          </div>
      </fieldset>
  </form>

  <form class="form-horizontal">
      <fieldset>
          <legend>
              {{Event Type 1009 - Actions lors de l'update du firmware :}}
              <a class="btn btn-success btn-xs pull-right addAction" data-type="1009" style="position: relative; top : 5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une action}}</a>
          </legend>
          <div id="div_1009">

          </div>
      </fieldset>
  </form>
</div> -->


</div>

</div>
</div>

<?php include_file('desktop', 'gds3710', 'js', 'gds3710');?>
<?php include_file('core', 'plugin.template', 'js');?>
