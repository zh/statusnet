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
            NoticeLocationNs: 'notice_data-location_ns'
        }
    },

    U: { // Utils
        FormNoticeEnhancements: function(form) {
            form_id = form.attr('id');
            $('#'+form_id+' #'+SN.C.S.NoticeDataText).unbind('keyup');
            $('#'+form_id+' #'+SN.C.S.NoticeDataText).unbind('keydown');
            if (maxLength > 0) {
                $('#'+form_id+' #'+SN.C.S.NoticeDataText).bind('keyup', function(e) {
                    SN.U.Counter(form);
                });
                // run once in case there's something in there
                SN.U.Counter(form);
            }

            $('#'+form_id+' #'+SN.C.S.NoticeDataText).bind('keydown', function(e) {
                SN.U.SubmitOnReturn(e, form);
            });

            if($('body')[0].id != 'conversation') {
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
            if (typeof(maxLength) == "undefined") {
                 maxLength = SN.C.I.MaxLength;
            }

            if (maxLength <= 0) {
                return;
            }

            var remaining = maxLength - $('#'+form_id+' #'+SN.C.S.NoticeDataText).val().length;
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
        },

        FormNoticeXHR: function(form) {
            form_id = form.attr('id');
            form.append('<input type="hidden" name="ajax" value="1"/>');
            form.ajaxForm({
                dataType: 'xml',
                timeout: '60000',
                beforeSend: function(xhr) {
                    if ($('#'+form_id+' #'+SN.C.S.NoticeDataText)[0].value.length === 0) {
                        form.addClass(SN.C.S.Warning);
                        return false;
                    }
                    form.addClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).addClass(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).attr(SN.C.S.Disabled, SN.C.S.Disabled);
                    return true;
                },
                error: function (xhr, textStatus, errorThrown) {
                    form.removeClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeClass(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeAttr(SN.C.S.Disabled, SN.C.S.Disabled);
                    if (textStatus == 'timeout') {
                        alert ('Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists');
                    }
                    else {
                        if ($('.'+SN.C.S.Error, xhr.responseXML).length > 0) {
                            form.append(document._importNode($('.'+SN.C.S.Error, xhr.responseXML)[0], true));
                        }
                        else {
                            if(jQuery.inArray(parseInt(xhr.status), SN.C.I.HTTP20x30x) < 0) {
                                alert('Sorry! We had trouble sending your notice ('+xhr.status+' '+xhr.statusText+'). Please report the problem to the site administrator if this happens again.');
                            }
                            else {
                                $('#'+form_id+' #'+SN.C.S.NoticeDataText).val('');
                                SN.U.FormNoticeEnhancements($('#'+form_id));
                            }
                        }
                    }
                },
                success: function(data, textStatus) {
                    var result;
                    if ($('#'+SN.C.S.Error, data).length > 0) {
                        result = document._importNode($('p', data)[0], true);
                        alert(result.textContent || result.innerHTML);
                    }
                    else {
                        if($('body')[0].id == 'bookmarklet') {
                            self.close();
                        }

                        if ($('#'+SN.C.S.CommandResult, data).length > 0) {
                            result = document._importNode($('p', data)[0], true);
                            alert(result.textContent || result.innerHTML);
                        }
                        else {
                             notice = document._importNode($('li', data)[0], true);
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
                                    $("#notices_primary .notices").prepend(notice);
                                }
                                $('#'+notice.id).css({display:'none'});
                                $('#'+notice.id).fadeIn(2500);
                                SN.U.NoticeAttachments();
                                SN.U.NoticeReply();
                                SN.U.NoticeFavor();
                             }
                        }
                        $('#'+form_id+' #'+SN.C.S.NoticeDataText).val('');
                        $('#'+form_id+' #'+SN.C.S.NoticeDataAttach).val('');
                        $('#'+form_id+' #'+SN.C.S.NoticeInReplyTo).val('');
                        $('#'+form_id+' #'+SN.C.S.NoticeDataAttachSelected).remove();
                        SN.U.FormNoticeEnhancements($('#'+form_id));
                    }
                },
                complete: function(xhr, textStatus) {
                    form.removeClass(SN.C.S.Processing);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeAttr(SN.C.S.Disabled);
                    $('#'+form_id+' #'+SN.C.S.NoticeActionSubmit).removeClass(SN.C.S.Disabled);
                }
            });
        },

        NoticeReply: function() {
            if ($('#'+SN.C.S.NoticeDataText).length > 0 && $('#content .notice_reply').length > 0) {
                $('#content .notice').each(function() {
                    var notice = $(this)[0];
                    $($('.notice_reply', notice)[0]).click(function() {
                        var nickname = ($('.author .nickname', notice).length > 0) ? $($('.author .nickname', notice)[0]) : $('.author .nickname.uid');
                        SN.U.NoticeReplySet(nickname.text(), $($('.notice_id', notice)[0]).text());
                        return false;
                    });
                });
            }
        },

        NoticeReplySet: function(nick,id) {
            if (nick.match(SN.C.I.PatternUsername)) {
                var text = $('#'+SN.C.S.NoticeDataText);
                if (text.length) {
                    replyto = '@' + nick + ' ';
                    text.val(replyto + text.val().replace(RegExp(replyto, 'i'), ''));
                    $('#'+SN.C.S.FormNotice+' input#'+SN.C.S.NoticeInReplyTo).val(id);
                    if (text[0].setSelectionRange) {
                        var len = text.val().length;
                        text[0].setSelectionRange(len,len);
                        text[0].focus();
                    }
                    return false;
                }
            }
            return true;
        },

        NoticeFavor: function() {
            $('.form_favor').each(function() { SN.U.FormXHR($(this)); });
            $('.form_disfavor').each(function() { SN.U.FormXHR($(this)); });
        },

        NoticeAttachments: function() {
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

            $('#content .notice a.attachment').click(function() {
                $().jOverlay({url: $('address .url')[0].href+'attachment/' + ($(this).attr('id').substring('attachment'.length + 1)) + '/ajax'});
                return false;
            });

            var t;
            $("body:not(#shownotice) #content .notice a.thumbnail").hover(
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
                });
            });
        },

        NoticeLocationAttach: function() {
            if(navigator.geolocation) navigator.geolocation.watchPosition(function(position) {
                $('#'+SN.C.S.NoticeLat).val(position.coords.latitude);
                $('#'+SN.C.S.NoticeLon).val(position.coords.longitude);
            });
        },

        NewDirectMessage: function() {
            NDM = $('.entity_send-a-message a');
            NDM.attr({'href':NDM.attr('href')+'&ajax=1'});
            NDM.bind('click', function() {
                $(this).addClass('processing');
                var NDMF = $('.entity_send-a-message form');
                if (NDMF.length === 0) {
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
                    });
                }
                else {
                    NDMF.show();
                    $('.entity_send-a-message textarea').focus();
                }

                $(this).removeClass('processing');
                return false;
            });
        }
    },

    Init: {
        NoticeForm: function() {
            if ($('body.user_in').length > 0) {
                $('.'+SN.C.S.FormNotice).each(function() {
                    SN.U.FormNoticeXHR($(this));
                    SN.U.FormNoticeEnhancements($(this));
                });

                SN.U.NoticeDataAttach();
                SN.U.NoticeLocationAttach();
            }
        },

        Notices: function() {
            if ($('body.user_in').length > 0) {
                SN.U.NoticeFavor();

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

