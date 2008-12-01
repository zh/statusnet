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
	// count character on keyup
	function counter(event){
		var maxLength = 140;
		var currentLength = $("#status_textarea").val().length;
		var remaining = maxLength - currentLength;
		var counter = $("#counter");
		counter.text(remaining);
		
		if (remaining <= 0) {
			$("#status_form").addClass("response_error");
		} else {
			$("#status_form").removeClass("response_error");
		}
	}

	function submitonreturn(event) {
		if (event.keyCode == 13) {
			$("#status_form").submit();
			event.preventDefault();
			event.stopPropagation();
			return false;
		}
		return true;
	}

	if ($("#status_textarea").length) {
		$("#status_textarea").bind("keyup", counter);
		$("#status_textarea").bind("keydown", submitonreturn);
		
		// run once in case there's something in there
		counter();
		
		// set the focus
		$("#status_textarea").focus();
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

	function addAjaxHidden() {
		var ajax = document.createElement('input');
		ajax.setAttribute('type', 'hidden');
		ajax.setAttribute('name', 'ajax');
		ajax.setAttribute('value', 1);
		this.appendChild(ajax);
	}

	$("form.favor").ajaxForm(favoptions);
	$("form.disfavor").ajaxForm(disoptions);
	$("form.favor").each(addAjaxHidden);
	$("form.disfavor").each(addAjaxHidden);

	$("#nudge").ajaxForm ({ dataType: 'xml',
							success: function(xml) { $("#nudge").replaceWith(document._importNode($("#nudge_response", xml).get(0),true)); }
						 });
	$("#nudge").each(addAjaxHidden);
	$("#nudge .submit").bind('click', function(e) {	$(this).addClass("processing"); });


	var Subscribe = { dataType: 'xml',
					  success: function(xml) { var form_unsubscribe = document._importNode($('form', xml).get(0), true);
										  	   var form_unsubscribe_id = form_unsubscribe.id;
											   var form_subscribe_id = form_unsubscribe_id.replace('unsubscribe', 'subscribe');
											   $("form#"+form_subscribe_id).replaceWith(form_unsubscribe);
											   $("form#"+form_unsubscribe_id).ajaxForm(UnSubscribe).each(addAjaxHidden);
											   $("dd.subscribers").text(parseInt($("dd.subscribers").text())+1);
										     }
					};

	var UnSubscribe = { dataType: 'xml',
					    success: function(xml) { var form_subscribe = document._importNode($('form', xml).get(0), true);
										  		 var form_subscribe_id = form_subscribe.id;
												 var form_unsubscribe_id = form_subscribe_id.replace('subscribe', 'unsubscribe');
												 $("form#"+form_unsubscribe_id).replaceWith(form_subscribe);
												 $("form#"+form_subscribe_id).ajaxForm(Subscribe).each(addAjaxHidden);
												 $("#profile_send_a_new_message").remove();
												 $("#profile_nudge").remove();
											     $("dd.subscribers").text(parseInt($("dd.subscribers").text())-1);
											   }
					  };

	$("form.subscribe").ajaxForm(Subscribe);
	$("form.unsubscribe").ajaxForm(UnSubscribe);
	$("form.subscribe").each(addAjaxHidden);
	$("form.unsubscribe").each(addAjaxHidden);


	var PostNotice = { dataType: 'xml',
					   beforeSubmit: function(formData, jqForm, options) { if ($("#status_textarea").get(0).value.length == 0) {
																				$("#status_form").addClass("response_error");
																				return false;
																		   }
																		   $("#status_form input[type=submit]").attr("disabled", "disabled");
																		   $("#status_form input[type=submit]").addClass("disabled");
																		   return true;
												 						 },
					   success: function(xml) {	if ($(".error", xml).length > 0) {
													var response_error = document._importNode($(".error", xml).get(0), true);
													response_error = response_error.textContent || response_error.innerHTML;
													alert(response_error);
												}
												else {
													$("#notices").prepend(document._importNode($("li", xml).get(0), true));
													$("#status_textarea").val("");
													counter();
													$(".notice_single:first").css({display:"none"});
													$(".notice_single:first").fadeIn(2500);
												}
												$("#status_form input[type=submit]").removeAttr("disabled");
												$("#status_form input[type=submit]").removeClass("disabled");
											 }
					   }
	$("#status_form").ajaxForm(PostNotice);
	$("#status_form").each(addAjaxHidden);
});

function doreply(nick,id) {
	rgx_username = /^[0-9a-zA-Z\-_.]*$/;
	if (nick.match(rgx_username)) {
		replyto = "@" + nick + " ";
		if ($("#status_textarea").length) {
			$("#status_textarea").val(replyto);
			$("form#status_form input#inreplyto").val(id);
			$("#status_textarea").focus();
			return false;
		}
	}
	return true;
}
