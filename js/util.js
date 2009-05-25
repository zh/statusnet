/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
// attachments and attachment pages not used at the moment except for attachment_ajax version
//    $('.attachments').click(function() {$().jOverlay({zIndex:999, success:function(html) {$('.attachment').click(function() {$().jOverlay({url:$(this).attr('href') + '/ajax'}); return false; });
//        }, url:$(this).attr('href') + '/ajax'}); return false; });

    //FIXME
    //need to link to proper url depending on site config (path name and theme, for instance)
    $('a.attachment').click(function() {$().jOverlay({url:'/attachment/' + ($(this).attr('id').substring('attachment'.length + 1)) + '/ajax'}); return false; });
    $('.entry-title a.attachment').append('&nbsp;<img style="display: inline; vertical-align: middle" src="/theme/base/images/icons/clip-inline.png" alt="Attachment" />');

	// count character on keyup
	function counter(event){
		var maxLength = 140;
		var currentLength = $("#notice_data-text").val().length;
		var remaining = maxLength - currentLength;
		var counter = $("#notice_text-count");
		counter.text(remaining);

		if (remaining <= 0) {
			$("#form_notice").addClass("warning");
		} else {
			$("#form_notice").removeClass("warning");
		}
	}

	function submitonreturn(event) {
		if (event.keyCode == 13) {
			$("#form_notice").submit();
			event.preventDefault();
			event.stopPropagation();
			return false;
		}
		return true;
	}

	if ($("#notice_data-text").length) {
		$("#notice_data-text").bind("keyup", counter);
		$("#notice_data-text").bind("keydown", submitonreturn);

		// run once in case there's something in there
		counter();

		// set the focus
		$("#notice_data-text").focus();
	}

	// XXX: refactor this code

	var favoptions = { dataType: 'xml',
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var dis = new_form.id;
												var fav = dis.replace('disfavor', 'favor');
												$('form#'+fav).replaceWith(new_form);
												$('form#'+dis).ajaxForm(disoptions).each(addAjaxHidden);
											  }
					 };

	var disoptions = { dataType: 'xml',
					   success: function(xml) { var new_form = document._importNode($('form', xml).get(0), true);
												var fav = new_form.id;
												var dis = fav.replace('favor', 'disfavor');
												$('form#'+dis).replaceWith(new_form);
												$('form#'+fav).ajaxForm(favoptions).each(addAjaxHidden);
											  }
					 };

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

	function addAjaxHidden() {
		var ajax = document.createElement('input');
		ajax.setAttribute('type', 'hidden');
		ajax.setAttribute('name', 'ajax');
		ajax.setAttribute('value', 1);
		this.appendChild(ajax);
	}

	$("form.form_favor").ajaxForm(favoptions);
	$("form.form_disfavor").ajaxForm(disoptions);
	$("form.form_group_join").ajaxForm(joinoptions);
	$("form.form_group_leave").ajaxForm(leaveoptions);
	$("form.form_favor").each(addAjaxHidden);
	$("form.form_disfavor").each(addAjaxHidden);
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
																				alert("Sorry! We had trouble sending your notice ("+xhr.status+" "+xhr.statusText+"). Please report the problem to the site administrator if this happens again.");
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
                                                              $("#notices_primary .notices").prepend(document._importNode(li, true));
                                                              $("#notices_primary .notice:first").css({display:"none"});
                                                              $("#notices_primary .notice:first").fadeIn(2500);
                                                              NoticeReply();
                                                         }
													}
													$("#notice_data-text").val("");
                                                    counter();
												}
												$("#form_notice").removeClass("processing");
												$("#notice_action-submit").removeAttr("disabled");
												$("#notice_action-submit").removeClass("disabled");
											 }
					   };
	$("#form_notice").ajaxForm(PostNotice);
	$("#form_notice").each(addAjaxHidden);
    NoticeHover();
    NoticeReply();
});


function NoticeHover() {
    function mouseHandler(e) {
        $(e.target).closest('li.hentry')[(e.type === 'mouseover') ? 'addClass' : 'removeClass']('hover');
    };
    $('#content .notices').mouseover(mouseHandler);
    $('#content .notices').mouseout(mouseHandler);
}


function NoticeReply() {
    if ($('#notice_data-text').length > 0) {
        $('#content .notice').each(function() {
            var notice = $(this)[0];
            $($('.notice_reply', notice)[0]).click(function() {
                var nickname = ($('.author .nickname', notice).length > 0) ? $($('.author .nickname', notice)[0]) : $('.author .nickname');
                NoticeReplySet(nickname.text(), $($('.notice_id', notice)[0]).text());
                return false;
            });
        });
    }
}

function NoticeReplySet(nick,id) {
	rgx_username = /^[0-9a-zA-Z\-_.]*$/;
	if (nick.match(rgx_username)) {
		replyto = "@" + nick + " ";
		if ($("#notice_data-text").length) {
			$("#notice_data-text").val(replyto);
			$("#form_notice input#notice_in-reply-to").val(id);
			$("#notice_data-text").focus();
			return false;
		}
	}
	return true;
}
