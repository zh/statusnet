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

class BlockAction extends Action {

    var $profile = NULL;

    function prepare($args) {

        parent::prepare($args);

        if (!common_logged_in()) {
            $this->client_error(_('Not logged in.'));
            return false;
        }

		$token = $this->trimmed('token');

		if (!$token || $token != common_session_token()) {
			$this->client_error(_('There was a problem with your session token. Try again, please.'));
			return;
		}

        $id = $this->trimmed('blockto');

        if (!$id) {
            $this->client_error(_('No profile specified.'));
            return false;
        }

        $this->profile = Profile::staticGet('id', $id);

        if (!$this->profile) {
            $this->client_error(_('No profile with that ID.'));
            return false;
        }

        return true;
    }

    function handle($args) {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('block')) {
                $this->are_you_sure_form();
            } else if ($this->arg('no')) {
                $cur = common_current_user();
                common_redirect(common_local_url('subscribers',
                                                 array('nickname' => $cur->nickname)));
            } else if ($this->arg('yes')) {
                $this->block_profile();
            }
        }
    }

    function are_you_sure_form() {

        $id = $this->profile->id;

		common_show_header(_('Block user'));

        common_element_start('p', NULL,
                             _('Are you sure you want to block this user? '.
                               'Afterwards, they will be unsubscribed from you, '.
                               'unable to subscribe to you in the future, and '.
                               'you will not be notified of any @-replies from them.'));

        common_element_start('form', array('id' => 'block-' . $id,
                                           'method' => 'post',
                                           'class' => 'block',
                                           'action' => common_local_url('block')));

        common_hidden('token', common_session_token());

        common_element('input', array('id' => 'blockto-' . $id,
                                      'name' => 'blockto',
                                      'type' => 'hidden',
                                      'value' => $id));

        common_submit('no', _('No'));
        common_submit('yes', _('Yes'));

        common_element_end('form');

        common_show_footer();
    }

    function block_profile() {

        $cur = common_current_user();

        if ($cur->hasBlocked($this->profile)) {
            $this->client_error(_('You have already blocked this user.'));
            return;
        }

        # Add a new block record

        $block = new Profile_block();

        # Begin a transaction

        $block->query('BEGIN');

        $block->blocker = $cur->id;
        $block->blocked = $this->profile->id;

        $result = $block->insert();

        if (!$result) {
            common_log_db_error($block, 'INSERT', __FILE__);
            $this->server_error(_('Could not save new block record.'));
            return;
        }

        # Cancel their subscription, if it exists

		$sub = Subscription::pkeyGet(array('subscriber' => $this->profile->id,
										   'subscribed' => $cur->id));

        if ($sub) {
            $result = $sub->delete();
            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                $this->server_error(_('Could not delete subscription.'));
                return;
            }
        }

        $block->query('COMMIT');

        common_redirect(common_local_url('subscribers',
                                         array('nickname' => $cur->nickname)));
    }
}
