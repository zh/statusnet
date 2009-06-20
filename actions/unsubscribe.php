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

class UnsubscribeAction extends Action
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

        $other_id = $this->arg('unsubscribeto');

        if (!$other_id) {
            $this->clientError(_('No profile id in request.'));
            return;
        }

        $other = Profile::staticGet('id', $other_id);

        if (!$other_id) {
            $this->clientError(_('No profile with that id.'));
            return;
        }

        $result = subs_unsubscribe_to($user, $other);

        if ($result != true) {
            $this->clientError($result);
            return;
        }

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8');
            $this->elementStart('head');
            $this->element('title', null, _('Unsubscribed'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $subscribe = new SubscribeForm($this, $other);
            $subscribe->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('subscriptions', array('nickname' =>
                                                                    $user->nickname)),
                            303);
        }
    }
}
