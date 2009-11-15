<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * User administration panel
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
 * @author    Evan Prodromou <evan@status.net>
 * @author    Zach Copley <zach@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Administer user settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class UseradminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('User');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('User settings for this StatusNet site.');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new UserAdminPanelForm($this);
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
        static $settings = array('theme');
        static $booleans = array('closed', 'inviteonly', 'private');

        $values = array();

        foreach ($settings as $setting) {
            $values[$setting] = $this->trimmed($setting);
        }

        // This throws an exception on validation errors

        $this->validate($values);

        // assert(all values are valid);

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $setting) {
            Config::save('site', $setting, $values[$setting]);
        }

        $config->query('COMMIT');

        return;
    }

    function validate(&$values)
    {
    }
}

class UserAdminPanelForm extends Form
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'useradminpanel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_user_admin_panel';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('useradminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->li();

        $this->out->checkbox('closed', _('Closed'),
                             (bool) $this->value('closed'),
                             _('Is registration on this site prohibited?'));

        $this->unli();
        $this->li();

        $this->out->checkbox('inviteonly', _('Invite-only'),
                             (bool) $this->value('inviteonly'),
                             _('Is registration on this site only open to invited users?'));

        $this->unli();
    }

    /**
     * Utility to simplify some of the duplicated code around
     * params and settings.
     *
     * @param string $setting      Name of the setting
     * @param string $title        Title to use for the input
     * @param string $instructions Instructions for this field
     *
     * @return void
     */

    function input($setting, $title, $instructions)
    {
        $this->out->input($setting, $title, $this->value($setting), $instructions);
    }

    /**
     * Utility to simplify getting the posted-or-stored setting value
     *
     * @param string $setting Name of the setting
     *
     * @return string param value if posted, or current config value
     */

    function value($cat, $setting)
    {
        $value = $this->out->trimmed($setting);
        if (empty($value)) {
            $value = common_config($cat, $setting);
        }
        return $value;
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Save'), 'submit', null, _('Save site settings'));
    }
}
