<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * 
 * Handler for queue items of type 'usersum', sends an email summaries
 * to a particular user.
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
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Handler for queue items of type 'usersum', sends an email summaries
 * to a particular user.
 *
 * @category  Email
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class UserEmailSummaryHandler extends QueueHandler
{
    // Maximum number of notices to include by default. This is probably too much.
    
    const MAX_NOTICES = 200;
    
    /**
     * Return transport keyword which identifies items this queue handler
     * services; must be defined for all subclasses.
     *
     * Must be 8 characters or less to fit in the queue_item database.
     * ex "email", "jabber", "sms", "irc", ...
     *
     * @return string
     */
    
    function transport()
    {
        return 'sitesum';
    }

    /**
     * Send a summary email to the user
     * 
     * @param mixed $object
     * @return boolean true on success, false on failure
     */
    
    function handle($user_id)
    {
	// Skip if they've asked not to get summaries

	$ess = Email_summary_status::staticGet('user_id', $user_id);
	
	if (!empty($ess) && !$ess->send_summary) {
	    common_log(LOG_INFO, sprintf('Not sending email summary for user %s by request.', $user_id));
	    return true;
	}

	$since_id = null;
	
	if (!empty($ess)) {
	    $since_id = $ess->last_summary_id;
	}
	  
	$user = User::staticGet('id', $user_id);

	if (empty($user)) {
	    common_log(LOG_INFO, sprintf('Not sending email summary for user %s; no such user.', $user_id));
	    return true;
	}
	
	if (empty($user->email)) {
	    common_log(LOG_INFO, sprintf('Not sending email summary for user %s; no email address.', $user_id));
	    return true;
	}
	
	$profile = $user->getProfile();
	
	if (empty($profile)) {
	    common_log(LOG_WARNING, sprintf('Not sending email summary for user %s; no profile.', $user_id));
	    return true;
	}
	
	$notice = $user->ownFriendsTimeline(0, self::MAX_NOTICES, $since_id);

	if (empty($notice) || $notice->N == 0) {
	    common_log(LOG_WARNING, sprintf('Not sending email summary for user %s; no notices.', $user_id));
	    return true;
	}

	// XXX: This is risky fingerpoken in der objektvars, but I didn't feel like
	// figuring out a better way. -ESP

	$new_top = null;
	
	if ($notice instanceof ArrayWrapper) {
	    $new_top = $notice->_items[0]->id;
	}
	
	$out = new XMLStringer();

	$out->raw('<style>'.$this->stylesheet().'</style>');
	
	$out->raw(sprintf(_('<p>Recent updates from %1s for %2s:</p>'),
			  common_config('site', 'name'),
			  $profile->getBestName()));
	
	$nl = new NoticeList($notice, $out);

	// Outputs to the string
	
	$nl->show();
	
	$out->raw(sprintf(_('<p><a href="%1s">change your email settings for %2s</a></p>'),
			  common_local_url('emailsettings'),
			  common_config('site', 'name')));

	$body = $out->getString();
	
	// FIXME: do something for people who don't like HTML email
	
	mail_to_user($user, _('Updates from your network'), $body,
		     array('Content-Type' => 'text/html; charset=UTF-8'));

	if (empty($ess)) {
	    
	    $ess = new Email_summary_status();
	    
	    $ess->user_id         = $user_id;
	    $ess->created         = common_sql_now();
	    $ess->last_summary_id = $new_top;
	    $ess->modified        = common_sql_now();

	    $ess->insert();
	    
	} else {
	    
	    $orig = clone($ess);
	    
	    $ess->last_summary_id = $new_top;
	    $ess->modified        = common_sql_now();

	    $ess->update();
	}
	
	return true;
    }
    
    function stylesheet()
    {
	$ss = <<<END_OF_STYLESHEET

#notices_primary {
    padding-top: 8px;
    clear: both;
}

#notices_primary h2 {
    display: none;
}

.notice {
    list-style-type: none;
    margin-bottom: 25px;
    clear: left;
    min-height: 54px;
    padding-bottom: 2px;
}

.notice, .profile, .application {
    position:relative;
    clear:both;
    float:left;
    width:100%;
}

.notice .author {
    margin-right: 8px;
}

.fn {
    overflow: hidden;
}

.notice .author .fn {
    font-weight: bold;
}

#core .vcard .photo {
    display: inline;
    margin-right: 11px;
    float: left;
}

#content .notice .author .photo {
    position: absolute;
    top: 4px;
    left: 4px;
    float: none;
}

#content .notice .entry-title {
    margin: 2px 7px 0px 59px;
}

.vcard .url {
    text-decoration:none;
}
.vcard .url:hover {
    text-decoration:underline;
}

.notice .entry-title {
    overflow:hidden;
    word-wrap:break-word;
}

.notice .entry-title.ov {
overflow:visible;
}

#showstream h1 { 
    display:none;
}

#showstream .notice .entry-title, #showstream .notice div.entry-content {
    margin-left: 0;
}

#showstream #content .notice .author {
    display: none;
}

#showstream .notice {
    min-height: 1em; 
}

#shownotice .vcard .photo {
    margin-bottom: 4px;
}

#shownotice .notice .entry-title {
    margin-left:110px;
    font-size:2.2em;
    min-height:123px;
    font-size: 1.6em;
    line-height: 1.2em;
}

#shownotice .notice div.entry-content {
    margin-left:0;
}

.notice p.entry-content {
    display:inline;
}

.notice div.entry-content {
    clear:left;
    float:left;
    margin-left:59px;
    margin-top: 10px;
}

.entry-content .repeat {
    display: block;
}

.entry-content .repeat .photo {
float:none;
margin-right:1px;
position:relative;
top:4px;
left:0;
}

.notice-options {
    float: right;    
    margin-top: 12px;
    margin-right: -6px;
}

.notice-options fieldset {
    border: none;
}

.notice-options legend {
    display: none;
}

.notice-options form, .notice-options a, .notice-options .repeated {
    float: left;
    margin-right: 10px;
}

.notice-options input, .notice-options a, .notice-options .repeated {    
    text-indent: -9999px;
    outline:none;
}

.notice-options input.submit, .notice-options a, .notice-options .repeated {
    display: block;
    border: 0;
    height: 16px;
    width: 16px;
}

.notice-options input.submit, .notice-options a {
    opacity: 0.6;
}

.notice-options input.submit:hover, .notice-options a:hover {
    opacity: 1;
}

.notice .attachment {
    position:relative;
    padding-left:16px;
}

.notice .attachment.more {
text-indent:-9999px;
width:16px;
height:16px;
display:inline-block;
overflow:hidden;
vertical-align:middle;
margin-left:4px;
}

#attachments .attachment,
.notice .attachment.more {
padding-left:0;
}

.notice .attachment img {
position:absolute;
top:18px;
left:0;
z-index:99;
}

#shownotice .notice .attachment img {
position:static;
}

#attachments {
clear:both;
float:left;
width:100%;
margin-top:18px;
}
#attachments dt {
font-weight:bold;
font-size:1.3em;
margin-bottom:4px;
}

#attachments ol li {
margin-bottom:18px;
list-style-type:decimal;
float:left;
clear:both;
}

#jOverlayContent,
#jOverlayContent #content,
#jOverlayContent #content_inner {
width: auto !important;
margin-bottom:0;
}
#jOverlayContent #content {
padding:11px;
min-height:auto;
    border: 1px solid #fff;
}
#jOverlayContent .entry-title {
display:block;
margin-bottom:11px;
}
#jOverlayContent button {
    position:absolute;
    top: 5px;
    right: 20px;
}
#jOverlayContent h1 {
max-width:425px;
}
#jOverlayLoading {
top:5%;
left:40%;
}
#attachment_view img {
max-width:480px;
max-height:480px;
}
#attachment_view #oembed_info {
margin-top:11px;
}
#attachment_view #oembed_info dt,
#attachment_view #oembed_info dd {
float:left;
}
#attachment_view #oembed_info dt {
clear:left;
margin-right:11px;
font-weight:bold;
}
#attachment_view #oembed_info dt:after {
content: ":";
}

#content .notice .notice {
    width: 98%;
    margin-left: 2%;
    margin-top: 16px;
    margin-bottom: 10px;
}

.notice .notice {
background-color:rgba(200, 200, 200, 0.050);
}
.notice .notice .notice {
background-color:rgba(200, 200, 200, 0.100);
}
.notice .notice .notice .notice {
background-color:rgba(200, 200, 200, 0.150);
}
.notice .notice .notice .notice .notice {
background-color:rgba(200, 200, 200, 0.300);
}

END_OF_STYLESHEET;

        return $ss;
    }
    
}

