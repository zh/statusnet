<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Handler for posting new messages
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

    var $content = null;
    var $to = null;
    var $other = null;

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

    function prepare($args)
    {
        parent::prepare($args);

        $user = common_current_user();

        if (!$user) {
            $this->clientError(_('Only logged-in users can send direct messages.'), 403);
            return false;
        }

        $this->content = $this->trimmed('content');
        $this->to = $this->trimmed('to');

        if ($this->to) {

            $this->other = User::staticGet('id', $this->to);

            if (!$this->other) {
                $this->clientError(_('No such user'), 404);
                return false;
            }

            if (!$user->mutuallySubscribed($this->other)) {
                $this->clientError(_('You can\'t send a message to this user.'), 404);
                return false;
            }
        }

        return true;
    }

    function saveNewMessage()
    {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. ' .
                'Try again, please.'));
            return;
        }

        $user = common_current_user();
        assert($user); // XXX: maybe an error instead...

        if (!$this->content) {
            $this->showForm(_('No content!'));
            return;
        } else {
            $content_shortened = common_shorten_links($this->content);

            if (mb_strlen($content_shortened) > 140) {
                $this->showForm(_('That\'s too long. ' .
                    'Max message size is 140 chars.'));
                return;
            }
        }

        if (!$this->other) {
            $this->showForm(_('No recipient specified.'));
            return;
        } else if (!$user->mutuallySubscribed($this->other)) {
            $this->clientError(_('You can\'t send a message to this user.'), 404);
            return;
        } else if ($user->id == $this->other->id) {
            $this->clientError(_('Don\'t send a message to yourself; ' .
                'just say it to yourself quietly instead.'), 403);
            return;
        }

        $message = Message::saveNew($user->id, $this->other->id, $this->content, 'web');

        if (is_string($message)) {
            $this->showForm($message);
            return;
        }

        $this->notify($user, $this->other, $message);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Message sent'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', array('id' => 'command_result'),
                sprintf(_('Direct message to %s sent'),
                    $this->other->nickname));
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $url = common_local_url('outbox',
                array('nickname' => $user->nickname));
            common_redirect($url, 303);
        }
    }

    /**
     * Show an Ajax-y error message
     *
     * Goes back to the browser, where it's shown in a popup.
     *
     * @param string $msg Message to show
     *
     * @return void
     */

    function ajaxErrorMsg($msg)
    {
        $this->startHTML('text/xml;charset=utf-8', true);
        $this->elementStart('head');
        $this->element('title', null, _('Ajax Error'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $this->element('p', array('id' => 'error'), $msg);
        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    function showForm($msg = null)
    {
        if ($msg && $this->boolean('ajax')) {
            $this->ajaxErrorMsg($msg);
            return;
        }

        $this->msg = $msg;
        $this->showPage();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        }
    }

    function notify($from, $to, $message)
    {
        mail_notify_message($message, $from, $to);
        // XXX: Jabber, SMS notifications... probably queued
    }

    // Do nothing (override)

    function showNoticeForm()
    {
        $message_form = new MessageForm($this, $this->other, $this->content);
        $message_form->show();
    }
}
