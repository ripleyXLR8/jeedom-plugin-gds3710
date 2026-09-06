
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


$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

/* gds3710EventTypes est servi par la page d'equipement : { "<type>": "<short_name>" },
   directement issu de get_GDS3710_event_list(). Les trois listes ci-dessous en decoulent,
   au lieu d'etre recopiees a la main comme elles l'etaient — recopie qui avait fini par
   diverger dans les deux sens. */
function gds3710PourChaqueType(_rappel) {
  if (typeof gds3710EventTypes === 'undefined') {
    return;
  }
  for (var type in gds3710EventTypes) {
    if (Object.prototype.hasOwnProperty.call(gds3710EventTypes, type)) {
      _rappel(type, gds3710EventTypes[type]);
    }
  }
}

gds3710PourChaqueType(function (type) {
  $('#div_' + type).sortable({axis: "y", cursor: "move", items: "." + type, placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */

/* Remplit la colonne des valeurs a partir de l etat reel des commandes. Un seul appel
   pour tout l equipement, plutot qu un par ligne. */
function chargerValeursCommandes(_id) {
    if (!isset(_id) || _id === '') {
        return;
    }
    $.ajax({
        type: 'POST',
        url: 'plugins/gds3710/core/ajax/gds3710.ajax.php',
        data: { action: 'getCmdValues', id: _id },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) {
            if (data.state != 'ok') {
                return;
            }
            if ($('#table_cmd tbody tr.cmd').length === 0) {
                setTimeout(function () { chargerValeursCommandes(_id); }, 400);
                return;
            }
            $('#table_cmd tbody tr.cmd').each(function () {
                var ligne = $(this);
                var info = data.result[ligne.attr('data-cmd_id')];
                if (!isset(info)) {
                    return;
                }
                ligne.find('.cmdValeur').val(info.value);
                if (info.date != '') {
                    ligne.find('.cmdValeur').attr('title', '{{Collecté le}} ' + info.date);
                }
            });
        }
    });
}


/* Liste des commandes info de l equipement, sous forme d options. Le coeur la
   construit par un appel asynchrone ; on ne l emet qu une fois et on sert toutes les
   lignes avec le meme resultat, la ou le tableau en compte une quarantaine. */
var optionsInfo = null;
var optionsInfoAttente = [];

function reinitialiserOptionsInfo() {
    optionsInfo = null;
    optionsInfoAttente = [];
}

function avecOptionsInfo(_rappel) {
    if (optionsInfo !== null) {
        _rappel(optionsInfo);
        return;
    }
    optionsInfoAttente.push(_rappel);
    if (optionsInfoAttente.length > 1) {
        return;                       // une requete est deja en vol
    }
    jeedom.eqLogic.buildSelectCmd({
        id: $('.eqLogicAttr[data-l1key=id]').value(),
        filter: { type: 'info' },
        error: function (error) {
            optionsInfoAttente = [];
            $('#div_alert').showAlert({ message: error.message, level: 'danger' });
        },
        success: function (result) {
            optionsInfo = result;
            var enAttente = optionsInfoAttente;
            optionsInfoAttente = [];
            for (var i = 0; i < enAttente.length; i++) {
                enAttente[i](result);
            }
        }
    });
}

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<span class="cmdAttr" data-l1key="id" style="display:none;"></span>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
    /* Commande info liee. Jeedom peuple et revele ce select depuis changeType(), pour
       les seules commandes action : il porte l etat que le bouton modifie. */
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;width:140px;" title="{{Commande information liée}}">';
    tr += '<option value="">{{Aucune}}</option>';
    tr += '</select>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    /* Une commande action n a pas d etat : lui presenter une zone vide laisse croire
       qu une valeur manque. La colonne ne concerne donc que les commandes info.
       Plus aucune liaison vers configuration[value] non plus : ce champ herite n est
       plus alimente, et le tableau y reecrivait son propre balisage. */
    if (init(_cmd.type) == 'info') {
        tr += '<textarea class="cmdValeur form-control input-sm" style="height:65px;" readonly="readonly"></textarea>';
    }
    tr += '</td>';
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized"/>{{Historiser}}</label></span><br/>';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:30%;max-width:70px;display:inline-block;margin-right:2px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:30%;max-width:70px;display:inline-block;margin-right:2px;">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}" title="{{Unité}}" style="width:30%;max-width:70px;display:inline-block;">';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
    tr += '</td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    /* Reference capturee maintenant : le rappel asynchrone plus bas s executera alors
       que d autres lignes auront ete ajoutees, et « tr:last » ne designerait plus
       celle-ci. */
    var ligne = $('#table_cmd tbody tr:last');
    ligne.setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        ligne.find('.cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }
    jeedom.cmd.changeType(ligne, init(_cmd.subType));

    avecOptionsInfo(function (options) {
        ligne.find('.cmdAttr[data-l1key=value]').append(options);
        /* Les valeurs sont reposees maintenant que le select porte ses options :
           le premier setValues() s appliquait a une liste encore vide. */
        ligne.setValues(_cmd, '.cmdAttr');
        if (isset(_cmd.type)) {
            ligne.find('.cmdAttr[data-l1key=type]').value(init(_cmd.type));
        }
        jeedom.cmd.changeType(ligne, init(_cmd.subType));
    });
}

$('.addAction').on('click', function () {
    addAction({}, $(this).attr('data-type'));
});

$("body").delegate('.bt_removeAction', 'click', function () {
    var type = $(this).attr('data-type');
    $(this).closest('.' + type).remove();
});

 $("body").delegate(".listCmdAction", 'click', function () {
    var type = $(this).attr('data-type');
    var el = $(this).closest('.' + type).find('.expressionAttr[data-l1key=cmd]');
    jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function (result) {
        el.value(result.human);
        jeedom.cmd.displayActionOption(el.value(), '', function (html) {
            el.closest('.' + type).find('.actionOptions').html(html);
        });

    });
});

$('body').off('focusout','.cmdAction.expressionAttr[data-l1key=cmd]').on('focusout','.cmdAction.expressionAttr[data-l1key=cmd]',function (event) {
  var type = $(this).attr('data-type');
  var expression = $(this).closest('.' + type).getValues('.expressionAttr');
  var el = $(this);
  jeedom.cmd.displayActionOption($(this).value(), init(expression[0].options), function (html) {
    el.closest('.' + type).find('.actionOptions').html(html);
  });
});

function addAction(_action, _type) {
    var div = '<div class="' + _type + '">';
    div += '<div class="form-group ">';
    div += '<label class="col-sm-1 control-label">Action</label>';
    div += '<div class="col-sm-4">';
    div += '<div class="input-group">';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default bt_removeAction btn-sm" data-type="' + _type + '"><i class="fa fa-minus-circle"></i></a>';
    div += '</span>';
    div += '<input class="expressionAttr form-control input-sm cmdAction" data-l1key="cmd" data-type="' + _type + '" />';
    div += '<span class="input-group-btn">';
    div += '<a class="btn btn-default btn-sm listCmdAction" data-type="' + _type + '"><i class="fa fa-list-alt"></i></a>';
    div += '</span>';
    div += '</div>';
    div += '</div>';
    div += '<div class="col-sm-7 actionOptions">';
    div += jeedom.cmd.displayActionOption(init(_action.cmd, ''), _action.options);
    div += '</div>';
    div += '</div>';
    $('#div_' + _type).append(div);
    $('#div_' + _type + ' .' + _type + ':last').setValues(_action, '.expressionAttr');
}

function saveEqLogic(_eqLogic) {
    if (!isset(_eqLogic.configuration)) {
        _eqLogic.configuration = {};
    }
    /* Cette liste etait ecrite a la main et lisait les mauvais conteneurs pour toute la
       famille « Surveillance Logiciel » : SystemUp allait chercher #div_1001 quand la page
       produit #div_1101, Reboot #div_1002 pour #div_1102, et ainsi de suite jusqu'a
       FirmwareUpdate. Consequence, les actions posees sur ces neuf types n'etaient jamais
       enregistrees — et l'enregistrement ecrasait en prime celles deja en base. Depuis que
       le catalogue declare un type 1002, les actions du nouvel onglet « Door and Lock
       Abnormal Alarm » partaient meme sous la clef « Reboot ». L'affichage, lui, utilisait
       les bons identifiants : les deux sens se contredisaient. */
    gds3710PourChaqueType(function (type, nom) {
        _eqLogic.configuration[nom] = $('#div_' + type + ' .' + type).getValues('.expressionAttr');
    });
    return _eqLogic;
}


function printEqLogic(_eqLogic) {
    /* La liste des commandes info appartient a l equipement affiche : on la jette en
       changeant d equipement. */
    reinitialiserOptionsInfo();

    /* Differe : le coeur appelle printEqLogic AVANT de construire les lignes du
       tableau. Sans ce report, la reponse pourrait arriver avant qu il y ait des
       lignes a remplir. */
    setTimeout(function () {
        chargerValeursCommandes(_eqLogic.id);
    }, 0);

    /* Vider avant de reafficher. Les neuf conteneurs 1101 a 1109 n'etaient pas vides ici
       non plus — la liste visait 1001 a 1009, qui n'existent pas — si bien qu'en passant
       d'un equipement a l'autre leurs actions s'empilaient a l'ecran. */
    gds3710PourChaqueType(function (type) {
        $('#div_' + type).empty();
    });

    //printScheduling(_eqLogic);

    if (isset(_eqLogic.configuration)) {
        gds3710PourChaqueType(function (type, nom) {
            if (!isset(_eqLogic.configuration[nom])) {
                return;
            }
            for (var i in _eqLogic.configuration[nom]) {
                addAction(_eqLogic.configuration[nom][i], type);
            }
        });
    }
}
