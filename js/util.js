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
		var maxLength = 140;
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

	if ($("#notice_data-text").length) {
		$("#notice_data-text").bind("keyup", counter);
		$("#notice_data-text").bind("keydown", submitonreturn);

		// run once in case there's something in there
		counter();

        if($('body')[0].id != 'conversation') {
            $("#notice_data-text").focus();
        }
	}

	// XXX: refactor this code

	var joinoptions = { dataType: 'xml',
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var leave = new_form.id;
												var join = leave.replace('leave', 'join');
												$('form#'+join).replaceWith(new_form);
												$('form#'+leave).ajaxForm(leaveoptions).each(addAjaxHidden);
											  }
					 };

	var leaveoptions = { dataType: 'xml',
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var join = new_form.id;
												var leave = join.replace('join', 'leave');
												$('form#'+leave).replaceWith(new_form);
												$('form#'+join).ajaxForm(joinoptions).each(addAjaxHidden);
											  }
					 };

	$("form.form_group_join").ajaxForm(joinoptions);
	$("form.form_group_leave").ajaxForm(leaveoptions);
	$("form.form_group_join").each(addAjaxHidden);
	$("form.form_group_leave").each(addAjaxHidden);

	$("#form_user_nudge").ajaxForm ({ dataType: 'xml',
		beforeSubmit: function(xml) { $("#form_user_nudge input[type=submit]").attr("disabled", "disabled");
									  $("#form_user_nudge input[type=submit]").addClass("disabled");
									},
		success: function(xml) { $("#form_user_nudge").replaceWith(document._importNode($("#nudge_response", xml).get(0),true));
							     $("#form_user_nudge input[type=submit]").removeAttr("disabled");
							     $("#form_user_nudge input[type=submit]").removeClass("disabled");
							   }
	 });
	$("#form_user_nudge").each(addAjaxHidden);

	var Subscribe = { dataType: 'xml',
					  beforeSubmit: function(formData, jqForm, options) { $(".form_user_subscribe input[type=submit]").attr("disabled", "disabled");
																	      $(".form_user_subscribe input[type=submit]").addClass("disabled");
																	    },
					  success: function(xml) { var form_unsubscribe = document._importNode($('form', xml).get(0), true);
										  	   var form_unsubscribe_id = form_unsubscribe.id;
											   var form_subscribe_id = form_unsubscribe_id.replace('unsubscribe', 'subscribe');
											   $("form#"+form_subscribe_id).replaceWith(form_unsubscribe);
											   $("form#"+form_unsubscribe_id).ajaxForm(UnSubscribe).each(addAjaxHidden);
											   $("dd.subscribers").text(parseInt($("dd.subscribers").text())+1);
											   $(".form_user_subscribe input[type=submit]").removeAttr("disabled");
											   $(".form_user_subscribe input[type=submit]").removeClass("disabled");
										     }
					};

	var UnSubscribe = { dataType: 'xml',
						beforeSubmit: function(formData, jqForm, options) { $(".form_user_unsubscribe input[type=submit]").attr("disabled", "disabled");
																		    $(".form_user_unsubscribe input[type=submit]").addClass("disabled");
																		  },
					    success: function(xml) { var form_subscribe = document._importNode($('form', xml).get(0), true);
										  		 var form_subscribe_id = form_subscribe.id;
												 var form_unsubscribe_id = form_subscribe_id.replace('subscribe', 'unsubscribe');
												 $("form#"+form_unsubscribe_id).replaceWith(form_subscribe);
												 $("form#"+form_subscribe_id).ajaxForm(Subscribe).each(addAjaxHidden);
												 $("#profile_send_a_new_message").remove();
												 $("#profile_nudge").remove();
											     $("dd.subscribers").text(parseInt($("dd.subscribers").text())-1);
												 $(".form_user_unsubscribe input[type=submit]").removeAttr("disabled");
												 $(".form_user_unsubscribe input[type=submit]").removeClass("disabled");
											   }
					  };

	$(".form_user_subscribe").ajaxForm(Subscribe);
	$(".form_user_unsubscribe").ajaxForm(UnSubscribe);
	$(".form_user_subscribe").each(addAjaxHidden);
	$(".form_user_unsubscribe").each(addAjaxHidden);

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
																					counter();
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
                                                            NoticeFavors();
                                                         }
													}
													$("#notice_data-text").val("");
    												$("#notice_data-attach").val("");
    												$("#notice_in-reply-to").val("");
                                                    $('#notice_data-attach_selected').remove();
                                                    counter();
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
    NoticeFavors();
});

function addAjaxHidden() {
	var ajax = document.createElement('input');
	ajax.setAttribute('type', 'hidden');
	ajax.setAttribute('name', 'ajax');
	ajax.setAttribute('value', 1);
	this.appendChild(ajax);
}

function NoticeFavors() {

	// XXX: refactor this code
	var favoptions = { dataType: 'xml',
					   beforeSubmit: function(data, target, options) {
					   							$(target).addClass('processing');
												return true;
											  },
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var dis = new_form.id;
												var fav = dis.replace('disfavor', 'favor');
												$('form#'+fav).replaceWith(new_form);
												$('form#'+dis).ajaxForm(disoptions).each(addAjaxHidden);
											  }
					 };

	var disoptions = { dataType: 'xml',
					   beforeSubmit: function(data, target, options) {
					   							$(target).addClass('processing');
												return true;
											  },
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var fav = new_form.id;
												var dis = fav.replace('favor', 'disfavor');
												$('form#'+dis).replaceWith(new_form);
												$('form#'+fav).ajaxForm(favoptions).each(addAjaxHidden);
											  }
					 };

	$("form.form_favor").ajaxForm(favoptions);
	$("form.form_disfavor").ajaxForm(disoptions);
	$("form.form_favor").each(addAjaxHidden);
	$("form.form_disfavor").each(addAjaxHidden);
}

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
