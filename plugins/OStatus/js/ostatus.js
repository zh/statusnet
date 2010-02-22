/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  OStatus UI interaction
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 * @note      Everything in here should eventually migrate over to /js/util.js's SN.
 */

SN.C.S.StatusNetInstance = 'StatusNetInstance';

SN.U.StatusNetInstance = {
    Set: function(value) {
        $.cookie(
            SN.C.S.StatusNetInstance,
            JSON.stringify(value),
            {
                path: '/',
                expires: SN.U.GetFullYear(2029, 0, 1)
            });
    },

    Get: function() {
        var cookieValue = $.cookie(SN.C.S.StatusNetInstance);
        if (cookieValue !== null) {
            return JSON.parse(cookieValue);
        }
        return null;
    },

    Delete: function() {
        $.cookie(SN.C.S.StatusNetInstance, null);
    }
};

SN.Init.OStatusCookie = function() {
    if (SN.U.StatusNetInstance.Get() === null) {
        SN.C.I.OStatusProfile = SN.C.I.OStatusProfile || null;
        SN.U.StatusNetInstance.Set({profile: SN.C.I.OStatusProfile});
    }
};

SN.U.DialogBox = {
    Subscribe: function(a) {
        var f = a.parent().find('.form_settings');
        if (f.length > 0) {
            f.show();
        }
        else {
            a[0].href = (a[0].href.match(/[\\?]/) === null) ? a[0].href+'?' : a[0].href+'&';
            $.ajax({
                type: 'GET',
                dataType: 'xml',
                url: a[0].href+'ajax=1',
                beforeSend: function(formData) {
                    a.addClass('processing');
                },
                error: function (xhr, textStatus, errorThrown) {
                    alert(errorThrown || textStatus);
                },
                success: function(data, textStatus, xhr) {
                    if (typeof($('form', data)[0]) != 'undefined') {
                        a.after(document._importNode($('form', data)[0], true));

                        var form = a.parent().find('.form_settings');

                        form
                            .addClass('dialogbox')
                            .append('<button class="close">&#215;</button>');

                        form
                            .find('.submit')
                                .addClass('submit_dialogbox')
                                .removeClass('submit')
                                .bind('click', function() {
                                    form.addClass('processing');
                                });

                        form.find('button.close').click(function(){
                            form.hide();

                            return false;
                        });

                        form.find('#profile').focus();

                        if (form.attr('id') == 'form_ostatus_connect') {
                            SN.Init.OStatusCookie();
                            form.find('#profile').val(SN.U.StatusNetInstance.Get().profile);

                            form.find("[type=submit]").bind('click', function() {
                                SN.U.StatusNetInstance.Set({profile: form.find('#profile').val()});
                                return true;
                            });
                        }
                    }

                    a.removeClass('processing');
                }
            });
        }
    }
};

SN.Init.Subscribe = function() {
    $('.entity_subscribe .entity_remote_subscribe').live('click', function() { SN.U.DialogBox.Subscribe($(this)); return false; });
};

$(document).ready(function() {
    SN.Init.Subscribe();
});
