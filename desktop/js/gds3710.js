
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
$("#div_100").sortable({axis: "y", cursor: "move", items: ".100", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_101").sortable({axis: "y", cursor: "move", items: ".101", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_200").sortable({axis: "y", cursor: "move", items: ".200", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_300").sortable({axis: "y", cursor: "move", items: ".300", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_301").sortable({axis: "y", cursor: "move", items: ".301", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_302").sortable({axis: "y", cursor: "move", items: ".302", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_400").sortable({axis: "y", cursor: "move", items: ".400", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_500").sortable({axis: "y", cursor: "move", items: ".500", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_501").sortable({axis: "y", cursor: "move", items: ".501", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_504").sortable({axis: "y", cursor: "move", items: ".504", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_600").sortable({axis: "y", cursor: "move", items: ".600", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_601").sortable({axis: "y", cursor: "move", items: ".601", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_602").sortable({axis: "y", cursor: "move", items: ".602", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_700").sortable({axis: "y", cursor: "move", items: ".700", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_800").sortable({axis: "y", cursor: "move", items: ".800", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_900").sortable({axis: "y", cursor: "move", items: ".900", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1000").sortable({axis: "y", cursor: "move", items: ".1000", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1100").sortable({axis: "y", cursor: "move", items: ".1100", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1101").sortable({axis: "y", cursor: "move", items: ".1101", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1102").sortable({axis: "y", cursor: "move", items: ".1102", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1103").sortable({axis: "y", cursor: "move", items: ".1103", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1104").sortable({axis: "y", cursor: "move", items: ".1104", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1105").sortable({axis: "y", cursor: "move", items: ".1105", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1106").sortable({axis: "y", cursor: "move", items: ".1106", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1107").sortable({axis: "y", cursor: "move", items: ".1107", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1108").sortable({axis: "y", cursor: "move", items: ".1108", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1109").sortable({axis: "y", cursor: "move", items: ".1109", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1200").sortable({axis: "y", cursor: "move", items: ".1200", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1300").sortable({axis: "y", cursor: "move", items: ".1300", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1400").sortable({axis: "y", cursor: "move", items: ".1400", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1401").sortable({axis: "y", cursor: "move", items: ".1401", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1402").sortable({axis: "y", cursor: "move", items: ".1402", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1403").sortable({axis: "y", cursor: "move", items: ".1403", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1404").sortable({axis: "y", cursor: "move", items: ".1404", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#div_1405").sortable({axis: "y", cursor: "move", items: ".1405", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
/*
 * Fonction pour l'ajout de commande, appellé automatiquement par plugin.template
 */
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
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}" readonly=true>';
    tr += '</td>';
    tr += '<td>';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
    tr += '</td>';
    tr += '<td>';
    tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="value" style="height:65px;" readonly=true />';
    tr += '</td>';
    tr += '<td>';
    tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
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
    $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
    if (isset(_cmd.type)) {
        $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
    }

    jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));
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
    _eqLogic.configuration.OpenDoorViaCard = $('#div_100 .100').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaCardOverWiegand = $('#div_101 .101').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaUniversalPIN = $('#div_300 .300').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaPrivatePIN = $('#div_301 .301').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaGuestPIN = $('#div_302 .302').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaCardandPIN = $('#div_600 .600').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaDI = $('#div_400 .400').getValues('.expressionAttr');
    _eqLogic.configuration.OpenDoorViaRemotePIN = $('#div_700 .700').getValues('.expressionAttr');
    _eqLogic.configuration.HttpAPIOpenDoor = $('#div_800 .800').getValues('.expressionAttr');

    _eqLogic.configuration.VisitingLog = $('#div_200 .200').getValues('.expressionAttr');

    _eqLogic.configuration.CallOutLog = $('#div_500 .500').getValues('.expressionAttr');
    _eqLogic.configuration.CallInLog = $('#div_501 .501').getValues('.expressionAttr');
    _eqLogic.configuration.CallLogDoorBellCall = $('#div_504 .504').getValues('.expressionAttr');

    _eqLogic.configuration.KeepDoorOpenImmediately = $('#div_601 .601').getValues('.expressionAttr');
    _eqLogic.configuration.KeepDoorOpenScheduled = $('#div_602 .602').getValues('.expressionAttr');

    _eqLogic.configuration.DismantleByForce = $('#div_1100 .1100').getValues('.expressionAttr');
    _eqLogic.configuration.HostageAlarm = $('#div_1200 .1200').getValues('.expressionAttr');
    _eqLogic.configuration.InvalidPassword = $('#div_1300 .1300').getValues('.expressionAttr');
    _eqLogic.configuration.DIAlarm = $('#div_1000 .1000').getValues('.expressionAttr');
    _eqLogic.configuration.MotionDetection = $('#div_900 .900').getValues('.expressionAttr');

    _eqLogic.configuration.SystemUp = $('#div_1001 .1001').getValues('.expressionAttr');
    _eqLogic.configuration.Reboot = $('#div_1002 .1002').getValues('.expressionAttr');
    _eqLogic.configuration.ResetClearAllData = $('#div_1003 .1003').getValues('.expressionAttr');
    _eqLogic.configuration.ResetRetainNetworkDataOnly = $('#div_1004 .1004').getValues('.expressionAttr');
    _eqLogic.configuration.ResetRetainOnlyCardInformation = $('#div_1005 .1005').getValues('.expressionAttr');
    _eqLogic.configuration.ResetRetainNetworkDataAndCardInformation = $('#div_1006 .1006').getValues('.expressionAttr');
    _eqLogic.configuration.ResetWiegand = $('#div_1007 .1007').getValues('.expressionAttr');
    _eqLogic.configuration.ConfigUpdate = $('#div_1008 .1008').getValues('.expressionAttr');
    _eqLogic.configuration.FirmwareUpdate = $('#div_1009 .1009').getValues('.expressionAttr');

    _eqLogic.configuration.MainboardTemperatureNormal = $('#div_1400 .1400').getValues('.expressionAttr');
    _eqLogic.configuration.MainboardTemperatureTooLow = $('#div_1401 .1401').getValues('.expressionAttr');
    _eqLogic.configuration.MainboardTemperatureTooHigh = $('#div_1402 .1402').getValues('.expressionAttr');
    _eqLogic.configuration.SensorTemperatureNormal = $('#div_1403 .1403').getValues('.expressionAttr');
    _eqLogic.configuration.SensorTemperatureTooLow = $('#div_1404 .1404').getValues('.expressionAttr');
    _eqLogic.configuration.SensorTemperatureTooHigh = $('#div_1405 .1405').getValues('.expressionAttr');
    //_eqLogic.configuration.heating = $('#div_heat .heat').getValues('.expressionAttr');
    //_eqLogic.configuration.cooling = $('#div_cool .cool').getValues('.expressionAttr');
    //_eqLogic.configuration.stoping = $('#div_stop .stop').getValues('.expressionAttr');
    //_eqLogic.configuration.window = $('#div_window .window').getValues('.expressionAttr');
    //_eqLogic.configuration.failure = $('#div_failure .failure').getValues('.expressionAttr');
    //_eqLogic.configuration.failureActuator = $('#div_failureActuator .failureActuator').getValues('.expressionAttr');
    //_eqLogic.configuration.orderChange = $('#div_orderChange .orderChange').getValues('.expressionAttr');
    //_eqLogic.configuration.existingMode = [];
    //$('#div_modes .mode').each(function () {
        //var existingMode = $(this).getValues('.modeAttr');
        //existingMode = existingMode[0];
        //existingMode.actions = $(this).find('.modeAction').getValues('.expressionAttr');
        //_eqLogic.configuration.existingMode.push(existingMode);
    //});
    return _eqLogic;
}


function printEqLogic(_eqLogic) {
    $('#div_100').empty();
    $('#div_101').empty();
    $('#div_300').empty();
    $('#div_301').empty();
    $('#div_302').empty();
    $('#div_600').empty();
    $('#div_400').empty();
    $('#div_700').empty();
    $('#div_800').empty();

    $('#div_200').empty();

    $('#div_500').empty();
    $('#div_501').empty();
    $('#div_504').empty();

    $('#div_601').empty();
    $('#div_602').empty();

    $('#div_1100').empty();
    $('#div_1200').empty();
    $('#div_1300').empty();
    $('#div_1000').empty();
    $('#div_900').empty();

    $('#div_1001').empty();
    $('#div_1002').empty();
    $('#div_1003').empty();
    $('#div_1004').empty();
    $('#div_1005').empty();
    $('#div_1006').empty();
    $('#div_1007').empty();
    $('#div_1008').empty();
    $('#div_1009').empty();

    $('#div_1400').empty();
    $('#div_1401').empty();
    $('#div_1402').empty();
    $('#div_1403').empty();
    $('#div_1404').empty();
    $('#div_1405').empty();

    //printScheduling(_eqLogic);

    if (isset(_eqLogic.configuration)) {
        if (isset(_eqLogic.configuration.OpenDoorViaCard)) {
            for (var i in _eqLogic.configuration.OpenDoorViaCard) {
                addAction(_eqLogic.configuration.OpenDoorViaCard[i], '100');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaCardOverWiegand)) {
            for (var i in _eqLogic.configuration.OpenDoorViaCardOverWiegand) {
                addAction(_eqLogic.configuration.OpenDoorViaCardOverWiegand[i], '101');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaUniversalPIN)) {
            for (var i in _eqLogic.configuration.OpenDoorViaUniversalPIN) {
                addAction(_eqLogic.configuration.OpenDoorViaUniversalPIN[i], '300');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaPrivatePIN)) {
            for (var i in _eqLogic.configuration.OpenDoorViaPrivatePIN) {
                addAction(_eqLogic.configuration.OpenDoorViaPrivatePIN[i], '301');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaGuestPIN)) {
            for (var i in _eqLogic.configuration.OpenDoorViaGuestPIN) {
                addAction(_eqLogic.configuration.OpenDoorViaGuestPIN[i], '302');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaCardandPIN)) {
            for (var i in _eqLogic.configuration.OpenDoorViaCardandPIN) {
                addAction(_eqLogic.configuration.OpenDoorViaCardandPIN[i], '600');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaDI)) {
            for (var i in _eqLogic.configuration.OpenDoorViaDI) {
                addAction(_eqLogic.configuration.OpenDoorViaDI[i], '400');
            }
        }
        if (isset(_eqLogic.configuration.OpenDoorViaRemotePIN)) {
            for (var i in _eqLogic.configuration.OpenDoorViaRemotePIN) {
                addAction(_eqLogic.configuration.OpenDoorViaRemotePIN[i], '700');
            }
        }
        if (isset(_eqLogic.configuration.HttpAPIOpenDoor)) {
            for (var i in _eqLogic.configuration.HttpAPIOpenDoor) {
                addAction(_eqLogic.configuration.HttpAPIOpenDoor[i], '800');
            }
        }
        if (isset(_eqLogic.configuration.VisitingLog)) {
            for (var i in _eqLogic.configuration.VisitingLog) {
                addAction(_eqLogic.configuration.VisitingLog[i], '200');
            }
        }
        if (isset(_eqLogic.configuration.CallOutLog)) {
            for (var i in _eqLogic.configuration.CallOutLog) {
                addAction(_eqLogic.configuration.CallOutLog[i], '500');
            }
        }
        if (isset(_eqLogic.configuration.CallInLog)) {
            for (var i in _eqLogic.configuration.CallInLog) {
                addAction(_eqLogic.configuration.CallInLog[i], '501');
            }
        }
        if (isset(_eqLogic.configuration.CallLogDoorBellCall)) {
            for (var i in _eqLogic.configuration.CallLogDoorBellCall) {
                addAction(_eqLogic.configuration.CallLogDoorBellCall[i], '504');
            }
        }
        if (isset(_eqLogic.configuration.KeepDoorOpenImmediately)) {
            for (var i in _eqLogic.configuration.KeepDoorOpenImmediately) {
                addAction(_eqLogic.configuration.KeepDoorOpenImmediately[i], '601');
            }
        }
        if (isset(_eqLogic.configuration.KeepDoorOpenScheduled)) {
            for (var i in _eqLogic.configuration.KeepDoorOpenScheduled) {
                addAction(_eqLogic.configuration.KeepDoorOpenScheduled[i], '602');
            }
        }
        if (isset(_eqLogic.configuration.DismantleByForce)) {
            for (var i in _eqLogic.configuration.DismantleByForce) {
                addAction(_eqLogic.configuration.DismantleByForce[i], '1100');
            }
        }
        if (isset(_eqLogic.configuration.HostageAlarm)) {
            for (var i in _eqLogic.configuration.HostageAlarm) {
                addAction(_eqLogic.configuration.HostageAlarm[i], '1200');
            }
        }
        if (isset(_eqLogic.configuration.InvalidPassword)) {
            for (var i in _eqLogic.configuration.InvalidPassword) {
                addAction(_eqLogic.configuration.InvalidPassword[i], '1300');
            }
        }
        if (isset(_eqLogic.configuration.DIAlarm)) {
            for (var i in _eqLogic.configuration.DIAlarm) {
                addAction(_eqLogic.configuration.DIAlarm[i], '1000');
            }
        }
        if (isset(_eqLogic.configuration.MotionDetection)) {
            for (var i in _eqLogic.configuration.MotionDetection) {
                addAction(_eqLogic.configuration.MotionDetection[i], '900');
            }
        }
        if (isset(_eqLogic.configuration.SystemUp)) {
            for (var i in _eqLogic.configuration.SystemUp) {
                addAction(_eqLogic.configuration.SystemUp[i], '1101');
            }
        }
        if (isset(_eqLogic.configuration.Reboot)) {
            for (var i in _eqLogic.configuration.Reboot) {
                addAction(_eqLogic.configuration.Reboot[i], '1102');
            }
        }
        if (isset(_eqLogic.configuration.ResetClearAllData)) {
            for (var i in _eqLogic.configuration.ResetClearAllData) {
                addAction(_eqLogic.configuration.ResetClearAllData[i], '1103');
            }
        }
        if (isset(_eqLogic.configuration.ResetRetainNetworkDataOnly)) {
            for (var i in _eqLogic.configuration.ResetRetainNetworkDataOnly) {
                addAction(_eqLogic.configuration.ResetRetainNetworkDataOnly[i], '1104');
            }
        }
        if (isset(_eqLogic.configuration.ResetRetainOnlyCardInformation)) {
            for (var i in _eqLogic.configuration.ResetRetainOnlyCardInformation) {
                addAction(_eqLogic.configuration.ResetRetainOnlyCardInformation[i], '1105');
            }
        }
        if (isset(_eqLogic.configuration.ResetRetainNetworkDataAndCardInformation)) {
            for (var i in _eqLogic.configuration.ResetRetainNetworkDataAndCardInformation) {
                addAction(_eqLogic.configuration.ResetRetainNetworkDataAndCardInformation[i], '1106');
            }
        }
        if (isset(_eqLogic.configuration.ResetWiegand)) {
            for (var i in _eqLogic.configuration.ResetWiegand) {
                addAction(_eqLogic.configuration.ResetWiegand[i], '1107');
            }
        }
        if (isset(_eqLogic.configuration.ConfigUpdate)) {
            for (var i in _eqLogic.configuration.ConfigUpdate) {
                addAction(_eqLogic.configuration.ConfigUpdate[i], '1108');
            }
        }
        if (isset(_eqLogic.configuration.FirmwareUpdate)) {
            for (var i in _eqLogic.configuration.FirmwareUpdate) {
                addAction(_eqLogic.configuration.FirmwareUpdate[i], '1109');
            }
        }
        if (isset(_eqLogic.configuration.MainboardTemperatureNormal)) {
            for (var i in _eqLogic.configuration.MainboardTemperatureNormal) {
                addAction(_eqLogic.configuration.MainboardTemperatureNormal[i], '1400');
            }
        }
        if (isset(_eqLogic.configuration.MainboardTemperatureTooLow)) {
            for (var i in _eqLogic.configuration.MainboardTemperatureTooLow) {
                addAction(_eqLogic.configuration.MainboardTemperatureTooLow[i], '1401');
            }
        }
        if (isset(_eqLogic.configuration.MainboardTemperatureTooHigh)) {
            for (var i in _eqLogic.configuration.MainboardTemperatureTooHigh) {
                addAction(_eqLogic.configuration.MainboardTemperatureTooHigh[i], '1402');
            }
        }
        if (isset(_eqLogic.configuration.SensorTemperatureNormal)) {
            for (var i in _eqLogic.configuration.SensorTemperatureNormal) {
                addAction(_eqLogic.configuration.SensorTemperatureNormal[i], '1403');
            }
        }
        if (isset(_eqLogic.configuration.SensorTemperatureTooLow)) {
            for (var i in _eqLogic.configuration.SensorTemperatureTooLow) {
                addAction(_eqLogic.configuration.SensorTemperatureTooLow[i], '1404');
            }
        }
        if (isset(_eqLogic.configuration.SensorTemperatureTooHigh)) {
            for (var i in _eqLogic.configuration.SensorTemperatureTooHigh) {
                addAction(_eqLogic.configuration.SensorTemperatureTooHigh[i], '1405');
            }
        }



    }
}
