<?php
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

require_once(INSTALLDIR.'/extlib/facebook/facebook.php');
require_once(INSTALLDIR.'/lib/noticelist.php');

define("FACEBOOK_SERVICE", 2); // Facebook is foreign_service ID 2

// Gets all the notices from users with a Facebook link since a given ID
function get_facebook_notices($since)
{
    $qry = 'SELECT notice.* ' .
        'FROM notice ' .
        'JOIN foreign_link ' .
        'WHERE notice.profile_id = foreign_link.user_id ' .
        'AND foreign_link.service = 2';

    // XXX: What should the limit be?
    return Notice::getStreamDirect($qry, 0, 100, 0, 0, null, $since);
}

function get_facebook()
{
    $apikey = common_config('facebook', 'apikey');
    $secret = common_config('facebook', 'secret');
    return new Facebook($apikey, $secret);
}

function start_fbml($indent = true)
{
    global $xw;
    $xw = new XMLWriter();
    $xw->openURI('php://output');
    $xw->setIndent($indent);
}

function update_profile_box($facebook, $fbuid, $user, $notice)
{

    // Need to include inline CSS for styling the Profile box

    $style = '<style>
     #notices {
         clear: both;
         margin: 0 auto;
         padding: 0;
         list-style-type: none;
         width: 600px;
         border-top: 1px solid #dec5b5;
     }
     #notices a:hover {
         text-decoration: underline;
     }
     .notice_single {
         clear: both;
         display: block;
         margin: 0;
         padding: 5px 5px 5px 0;
         min-height: 48px;
         font-family: Georgia, "Times New Roman", Times, serif;
         font-size: 13px;
         line-height: 16px;
         border-bottom: 1px solid #dec5b5;
         background-color:#FCFFF5;
         opacity:1;
     }
     .notice_single:hover {
         background-color: #f7ebcc;
     }
     .notice_single p {
         display: inline;
         margin: 0;
         padding: 0;
     }
     </style>';

    global $xw;
    $xw = new XMLWriter();
    $xw->openMemory();

    $item = new NoticeListItem($notice);
    $item->show();

    $fbml = "<fb:wide>$style " . $xw->outputMemory(false) . "</fb:wide>";
    $fbml .= "<fb:narrow>$style " . $xw->outputMemory(false) . "</fb:narrow>";

    $fbml_main = "<fb:narrow>$style " . $xw->outputMemory(false) . "</fb:narrow>";

    $facebook->api_client->profile_setFBML(null, $fbuid, $fbml, null, null, $fbml_main);
}
