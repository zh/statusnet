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

require_once INSTALLDIR.'/extlib/facebook/facebook.php';
require_once INSTALLDIR.'/lib/noticelist.php';

define("FACEBOOK_SERVICE", 2); // Facebook is foreign_service ID 2
define("FACEBOOK_NOTICE_PREFIX", 1);
define("FACEBOOK_PROMPTED_UPDATE_PREF", 2);

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

    $item = new FacebookNoticeListItem($notice);
    $item->show();

    $fbml = "<fb:wide>$style " . $xw->outputMemory(false) . "</fb:wide>";
    $fbml .= "<fb:narrow>$style " . $xw->outputMemory(false) . "</fb:narrow>";

    $fbml_main = "<fb:narrow>$style " . $xw->outputMemory(false) . "</fb:narrow>";

    $facebook->api_client->profile_setFBML(null, $fbuid, $fbml, null, null, $fbml_main);
}

function getFacebookBaseCSS()
{
    # Add a timestamp to the CSS file so Facebook cache wont ignore our changes
    $ts = filemtime(theme_file('facebookapp_base.css'));
    $cssurl = theme_path('facebookapp_base.css') . "?ts=$ts";
    return $cssurl;
}

function getFacebookThemeCSS() 
{
    # Add a timestamp to the CSS file so Facebook cache wont ignore our changes
    $ts = filemtime(theme_file('facebookapp_theme.css'));
    $cssurl = theme_path('facebookapp_theme.css') . "?ts=$ts";
    return $cssurl;   
}

function getFacebookJS() {

    # Add a timestamp to the FBJS file so Facebook cache wont ignore our changes
    $ts = filemtime(INSTALLDIR.'/js/facebookapp.js');
    $jsurl = common_path('js/facebookapp.js') . "?ts=$ts";
    return $jsurl;
}


class FacebookNoticeList extends NoticeList
{
    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */

    function show()
    {
        common_element_start('div', array('id' =>'notices_primary'));
        common_element('h2', null, _('Notices'));
        common_element_start('ul', array('class' => 'notices'));

        $cnt = 0;

        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $item = $this->newListItem($this->notice);
            $item->show();
        }

        common_element_end('ul');
        common_element_end('div');

        return $cnt;
    }

    /**
     * returns a new list item for the current notice
     *
     * Overridden to return a Facebook specific list item.
     *
     * @param Notice $notice the current notice
     *
     * @return FacebookNoticeListItem a list item for displaying the notice
     * formatted for display in the Facebook App.
     */

    function newListItem($notice)
    {
        return new FacebookNoticeListItem($notice);
    }

}

class FacebookNoticeListItem extends NoticeListItem
{
    /**
     * recipe function for displaying a single notice in the Facebook App.
     *
     * Overridden to strip out some of the controls that we don't
     * want to be available.
     *
     * @return void
     */

    function show()
    {
        $this->showStart();

        common_element_start('div', 'entry-title');
        $this->showAuthor();
        $this->showContent();
        common_element_end('div');

        common_element_start('div', 'entry-content');
        $this->showNoticeLink();
        $this->showNoticeSource();
        $this->showReplyTo();
        common_element_end('div');

        $this->showEnd();
    }

    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        common_element_start('li', array('class' => 'hentry notice',
                                         'id' => 'notice-' . $this->notice->id));
    }

    function showNoticeLink()
    {
        $noticeurl = common_local_url('shownotice',
                                      array('notice' => $this->notice->id));
        // XXX: we need to figure this out better. Is this right?
        if (strcmp($this->notice->uri, $noticeurl) != 0 &&
            preg_match('/^http/', $this->notice->uri)) {
            $noticeurl = $this->notice->uri;
        }

        common_element_start('dl', 'timestamp');
        common_element('dt', null, _('Published'));
        common_element_start('dd', null);
        common_element_start('a', array('rel' => 'bookmark',
                                        'href' => $noticeurl));
        $dt = common_date_iso8601($this->notice->created);
        common_element('abbr', array('class' => 'published',
                                     'title' => $dt),
        common_date_string($this->notice->created));
        common_element_end('a');
        common_element_end('dd');
        common_element_end('dl');
    }

}

