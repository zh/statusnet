<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Site access administration panel
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
 * @category  Settings
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer site access settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class AccessadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('Access');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('Site access settings');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new AccessAdminPanelForm($this);
        $form->show();
        return;
    }

    /**
     * Save settings from the form
     *
     * @return void
     */

    function saveSettings()
    {
        static $booleans = array('site' => array('private', 'inviteonly', 'closed'));

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = ($this->boolean($setting)) ? 1 : 0;
            }
        }

        $config = new Config();

        $config->query('BEGIN');

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

        $config->query('COMMIT');

        return;
    }

}

class AccessAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_site_admin_panel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('accessadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
	$this->out->elementStart('fieldset', array('id' => 'settings_admin_access'));
        $this->out->element('legend', null, _('Registration'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $this->out->checkbox('private', _('Private'),
                             (bool) $this->value('private'),
                             _('Prohibit anonymous users (not logged in) from viewing site?'));
        $this->unli();

        $this->li();
        $this->out->checkbox('inviteonly', _('Invite only'),
                             (bool) $this->value('inviteonly'),
                             _('Make registration invitation only.'));
        $this->unli();

        $this->li();
        $this->out->checkbox('closed', _('Closed'),
                             (bool) $this->value('closed'),
                             _('Disable new registrations.'));
        $this->unli();
        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Save'), 'submit', null, _('Save access settings'));
    }

}
