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

require_once(INSTALLDIR.'/lib/mail.php');

class FavorAction extends Action
{

    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            common_user_error(_('Not logged in.'));
            return;
        }

        $user = common_current_user();

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            common_redirect(common_local_url('showfavorites', array('nickname' => $user->nickname)));
            return;
        }

        $id = $this->trimmed('notice');

        $notice = Notice::staticGet($id);

        # CSRF protection

        $token = $this->trimmed('token-'.$notice->id);
        if (!$token || $token != common_session_token()) {
            $this->client_error(_("There was a problem with your session token. Try again, please."));
            return;
        }

        if ($user->hasFave($notice)) {
            $this->client_error(_('This notice is already a favorite!'));
            return;
        }

        $fave = Fave::addNew($user, $notice);

        if (!$fave) {
            $this->server_error(_('Could not create favorite.'));
            return;
        }

        $this->notify($fave, $notice, $user);
        $user->blowFavesCache();
        
        if ($this->boolean('ajax')) {
            common_start_html('text/xml;charset=utf-8', true);
            common_element_start('head');
            common_element('title', null, _('Disfavor favorite'));
            common_element_end('head');
            common_element_start('body');
            common_disfavor_form($notice);
            common_element_end('body');
            common_element_end('html');
        } else {
            common_redirect(common_local_url('showfavorites',
                                             array('nickname' => $user->nickname)));
        }
    }

    function notify($fave, $notice, $user)
    {
        $other = User::staticGet('id', $notice->profile_id);
        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $user, $notice);
            }
            # XXX: notify by IM
            # XXX: notify by SMS
        }
    }

}
