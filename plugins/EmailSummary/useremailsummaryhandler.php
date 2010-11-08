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
}

