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
 */

$(document).ready(function(){
	var counterBlackout = false;
	
	// count character on keyup
	function counter(event){
         if (maxLength <= 0) {
              return;
         }
		var currentLength = $("#notice_data-text").val().length;
		var remaining = maxLength - currentLength;
		var counter = $("#notice_text-count");
		
		if (remaining.toString() != counter.text()) {
		    if (!counterBlackout || remaining == 0) {
                        if (counter.text() != String(remaining)) {
                            counter.text(remaining);
		        }

                        if (remaining < 0) {
                            $("#form_notice").addClass("warning");
                        } else {
                            $("#form_notice").removeClass("warning");
                        }
                        // Skip updates for the next 500ms.
                        // On slower hardware, updating on every keypress is unpleasant.
                        if (!counterBlackout) {
                            counterBlackout = true;
                            window.setTimeout(clearCounterBlackout, 500);
                        }
                    }
                }
	}
	
	function clearCounterBlackout() {
		// Allow keyup events to poke the counter again
		counterBlackout = false;
		// Check if the string changed since we last looked
		counter(null);
	}

	function submitonreturn(event) {
		if (event.keyCode == 13 || event.keyCode == 10) {
			// iPhone sends \n not \r for 'return'
			$("#form_notice").submit();
			event.preventDefault();
			event.stopPropagation();
			$("#notice_data-text").blur();
			$("body").focus();
			return false;
		}
		return true;
	}

     // define maxLength if it wasn't defined already

    if (typeof(maxLength) == "undefined") {
         maxLength = 140;
    }

	if ($("#notice_data-text").length) {
         if (maxLength > 0) {
              $("#notice_data-text").bind("keyup", counter);
              // run once in case there's something in there
              counter();
         }

		$("#notice_data-text").bind("keydown", submitonreturn);

        if($('body')[0].id != 'conversation') {
            $("#notice_data-text").focus();
        }
	}

	function addAjaxHidden() {
		var ajax = document.createElement('input');
		ajax.setAttribute('type', 'hidden');
		ajax.setAttribute('name', 'ajax');
		ajax.setAttribute('value', 1);
		this.appendChild(ajax);
	}

    $('.form_user_subscribe').each(function() { SN.U.FormXHR($(this)); });
    $('.form_user_unsubscribe').each(function() { SN.U.FormXHR($(this)); });
    $('.form_favor').each(function() { SN.U.FormXHR($(this)); });
    $('.form_disfavor').each(function() { SN.U.FormXHR($(this)); });
    $('.form_group_join').each(function() { SN.U.FormXHR($(this)); });
    $('.form_group_leave').each(function() { SN.U.FormXHR($(this)); });
    $('.form_user_nudge').each(function() { SN.U.FormXHR($(this)); });

	var PostNotice = { dataType: 'xml',
					   beforeSubmit: function(formData, jqForm, options) { if ($("#notice_data-text").get(0).value.length == 0) {
																				$("#form_notice").addClass("warning");
																				return false;
																		   }
																		   $("#form_notice").addClass("processing");
																		   $("#notice_action-submit").attr("disabled", "disabled");
																		   $("#notice_action-submit").addClass("disabled");
																		   return true;
												 						 },
					   timeout: '60000',
					   error: function (xhr, textStatus, errorThrown) {	$("#form_notice").removeClass("processing");
																		$("#notice_action-submit").removeAttr("disabled");
																		$("#notice_action-submit").removeClass("disabled");
																		if (textStatus == "timeout") {
																			alert ("Sorry! We had trouble sending your notice. The servers are overloaded. Please try again, and contact the site administrator if this problem persists");
																		}
																		else {
																			if ($(".error", xhr.responseXML).length > 0) {
																				$('#form_notice').append(document._importNode($(".error", xhr.responseXML).get(0), true));
																			}
																			else {
																				var HTTP20x30x = [200, 201, 202, 203, 204, 205, 206, 300, 301, 302, 303, 304, 305, 306, 307];
																				if(jQuery.inArray(parseInt(xhr.status), HTTP20x30x) < 0) {
																					alert("Sorry! We had trouble sending your notice ("+xhr.status+" "+xhr.statusText+"). Please report the problem to the site administrator if this happens again.");
																				}
																				else {
																					$("#notice_data-text").val("");
                                                                                     if (maxLength > 0) {
                                                                                          counter();
                                                                                     }
																				}
																			}
																		}
																	  },
					   success: function(xml) {	if ($("#error", xml).length > 0) {
													var result = document._importNode($("p", xml).get(0), true);
													result = result.textContent || result.innerHTML;
													alert(result);
												}
												else {
												    if($('body')[0].id == 'bookmarklet') {
												        self.close();
												    }
												    if ($("#command_result", xml).length > 0) {
													    var result = document._importNode($("p", xml).get(0), true);
													    result = result.textContent || result.innerHTML;
													    alert(result);
                                                    }
                                                    else {
                                                         li = $("li", xml).get(0);
                                                         if ($("#"+li.id).length == 0) {
                                                            var notice_irt_value = $('#notice_in-reply-to').val();
                                                            var notice_irt = '#notices_primary #notice-'+notice_irt_value;
                                                            if($('body')[0].id == 'conversation') {
                                                                if(notice_irt_value.length > 0 && $(notice_irt+' .notices').length < 1) {
                                                                    $(notice_irt).append('<ul class="notices"></ul>');
                                                                }
                                                                $($(notice_irt+' .notices')[0]).append(document._importNode(li, true));
                                                            }
                                                            else {
                                                                $("#notices_primary .notices").prepend(document._importNode(li, true));
                                                            }
                                                            $('#'+li.id).css({display:'none'});
                                                            $('#'+li.id).fadeIn(2500);
                                                            NoticeReply();
                                                            NoticeAttachments();
                                                         }
													}
													$("#notice_data-text").val("");
    												$("#notice_data-attach").val("");
    												$("#notice_in-reply-to").val("");
                                                    $('#notice_data-attach_selected').remove();
                                                     if (maxLength > 0) {
                                                          counter();
                                                     }
												}
												$("#form_notice").removeClass("processing");
												$("#notice_action-submit").removeAttr("disabled");
												$("#notice_action-submit").removeClass("disabled");
											 }
					   };
	$("#form_notice").ajaxForm(PostNotice);
	$("#form_notice").each(addAjaxHidden);
    NoticeReply();
    NoticeAttachments();
    NoticeDataAttach();
});

function NoticeReply() {
    if ($('#notice_data-text').length > 0 && $('#content .notice_reply').length > 0) {
        $('#content .notice').each(function() {
            var notice = $(this)[0];
            $($('.notice_reply', notice)[0]).click(function() {
                var nickname = ($('.author .nickname', notice).length > 0) ? $($('.author .nickname', notice)[0]) : $('.author .nickname.uid');
                NoticeReplySet(nickname.text(), $($('.notice_id', notice)[0]).text());
                return false;
            });
        });
    }
}

function NoticeReplySet(nick,id) {
	rgx_username = /^[0-9a-zA-Z\-_.]*$/;
	if (nick.match(rgx_username)) {
		var text = $("#notice_data-text");
		if (text.length) {
			replyto = "@" + nick + " ";
			text.val(replyto + text.val().replace(RegExp(replyto, 'i'), ''));
			$("#form_notice input#notice_in-reply-to").val(id);
			if (text.get(0).setSelectionRange) {
				var len = text.val().length;
				text.get(0).setSelectionRange(len,len);
				text.get(0).focus();
			}
			return false;
		}
	}
	return true;
}

function NoticeAttachments() {
    $.fn.jOverlay.options = {
        method : 'GET',
        data : '',
        url : '',
        color : '#000',
        opacity : '0.6',
        zIndex : 99,
        center : false,
        imgLoading : $('address .url')[0].href+'theme/base/images/illustrations/illu_progress_loading-01.gif',
        bgClickToClose : true,
        success : function() {
            $('#jOverlayContent').append('<button>&#215;</button>');
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

            if (anchor.children('img').length == 0) {
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
}

function NoticeDataAttach() {
    NDA = $('#notice_data-attach');
    NDA.change(function() {
        S = '<div id="notice_data-attach_selected" class="success"><code>'+$(this).val()+'</code> <button>&#215;</button></div>';
        NDAS = $('#notice_data-attach_selected');
        (NDAS.length > 0) ? NDAS.replaceWith(S) : $('#form_notice').append(S);
        $('#notice_data-attach_selected button').click(function(){
            $('#notice_data-attach_selected').remove();
            NDA.val('');
        });
    });
}

var SN = { // StatusNet
    C: { // Config
        S: { // Selector
            Disabled: 'disabled',
            Warning: 'warning',
            Error: 'error',
            Processing: 'processing'
        }
    },

    U: { // Utils
        FormXHR: function(f) {
            f.bind('submit', function(e) {
                form_id = $(this)[0].id;
                $.ajax({
                    type: 'POST',
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
                        if ($('form', data)[0].length > 0) {
                            form_new = $('form', data)[0];
                            $('#'+form_id).replaceWith(document._importNode(form_new, true));
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
    }
}
