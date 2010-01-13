/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, StatusNet, Inc.
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
 * @category  UI interaction
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

var SN = { // StatusNet
    C: { // Config
        I: { // Init
            CounterBlackout: false,
            MaxLength: 140,
            PatternUsername: /^[0-9a-zA-Z\-_.]*$/,
            HTTP20x30x: [200, 201, 202, 203, 204, 205, 206, 300, 301, 302, 303, 304, 305, 306, 307]
        },

        S: { // Selector
            Disabled: 'disabled',
            Warning: 'warning',
            Error: 'error',
            Success: 'success',
            Processing: 'processing',
            CommandResult: 'command_result',
            FormNotice: 'form_notice',
            NoticeDataText: 'notice_data-text',
            NoticeTextCount: 'notice_text-count',
            NoticeInReplyTo: 'notice_in-reply-to',
            NoticeDataAttach: 'notice_data-attach',
            NoticeDataAttachSelected: 'notice_data-attach_selected',
            NoticeActionSubmit: 'notice_action-submit',
            NoticeLat: 'notice_data-lat',
            NoticeLon: 'notice_data-lon',
            NoticeLocationId: 'notice_data-location_id',
            NoticeLocationNs: 'notice_data-location_ns',
            NoticeGeoName: 'notice_data-geo_name',
            NoticeDataGeo: 'notice_data-geo',
            NoticeDataGeoCookie: 'notice_data-geo_cookie',
            NoticeDataGeoSelected: 'notice_data-geo_selected'
        }
    },

    U: { // Utils
        FormNoticeEnhancements: function(form) {
            form_id = form.attr('id');

            if (jQuery.data(form[0], 'ElementData') === undefined) {
                MaxLength = $('#'+form_id+' #'+SN.C.S.NoticeTextCount).text();
                if (typeof(MaxLength) == 'undefined') {
                     MaxLength = SN.C.I.MaxLength;
                }
                jQuery.data(form[0], 'ElementData', {MaxLength:MaxLength});

                SN.U.Counter(form);

                NDT = $('#'+form_id+' #'+SN.C.S.NoticeDataText);

                NDT.bind('keyup', function(e) {
                    SN.U.Counter(form);
                });

                NDT.bind('keydown', function(e) {
                    SN.U.SubmitOnReturn(e, form);
                });
            }
            else {
                $('#'+form_id+' #'+SN.C.S.NoticeTextCount).text(jQuery.data(form[0], 'ElementData').MaxLength);
            }

            if ($('body')[0].id != 'conversation') {
                $('#'+form_id+' textarea').focus();
            }
        },

        SubmitOnReturn: function(event, el) {
            if (event.keyCode == 13 || event.keyCode == 10) {
                el.submit();
                event.preventDefault();
                event.stopPropagation();
                $('#'+el[0].id+' #'+SN.C.S.NoticeDataText).blur();
                $('body').focus();
                return false;
            }
            return true;
        },

        Counter: function(form) {
            SN.C.I.FormNoticeCurrent = form;
            form_id = form.attr('id');

            var MaxLength = jQuery.data(form[0], 'ElementData').MaxLength;

            if (MaxLength <= 0) {
                return;
            }

            var remaining = MaxLength - $('#'+form_id+' #'+SN.C.S.NoticeDataText).val().length;
            var counter = $('#'+form_id+' #'+SN.C.S.NoticeTextCount);

            if (remaining.toString() != counter.text()) {
                if (!SN.C.I.CounterBlackout || remaining === 0) {
                    if (counter.text() != String(remaining)) {
                        counter.text(remaining);
                    }
                    if (remaining < 0) {
                        form.addClass(SN.C.S.Warning);
                    } else {
                        form.removeClass(SN.C.S.Warning);
                    }
                    // Skip updates for the next 500ms.
                    // On slower hardware, updating on every keypress is unpleasant.
                    if (!SN.C.I.CounterBlackout) {
                        SN.C.I.CounterBlackout = true;
                        SN.C.I.FormNoticeCurrent = form;
                        window.setTimeout("SN.U.ClearCounterBlackout(SN.C.I.FormNoticeCurrent);", 500);
                    }
                }
            }
        },

        ClearCounterBlackout: function(form) {
            // Allow keyup events to poke the counter again
            SN.C.I.CounterBlackout = false;
            // Check if the string changed since we last looked
            SN.U.Counter(form);
        },

        FormXHR: function(f) {
            if (jQuery.data(f[0], "ElementData") === undefined) {
                jQuery.data(f[0], "ElementData", {Bind:'submit'});
                f.bind('submit', function(e) {
                    form_id = $(this)[0].id;
                    $.ajax({
                        type: 'POST',
                        dataType: 'xml',
                        url: $(this)[0].action,
                        data: $(this).serialize() + '&ajax=1',
                        beforeSend: function(xhr) {
                            $('#'+form_id).addClass(SN.C.S.Processing);
                            $('#'+form_id+' .submit').addClass(SN.C.S.Disabled);
                            $('#'+form_id+' .submit').attr(SN.C.S.Disabled, SN.C.S.Disabled);
                        },
                        error: function (xhr, textStatus, errorThrown) {
                            alert(errorThrown || textStatus);
                        },
                        success: function(data, textStatus) {
                            if (typeof($('form', data)[0]) != 'undefined') {
                                form_new = document._importNode($('form', data)[0], true);
                                $('#'+form_id).replaceWith(form_new);
                                $('#'+form_new.id).each(function() { SN.U.FormXHR($(this)); });
                            }
                            else {
                                $('#'+form_id).replaceWith(document._importNode($('p', data)[0], true));
                            }
                        }
                    });
                    return false;
                });
            }
        },

        FormNoticeXHR: function(form) {
            var NDG, NLat, NLon, NLNS, NLID;
            form_id = form.attr('id');
            form.append('<input type="hidden" name="ajax" value="1"/>');
            form.ajaxForm({
                dataType: 'xml',
                timeout: '60000',
                beforeSend: function(formData) {
                    if ($('#'+form_id+' #'+SN.C.S.NoticeDataText)[0].value.length === 0) {
                        form.addClass(SN.C.S.Warning);
                        return false;
                    }
                    form.addClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).addClass(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).attr(SN.C.S.Disabled, SN.C.S.Disabled);

                    NLat = $('#'+SN.C.S.NoticeLat).val();
                    NLon = $('#'+SN.C.S.NoticeLon).val();
                    NLNS = $('#'+SN.C.S.NoticeLocationNs).val();
                    NLID = $('#'+SN.C.S.NoticeLocationId).val();
                    NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked');

                    cookieValue = $.cookie(SN.C.S.NoticeDataGeoCookie);

                    if (cookieValue !== null && cookieValue != 'disabled') {
                        cookieValue = JSON.parse(cookieValue);
                        NLat = $('#'+SN.C.S.NoticeLat).val(cookieValue.NLat).val();
                        NLon = $('#'+SN.C.S.NoticeLon).val(cookieValue.NLon).val();
                        NLNS = $('#'+SN.C.S.NoticeLocationNs).val(cookieValue.NLNS).val();
                        NLID = $('#'+SN.C.S.NoticeLocationId).val(cookieValue.NLID).val();
                    }
                    if (cookieValue == 'disabled') {
                        NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked', false).attr('checked');
                    }
                    else {
                        NDG = $('#'+SN.C.S.NoticeDataGeo).attr('checked', true).attr('checked');
                    }

                    return true;
                },
                error: function (xhr, textStatus, errorThrown) {
                    form.removeClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeClass(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeAttr(SN.C.S.Disabled, SN.C.S.Disabled);
                    $('#'+form_id+' .form_response').remove();
                    if (textStatus == 'timeout') {
                        form.append('<p class="form_response error">Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists.</p>');
                    }
                    else {
                        if ($('.'+SN.C.S.Error, xhr.responseXML).length > 0) {
                            form.append(document._importNode($('.'+SN.C.S.Error, xhr.responseXML)[0], true));
                        }
                        else {
                            if (parseInt(xhr.status) === 0 || jQuery.inArray(parseInt(xhr.status), SN.C.I.HTTP20x30x) >= 0) {
                                $('#'+form_id).resetForm();
                                $('#'+form_id+' #'+SN.C.S.NoticeDataAttachSelected).remove();
                                SN.U.FormNoticeEnhancements($('#'+form_id));
                            }
                            else {
                                form.append('<p class="form_response error">(Sorry! We had trouble sending your notice ('+xhr.status+' '+xhr.statusText+'). Please report the problem to the site administrator if this happens again.</p>');
                            }
                        }
                    }
                },
                success: function(data, textStatus) {
                    $('#'+form_id+' .form_response').remove();
                    var result;
                    if ($('#'+SN.C.S.Error, data).length > 0) {
                        result = document._importNode($('p', data)[0], true);
                        result = result.textContent || result.innerHTML;
                        form.append('<p class="form_response error">'+result+'</p>');
                    }
                    else {
                        if($('body')[0].id == 'bookmarklet') {
                            self.close();
                        }

                        if ($('#'+SN.C.S.CommandResult, data).length > 0) {
                            result = document._importNode($('p', data)[0], true);
                            result = result.textContent || result.innerHTML;
                            form.append('<p class="form_response success">'+result+'</p>');
                        }
                        else {
                            var notices = $('#notices_primary .notices');
                            if (notices.length > 0) {
                                var notice = document._importNode($('li', data)[0], true);
                                if ($('#'+notice.id).length === 0) {
                                    var notice_irt_value = $('#'+SN.C.S.NoticeInReplyTo).val();
                                    var notice_irt = '#notices_primary #notice-'+notice_irt_value;
                                    if($('body')[0].id == 'conversation') {
                                        if(notice_irt_value.length > 0 && $(notice_irt+' .notices').length < 1) {
                                            $(notice_irt).append('<ul class="notices"></ul>');
                                        }
                                        $($(notice_irt+' .notices')[0]).append(notice);
                                    }
                                    else {
                                        notices.prepend(notice);
                                    }
                                    $('#'+notice.id).css({display:'none'});
                                    $('#'+notice.id).fadeIn(2500);
                                    SN.U.NoticeWithAttachment($('#'+notice.id));
                                    SN.U.NoticeReplyTo($('#'+notice.id));
                                    SN.U.FormXHR($('#'+notice.id+' .form_favor'));
                                }
                            }
                            else {
                                result = document._importNode($('title', data)[0], true);
                                result_title = result.textContent || result.innerHTML;
                                form.append('<p class="form_response success">'+result_title+'</p>');
                            }
                        }
                        $('#'+form_id).resetForm();
                        $('#'+form_id+' #'+SN.C.S.NoticeInReplyTo).val('');
                        $('#'+form_id+' #'+SN.C.S.NoticeDataAttachSelected).remove();
                        SN.U.FormNoticeEnhancements($('#'+form_id));
                    }
                },
                complete: function(xhr, textStatus) {
                    form.removeClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeAttr(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeClass(SN.C.S.Disabled);

                    $('#'+SN.C.S.NoticeLat).val(NLat);
                    $('#'+SN.C.S.NoticeLon).val(NLon);
                    $('#'+SN.C.S.NoticeLocationNs).val(NLNS);
                    $('#'+SN.C.S.NoticeLocationId).val(NLID);
                    $('#'+SN.C.S.NoticeDataGeo).attr('checked', NDG);
                }
            });
        },

        NoticeReply: function() {
            if ($('#'+SN.C.S.NoticeDataText).length > 0 && $('#content .notice_reply').length > 0) {
                $('#content .notice').each(function() { SN.U.NoticeReplyTo($(this)); });
            }
        },

        NoticeReplyTo: function(notice_item) {
            var notice = notice_item[0];
            var notice_reply = $('.notice_reply', notice)[0];

            if (jQuery.data(notice_reply, "ElementData") === undefined) {
                jQuery.data(notice_reply, "ElementData", {Bind:'submit'});
                $(notice_reply).bind('click', function() {
                    var nickname = ($('.author .nickname', notice).length > 0) ? $($('.author .nickname', notice)[0]) : $('.author .nickname.uid');
                    SN.U.NoticeReplySet(nickname.text(), $($('.notice_id', notice)[0]).text());
                    return false;
                });
            }
        },

        NoticeReplySet: function(nick,id) {
            if (nick.match(SN.C.I.PatternUsername)) {
                var text = $('#'+SN.C.S.NoticeDataText);
                if (text.length > 0) {
                    replyto = '@' + nick + ' ';
                    text.val(replyto + text.val().replace(RegExp(replyto, 'i'), ''));
                    $('#'+SN.C.S.FormNotice+' #'+SN.C.S.NoticeInReplyTo).val(id);

                    text[0].focus();
                    if (text[0].setSelectionRange) {
                        var len = text.val().length;
                        text[0].setSelectionRange(len,len);
                    }
                }
            }
        },

        NoticeFavor: function() {
            $('.form_favor').each(function() { SN.U.FormXHR($(this)); });
            $('.form_disfavor').each(function() { SN.U.FormXHR($(this)); });
        },

        NoticeRepeat: function() {
            $('.form_repeat').each(function() {
                SN.U.FormXHR($(this));
                SN.U.NoticeRepeatConfirmation($(this));
            });
        },

        NoticeRepeatConfirmation: function(form) {
            function NRC() {
                form.closest('.notice-options').addClass('opaque');
                form.addClass('dialogbox');

                form.append('<button class="close">&#215;</button>');
                form.find('button.close').click(function(){
                    $(this).remove();

                    form.closest('.notice-options').removeClass('opaque');
                    form.removeClass('dialogbox');
                    form.find('.submit_dialogbox').remove();
                    form.find('.submit').show();

                    return false;
                });
            };

            form.find('.submit').bind('click', function(e) {
                e.preventDefault();

                var submit = form.find('.submit').clone();
                submit.addClass('submit_dialogbox');
                submit.removeClass('submit');
                form.append(submit);

                $(this).hide();

                NRC();
            });
        },

        NoticeAttachments: function() {
            $('.notice a.attachment').each(function() {
                SN.U.NoticeWithAttachment($(this).closest('.notice'));
            });
        },

        NoticeWithAttachment: function(notice) {
            if ($('.attachment', notice).length === 0) {
                return;
            }

            var notice_id = notice.attr('id');

            $.fn.jOverlay.options = {
                method : 'GET',
                data : '',
                url : '',
                color : '#000',
                opacity : '0.6',
                zIndex : 9999,
                center : false,
                imgLoading : $('address .url')[0].href+'theme/base/images/illustrations/illu_progress_loading-01.gif',
                bgClickToClose : true,
                success : function() {
                    $('#jOverlayContent').append('<button class="close">&#215;</button>');
                    $('#jOverlayContent button').click($.closeOverlay);
                },
                timeout : 0,
                autoHide : true,
                css : {'max-width':'542px', 'top':'5%', 'left':'32.5%'}
            };

            $('#'+notice_id+' a.attachment').click(function() {
                $().jOverlay({url: $('address .url')[0].href+'attachment/' + ($(this).attr('id').substring('attachment'.length + 1)) + '/ajax'});
                return false;
            });

            var t;
            $("body:not(#shownotice) #"+notice_id+" a.thumbnail").hover(
                function() {
                    var anchor = $(this);
                    $("a.thumbnail").children('img').hide();
                    anchor.closest(".entry-title").addClass('ov');

                    if (anchor.children('img').length === 0) {
                        t = setTimeout(function() {
                            $.get($('address .url')[0].href+'attachment/' + (anchor.attr('id').substring('attachment'.length + 1)) + '/thumbnail', null, function(data) {
                                anchor.append(data);
                            });
                        }, 500);
                    }
                    else {
                        anchor.children('img').show();
                    }
                },
                function() {
                    clearTimeout(t);
                    $("a.thumbnail").children('img').hide();
                    $(this).closest(".entry-title").removeClass('ov');
                }
            );
        },

        NoticeDataAttach: function() {
            NDA = $('#'+SN.C.S.NoticeDataAttach);
            NDA.change(function() {
                S = '<div id="'+SN.C.S.NoticeDataAttachSelected+'" class="'+SN.C.S.Success+'"><code>'+$(this).val()+'</code> <button class="close">&#215;</button></div>';
                NDAS = $('#'+SN.C.S.NoticeDataAttachSelected);
                if (NDAS.length > 0) {
                    NDAS.replaceWith(S);
                }
                else {
                    $('#'+SN.C.S.FormNotice).append(S);
                }
                $('#'+SN.C.S.NoticeDataAttachSelected+' button').click(function(){
                    $('#'+SN.C.S.NoticeDataAttachSelected).remove();
                    NDA.val('');

                    return false;
                });
            });
        },

        NoticeLocationAttach: function() {
            var NLat = $('#'+SN.C.S.NoticeLat).val();
            var NLon = $('#'+SN.C.S.NoticeLon).val();
            var NLNS = $('#'+SN.C.S.NoticeLocationNs).val();
            var NLID = $('#'+SN.C.S.NoticeLocationId).val();
            var NLN = $('#'+SN.C.S.NoticeGeoName).text();
            var NDGe = $('#'+SN.C.S.NoticeDataGeo);

            function removeNoticeDataGeo() {
                $('label[for='+SN.C.S.NoticeDataGeo+']').removeClass('checked').attr('title', jQuery.trim($('label[for='+SN.C.S.NoticeDataGeo+']').text()));
                $('#'+SN.C.S.NoticeDataGeoSelected).hide();

                $('#'+SN.C.S.NoticeLat).val('');
                $('#'+SN.C.S.NoticeLon).val('');
                $('#'+SN.C.S.NoticeLocationNs).val('');
                $('#'+SN.C.S.NoticeLocationId).val('');
                $('#'+SN.C.S.NoticeDataGeo).attr('checked', false);

                $.cookie(SN.C.S.NoticeDataGeoCookie, 'disabled');
            }

            function getJSONgeocodeURL(geocodeURL, data) {
                $.getJSON(geocodeURL, data, function(location) {
                    var lns, lid;

                    if (typeof(location.location_ns) != 'undefined') {
                        $('#'+SN.C.S.NoticeLocationNs).val(location.location_ns);
                        lns = location.location_ns;
                    }

                    if (typeof(location.location_id) != 'undefined') {
                        $('#'+SN.C.S.NoticeLocationId).val(location.location_id);
                        lid = location.location_id;
                    }

                    if (typeof(location.name) == 'undefined') {
                        NLN_text = position.coords.latitude + ';' + position.coords.longitude;
                    }
                    else {
                        NLN_text = location.name;
                    }

                    $('#'+SN.C.S.NoticeGeoName)
                        .replaceWith('<a id="notice_data-geo_name"/>');

                    $('#'+SN.C.S.NoticeGeoName)
                        .attr('href', location.url)
                        .text(NLN_text)
                        .click(function() {
                            window.open(location.url);

                            return false;
                        });

                    $('#'+SN.C.S.NoticeLat).val(data.lat);
                    $('#'+SN.C.S.NoticeLon).val(data.lon);
                    $('#'+SN.C.S.NoticeLocationNs).val(lns);
                    $('#'+SN.C.S.NoticeLocationId).val(lid);
                    $('#'+SN.C.S.NoticeDataGeo).attr('checked', true);

                    var cookieValue = {
                        'NLat': data.lat,
                        'NLon': data.lon,
                        'NLNS': lns,
                        'NLID': lid,
                        'NLN': NLN_text,
                        'NLNU': location.url,
                        'NDG': true,
                        'NDGSM': false
                    };
                    $.cookie(SN.C.S.NoticeDataGeoCookie, JSON.stringify(cookieValue));
                });
            }

            if (NDGe.length > 0) {
                if ($.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                    NDGe.attr('checked', false);
                }
                else {
                    NDGe.attr('checked', true);
                }

                var NGW = $('#notice_data-geo_wrap');
                var geocodeURL = NGW.attr('title');
                NGW.removeAttr('title');

                $('label[for='+SN.C.S.NoticeDataGeo+']').attr('title', jQuery.trim($('label[for='+SN.C.S.NoticeDataGeo+']').text()));

                NDGe.change(function() {
                    var NLN = $('#'+SN.C.S.NoticeGeoName);
                    if (NLN.length > 0) {
                        NLN.remove();
                    }

                    if ($('#'+SN.C.S.NoticeDataGeo).attr('checked') === true || $.cookie(SN.C.S.NoticeDataGeoCookie) === null) {
                        $('label[for='+SN.C.S.NoticeDataGeo+']').addClass('checked').attr('title', NoticeDataGeo_text.ShareDisable);

                        var S = '<div id="'+SN.C.S.NoticeDataGeoSelected+'" class="'+SN.C.S.Success+'"/>';
                        var NDGS = $('#'+SN.C.S.NoticeDataGeoSelected);

                        if (NDGS.length > 0) {
                            NDGS.replaceWith(S);
                        }
                        else {
                            $('#'+SN.C.S.FormNotice).append(S);
                        }

                        NDGS = $('#'+SN.C.S.NoticeDataGeoSelected);
                        NDGS.prepend('<span id="'+SN.C.S.NoticeGeoName+'">Geo</span> <button class="minimize" title="'+NoticeDataGeo_text.InfoMinimize+'">&#95;</button> <button class="close" title="'+NoticeDataGeo_text.ShareDisable+'">&#215;</button>');

                        var NLN = $('#'+SN.C.S.NoticeGeoName);
                        NLN.addClass('processing');

                        $('#'+SN.C.S.NoticeDataGeoSelected+' button.close').click(function(){
                            removeNoticeDataGeo();

                            $('#'+SN.C.S.NoticeDataGeoSelected).remove();

                            $('#'+SN.C.S.NoticeDataText).focus();

                            return false;
                        });

                        $('#'+SN.C.S.NoticeDataGeoSelected+' button.minimize').click(function(){
                            $('#'+SN.C.S.NoticeDataGeoSelected).hide();

                            var cookieValue = {
                                'NLat': $('#'+SN.C.S.NoticeLat).val(),
                                'NLon': $('#'+SN.C.S.NoticeLat).val(),
                                'NLNS': $('#'+SN.C.S.NoticeLocationNs).val(),
                                'NLID': $('#'+SN.C.S.NoticeLocationId).val(),
                                'NLN': $('#'+SN.C.S.NoticeGeoName).text(),
                                'NLNU': $('#'+SN.C.S.NoticeGeoName).attr('href'),
                                'NDG': true,
                                'NDGSM': true
                            };
                            $.cookie(SN.C.S.NoticeDataGeoCookie, JSON.stringify(cookieValue));

                            $('#'+SN.C.S.NoticeDataText).focus();

                            return false;
                        });

                        if ($.cookie(SN.C.S.NoticeDataGeoCookie) === null || $.cookie(SN.C.S.NoticeDataGeoCookie) == 'disabled') {
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    function(position) {
                                        $('#'+SN.C.S.NoticeLat).val(position.coords.latitude);
                                        $('#'+SN.C.S.NoticeLon).val(position.coords.longitude);

                                        var data = {
                                            'lat': position.coords.latitude,
                                            'lon': position.coords.longitude,
                                            'token': $('#token').val()
                                        };

                                        getJSONgeocodeURL(geocodeURL, data);
                                    },

                                    function(error) {
                                        if (error.PERMISSION_DENIED == 1) {
                                            removeNoticeDataGeo();
                                        }
                                    }
                                );
                            }
                            else {
                                if (NLat.length > 0 && NLon.length > 0) {
                                    var data = {
                                        'lat': NLat,
                                        'lon': NLon,
                                        'token': $('#token').val()
                                    };

                                    getJSONgeocodeURL(geocodeURL, data);
                                }
                                else {
                                    removeNoticeDataGeo();
                                    $('#'+SN.C.S.NoticeDataGeo).remove();
                                    $('label[for='+SN.C.S.NoticeDataGeo+']').remove();
                                }
                            }
                        }
                        else {
                            var cookieValue = JSON.parse($.cookie(SN.C.S.NoticeDataGeoCookie));

                            if (cookieValue.NDGSM === true) {
                                $('#'+SN.C.S.NoticeDataGeoSelected).hide();
                            }

                            $('#'+SN.C.S.NoticeLat).val(cookieValue.NLat);
                            $('#'+SN.C.S.NoticeLon).val(cookieValue.NLon);
                            $('#'+SN.C.S.NoticeLocationNs).val(cookieValue.NLNS);
                            $('#'+SN.C.S.NoticeLocationId).val(cookieValue.NLID);
                            $('#'+SN.C.S.NoticeDataGeo).attr('checked', cookieValue.NDG);

                            $('#'+SN.C.S.NoticeGeoName)
                                .replaceWith('<a id="notice_data-geo_name"/>');

                            $('#'+SN.C.S.NoticeGeoName)
                                .attr('href', cookieValue.NLNU)
                                .text(cookieValue.NLN)
                                .click(function() {
                                    window.open($(this).attr('href'));

                                    return false;
                                });
                        }
                    }
                    else {
                        removeNoticeDataGeo();
                    }

                    $('#'+SN.C.S.NoticeDataText).focus();
                }).change();
            }
        },

        NewDirectMessage: function() {
            NDM = $('.entity_send-a-message a');
            NDM.attr({'href':NDM.attr('href')+'&ajax=1'});
            NDM.bind('click', function() {
                var NDMF = $('.entity_send-a-message form');
                if (NDMF.length === 0) {
                    $(this).addClass('processing');
                    $.get(NDM.attr('href'), null, function(data) {
                        $('.entity_send-a-message').append(document._importNode($('form', data)[0], true));
                        NDMF = $('.entity_send-a-message .form_notice');
                        SN.U.FormNoticeXHR(NDMF);
                        SN.U.FormNoticeEnhancements(NDMF);
                        NDMF.append('<button class="close">&#215;</button>');
                        $('.entity_send-a-message button').click(function(){
                            NDMF.hide();
                            return false;
                        });
                        NDM.removeClass('processing');
                    });
                }
                else {
                    NDMF.show();
                    $('.entity_send-a-message textarea').focus();
                }
                return false;
            });
        }
    },

    Init: {
        NoticeForm: function() {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeLocationAttach();

                $('.'+SN.C.S.FormNotice).each(function() {
                    SN.U.FormNoticeXHR($(this));
                    SN.U.FormNoticeEnhancements($(this));
                });

                SN.U.NoticeDataAttach();
            }
        },

        Notices: function() {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeFavor();
                SN.U.NoticeRepeat();
                SN.U.NoticeReply();
            }

            SN.U.NoticeAttachments();
        },

        EntityActions: function() {
            if ($('body.user_in').length > 0) {
                $('.form_user_subscribe').each(function() { SN.U.FormXHR($(this)); });
                $('.form_user_unsubscribe').each(function() { SN.U.FormXHR($(this)); });
                $('.form_group_join').each(function() { SN.U.FormXHR($(this)); });
                $('.form_group_leave').each(function() { SN.U.FormXHR($(this)); });
                $('.form_user_nudge').each(function() { SN.U.FormXHR($(this)); });

                SN.U.NewDirectMessage();
            }
        }
    }
};

$(document).ready(function(){
    if ($('.'+SN.C.S.FormNotice).length > 0) {
        SN.Init.NoticeForm();
    }
    if ($('#content .notices').length > 0) {
        SN.Init.Notices();
    }
    if ($('#content .entity_actions').length > 0) {
        SN.Init.EntityActions();
    }
});

