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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>

<form class="form-horizontal">
    <fieldset>
        <legend>{{Configuration du serveur}}</legend>
        <div class="form-group">
            <label class="col-lg-3 control-label">Activer la protection par mot de passe</label>
            <div id="div_password_protection" class="col-lg-3 tooltips" title="{{ Veuillez regarder la documentation pour identifier votre compteur }}">
                <input type="checkbox" id="password_protection" class="configKey" data-l1key="password_protection" placeholder="{{}}"/>
                <label for="password_protection">  </label>
                <label id="label_password_protection" style="color:red;margin-left:100px;margin-top:-15px;display:none">ATTENTION : l'activation de cette option est très fortement encouragée. Elle est obligatoire dans le cas ou des actions critiques de sécurité sont déclenchées lors d'évènements générés par le portier.</label>
                <script>
                $( "#password_protection" ).change(function() {
                        if($( this ).value() == "1"){
                            $("#label_password_protection").hide();
                            $("#password-form-group").show();
                            $("#login-form-group").show();
                        }
                        else{
                            $("#label_password_protection").show();
                            $("#password-form-group").hide();
                            $("#login-form-group").hide();
                        }
                });
                </script>
            </div>
        </div>
        <div id="login-form-group" class="form-group">
            <label class="col-lg-3 control-label">{{Idenfiant }}<sup><i class="fa fa-question-circle tooltips" title="{{Il s'agit de l'identifiant que votre portier devra fournir pour publier ses évènements dans Jeedom.}}" style="font-size : 1em;color:grey;"></i></sup></label>
            <div class="col-lg-2">
                <input  type="text" class="configKey" data-l1key="login" />
            </div>
        </div>
        <div id="password-form-group" class="form-group">
           <label class="col-lg-3 control-label">{{Mot de passe }}<sup><i class="fa fa-question-circle tooltips" title="{{Il s'agit du mot de passe que votre portier devra fournir pour publier ses évènements dans Jeedom.}}" style="font-size : 1em;color:grey;"></i></sup></label>
           <div class="col-lg-2">
            <input type="password" class="configKey" data-l1key="password" />
        </div>
    </div>
</fieldset>
</form>