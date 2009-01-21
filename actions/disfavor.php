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

require_once INSTALLDIR.'/lib/favorform.php';

class DisfavorAction extends Action
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
            common_redirect(common_local_url('showfavorites', array('nickname' => $user->nickname)));
            return;
        }

        $id = $this->trimmed('notice');

        $notice = Notice::staticGet($id);

        $token = $this->trimmed('token-'.$notice->id);

        if (!$token || $token != common_session_token()) {
            $this->clientError(_("There was a problem with your session token. Try again, please."));
            return;
        }

        $fave = new Fave();
        $fave->user_id = $this->id;
        $fave->notice_id = $notice->id;
        if (!$fave->find(true)) {
            $this->clientError(_('This notice is not a favorite!'));
            return;
        }

        $result = $fave->delete();

        if (!$result) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            $this->serverError(_('Could not delete favorite.'));
            return;
        }
        
        $user->blowFavesCache();

        if ($this->boolean('ajax')) {
            $this->startHTML('text/xml;charset=utf-8', true);
            $this->elementStart('head');
            $this->element('title', null, _('Add to favorites'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $favor = new FavorForm($this, $notice);
            $favor->show();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect(common_local_url('showfavorites',
                                             array('nickname' => $user->nickname)));
        }
    }
}
