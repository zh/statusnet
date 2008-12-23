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

if (!defined('LACONICA')) { exit(1); }

class SubscribeAction extends Action {

    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            common_user_error(_('Not logged in.'));
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
            $this->client_error(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        $other_id = $this->arg('subscribeto');

        $other = User::staticGet('id', $other_id);

        if (!$other) {
            $this->client_error(_('Not a local user.'));
            return;
        }

        $result = subs_subscribe_to($user, $other);

        if($result != true) {
            common_user_error($result);
            return;
        }

        if ($this->boolean('ajax')) {
            common_start_html('text/xml;charset=utf-8', true);
            common_element_start('head');
            common_element('title', null, _('Subscribed'));
            common_element_end('head');
            common_element_start('body');
            common_unsubscribe_form($other->getProfile());
            common_element_end('body');
            common_element_end('html');
        } else {
            common_redirect(common_local_url('subscriptions', array('nickname' =>
                                                                $user->nickname)));
        }
    }
}
