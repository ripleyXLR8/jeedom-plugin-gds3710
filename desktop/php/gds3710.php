<?php
  if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
  }
  $plugin = plugin::byId('gds3710');
  sendVarToJS('eqType', $plugin->getId());
  $eqLogics = eqLogic::byType($plugin->getId());
?>

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
        <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676">{{Configuration}}</span>
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

        foreach ($stack_section as $row_main) {
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
	                          foreach (jeeObject::all() as $object) {
	                            echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
	                          }
	                        ?>
	                   </select>
	               </div>
	           	</div>

    			<div class="form-group">
                	<label class="col-sm-3 control-label">{{Catégorie}}</label>
                	<div class="col-sm-3">
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
    				<div class="col-sm-3">
      					<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
      					<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
    				</div>
  				</div>
  			</fieldset>

  			<fieldset>
  				<legend>{{Configuration général}}</legend>

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

				<div class="form-group">
				    <label class="col-sm-3 control-label">{{Remote PIN 2}}</label>
				    <div class="col-sm-3">
				        <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="remote_pin_2" placeholder="Remote PIN 2"/>
				    </div>
				</div>

				<div class="form-group">
				    <label class="col-sm-3 control-label">{{Type d'authentification MJPEG :}}</label>
				    <div class="col-sm-3">
				    	<select class="eqLogicAttr form-control tooltips" title="{{type d'authentification pour le flux MJPEG}}" data-l1key="configuration" data-l2key="auth_type">
				        	<option value="basic">Basic</option>
				        	<option value="challenge">Challenge-Response</option>
				      </select>
				    </div>
				</div>
			</fieldset>

			<fieldset>
				<legend>{{Configuration client SIP (beta)}}</legend>

				<div class="form-group">
				    <label class="col-sm-3 control-label">{{Adresse du serveur SIP :}}</label>
				    <div class="col-sm-3">
				        <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="client_sip_websocket" placeholder="Adresse du serveur SIP"/>
				    </div>
				</div>

				<div class="form-group">
				    <label class="col-sm-3 control-label">{{URI du client SIP :}}</label>
				    <div class="col-sm-3">
				        <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="client_sip_uri" placeholder="URI du client SIP"/>
				    </div>
				</div>

				<div class="form-group">
				    <label class="col-sm-3 control-label">{{Mot-de-passe du client SIP :}}</label>
				    <div class="col-sm-3">
				        <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="client_sip_password" placeholder="Mot-de-passe du client SIP"/>
				    </div>
				</div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{URI du portier SIP :}}</label>
            <div class="col-sm-3">
                <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="portier_sip_uri" placeholder="URI du portier SIP"/>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Débogage SIP :}}</label>
            <div class="col-sm-3">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_sip_debug_enabled" checked/>{{Activer}}</label>
            </div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{Media des appels entrants :}}</label>
            <div class="col-sm-4">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_remote_call_audio_enabled" checked/>{{Audio}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_remote_call_video_enabled" checked/>{{Video}}</label>
<!--                 <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_remote_call_offer_audio_enabled" checked/>{{Recevoir Audio}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_remote_call_offer_video_enabled" checked/>{{Recevoir Video}}</label> -->
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-3 control-label">{{Media des appels sortants :}}</label>
            <div class="col-sm-4">
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_local_call_audio_enabled" checked/>{{Audio}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_local_call_video_enabled" checked/>{{Video}}</label>
<!--                 <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_local_call_offer_audio_enabled" checked/>{{Recevoir Audio}}</label>
                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="is_local_call_offer_video_enabled" checked/>{{Recevoir Video}}</label> -->
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
              <th style="width:250px;">{{Type d'évènement}}</th>
              <th style="width:250px;">{{Type}}</th>
              <th>{{Dernier évènement}}</th>
              <th style="width:250px;">{{Paramètres}}</th>
              <th style="width:150px;">{{Action}}</th>
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

</div>

</div>
</div>

<?php include_file('desktop', 'gds3710', 'js', 'gds3710');?>
<?php include_file('core', 'plugin.template', 'js');?>
