<?php
/**
 * Block a user action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Block a user action class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class BlockAction extends ProfileFormAction
{
    var $profile = null;

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        if (!parent::prepare($args)) {
            return false;
        }

        $cur = common_current_user();

        assert(!empty($cur)); // checked by parent

        if ($cur->hasBlocked($this->profile)) {
            $this->clientError(_("You already blocked that user."));
            return false;
        }

        return true;
    }

    /**
     * Handle request
     *
     * Shows a page with list of favorite notices
     *
     * @param array $args $_REQUEST args; handled in prepare()
     *
     * @return void
     */

    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->arg('no')) {
                $this->returnToArgs();
            } elseif ($this->arg('yes')) {
                $this->handlePost();
                $this->returnToArgs();
            } else {
                $this->showPage();
            }
        }
    }

    function showContent() {
        $this->areYouSureForm();
    }

    function title() {
        return _('Block user');
    }

    function showNoticeForm() {
        // nop
    }

    /**
     * Confirm with user.
     *
     * Shows a confirmation form.
     *
     * @return void
     */
    function areYouSureForm()
    {
        $id = $this->profile->id;
        $this->elementStart('form', array('id' => 'block-' . $id,
                                           'method' => 'post',
                                           'class' => 'form_settings form_entity_block',
                                           'action' => common_local_url('block')));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->element('legend', _('Block user'));
        $this->element('p', null,
                       _('Are you sure you want to block this user? '.
                         'Afterwards, they will be unsubscribed from you, '.
                         'unable to subscribe to you in the future, and '.
                         'you will not be notified of any @-replies from them.'));
        $this->element('input', array('id' => 'blockto-' . $id,
                                      'name' => 'profileid',
                                      'type' => 'hidden',
                                      'value' => $id));
        foreach ($this->args as $k => $v) {
            if (substr($k, 0, 9) == 'returnto-') {
                $this->hidden($k, $v);
            }
        }
        $this->submit('form_action-no', _('No'), 'submit form_action-primary', 'no', _("Do not block this user"));
        $this->submit('form_action-yes', _('Yes'), 'submit form_action-secondary', 'yes', _('Block this user'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Actually block a user.
     *
     * @return void
     */

    function handlePost()
    {
        $cur = common_current_user();

        if (Event::handle('StartBlockProfile', array($cur, $this->profile))) {
            $result = $cur->block($this->profile);
            if ($result) {
                Event::handle('EndBlockProfile', array($cur, $this->profile));
            }
        }

        if (!$result) {
            $this->serverError(_('Failed to save block information.'));
            return;
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('form_action-yes');
    }

}
