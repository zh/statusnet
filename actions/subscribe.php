<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) { exit(1); }

class SubscribeAction extends Action
{

    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return;
        }

        $user = common_current_user();

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('subscriptions', array('nickname' => $user->nickname)));
            return;
        }

        # CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $other_id = $this->arg('subscribeto');

        $other = User::staticGet('id', $other_id);

        if (!$other) {
            $this->clientError(_('Not a local user.'));
            return;
        }

        $result = subs_subscribe_to($user, $other);

        if($result != true) {
            $this->clientError($result);
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Subscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $unsubscribe = new UnsubscribeForm($this, $other->getProfile());
            $unsubscribe->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('subscriptions', array('nickname' =>
                                                                $user->nickname)),
                            303);
        }
    }
}
