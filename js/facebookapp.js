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

var max = 140;
var noticeBox = document.getElementById('notice_data-text'); 

if (noticeBox) {
    noticeBox.addEventListener('keyup', keypress);
    noticeBox.addEventListener('keydown', keypress);
    noticeBox.addEventListener('keypress', keypress);
    noticeBox.addEventListener('change', keypress);
}

// Do our the countdown
function keypress(evt) {  
    document.getElementById('notice_text-count').setTextValue(
        max - noticeBox.getValue().length);      
}
