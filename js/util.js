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
             if (event.keyCode == 13) {
                  $("#status_form").submit();
             }
             
            var maxLength     = 140;
            var currentLength = $("#status_textarea").val().length;
            var remaining = maxLength - currentLength;
            var counter   = $("#counter");
            counter.text(remaining);

            if (remaining <= 0) {
                counter.attr("class", "toomuch");
            } else {
                counter.attr("class", "");
            }
        }
     
        $("#status_textarea").bind("keyup", counter);
     
        if ($("#status_textarea").length) {
            // run once in case there's something in there
            counter();
        }
});

function doreply(nick) {
     rgx_username = /^[0-9a-zA-Z\-_.]*$/;
     if (nick.match(rgx_username)) {
          replyto = "@" + nick + " ";
          if ($("#status_textarea")) {
               $("#status_textarea").val(replyto);
               $("#status_textarea").focus();
			   return false;
		  }
     }
     return true;
}

