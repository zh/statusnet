<?php

/**
 * User by ID action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/mail.php';

/**
 * Nudge a user action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class NudgeAction extends Action
{
     /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return;
        }

        $user  = common_current_user();
        $other = User::staticGet('nickname', $this->arg('nickname'));

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('showstream',
                array('nickname' => $other->nickname)));
            return;
        }

        // CSRF protection
        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if (!$other->email || !$other->emailnotifynudge) {
            $this->clientError(_('This user doesn\'t allow nudges or hasn\'t confirmed or set his email yet.'));
            return;
        }

        $this->notify($user, $other);

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Nudge sent'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->element('p', array('id' => 'nudge_response'), _('Nudge sent!'));
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            // display a confirmation to the user
            common_redirect(common_local_url('showstream',
                                             array('nickname' => $other->nickname)),
                            303);
        }
    }

     /**
     * Do the actual notification
     *
     * @param class $user  nudger
     * @param class $other nudgee
     *
     * @return nothing
     */
    function notify($user, $other)
    {
        if ($other->id != $user->id) {
            if ($other->email && $other->emailnotifynudge) {
                mail_notify_nudge($user, $other);
            }
            // XXX: notify by IM
            // XXX: notify by SMS
        }
    }

    function isReadOnly($args)
    {
        return true;
    }
}

