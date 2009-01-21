<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Handler for posting new notices
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Personal
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */
 
if (!defined('LACONICA')) { 
    exit(1); 
}

/**
 * Action for posting new direct messages
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class NewmessageAction extends Action
{
    
    /**
     * Error message, if any
     */

    var $msg = null;

    /**
     * Title of the page
     *
     * Note that this usually doesn't get called unless something went wrong
     *
     * @return string page title
     */
     
    function title()
    {
        return _('New message');
    }
    
    /**
     * Handle input, produce output
     *
     * @param array $args $_REQUEST contents
     *
     * @return void
     */
    
    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'), 403);
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->saveNewMessage();
        } else {
            $this->showForm();
        }
    }

    function saveNewMessage()
    {
        $user = common_current_user();
        assert($user); // XXX: maybe an error instead...

        // CSRF protection
        
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. ' .
                'Try again, please.'));
            return;
        }
        
        $content = $this->trimmed('content');
        $to      = $this->trimmed('to');
        
        if (!$content) {
            $this->showForm(_('No content!'));
            return;
        } else {
            $content_shortened = common_shorten_links($content);

            if (mb_strlen($content_shortened) > 140) {
                common_debug("Content = '$content_shortened'", __FILE__);
                common_debug("mb_strlen(\$content) = " . 
                    mb_strlen($content_shortened),
                    __FILE__);
                $this->showForm(_('That\'s too long. ' .
                    'Max message size is 140 chars.'));
                return;
            }
        }

        $other = User::staticGet('id', $to);
        
        if (!$other) {
            $this->showForm(_('No recipient specified.'));
            return;
        } else if (!$user->mutuallySubscribed($other)) {
            $this->clientError(_('You can\'t send a message to this user.'), 404);
            return;
        } else if ($user->id == $other->id) {
            $this->clientError(_('Don\'t send a message to yourself; ' .
                'just say it to yourself quietly instead.'), 403);
            return;
        }
        
        $message = Message::saveNew($user->id, $other->id, $content, 'web');
        
        if (is_string($message)) {
            $this->showForm($message);
            return;
        }

        $this->notify($user, $other, $message);

        $url = common_local_url('outbox', array('nickname' => $user->nickname));

        common_redirect($url, 303);
    }

    function showForm($msg = null)
    {
        $content = $this->trimmed('content');
        $user    = common_current_user();

        $to = $this->trimmed('to');
        
        $other = User::staticGet('id', $to);

        if (!$other) {
            $this->clientError(_('No such user'), 404);
            return;
        }

        if (!$user->mutuallySubscribed($other)) {
            $this->clientError(_('You can\'t send a message to this user.'), 404);
            return;
        }        
        
        $this->msg = $msg;
        
        $this->showPage();
    }
    
    function notify($from, $to, $message)
    {
        mail_notify_message($message, $from, $to);
        // XXX: Jabber, SMS notifications... probably queued
    }
}
