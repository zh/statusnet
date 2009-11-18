<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Design administration panel
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
 * Administer design settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class DesignadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('Design');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('Design settings for this StatusNet site.');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new DesignAdminPanelForm($this);
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
        if (!in_array($values['theme'], Theme::listAvailable())) {
            $this->clientError(sprintf(_("Theme not available: %s"), $values['theme']));
        }
    }
}

class DesignAdminPanelForm extends Form
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_design_admin_panel';
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
        return common_local_url('designadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $themes = Theme::listAvailable();

        asort($themes);

        $themes = array_combine($themes, $themes);

        $this->out->elementStart('ul', 'form_data');
        $this->out->elementStart('li');

        $this->out->dropdown('theme', _('Theme'),
                             $themes, _('Theme for the site.'),
                             true, $this->value('theme'));

        $this->out->elementEnd('li');
        $this->out->elementEnd('ul');
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

    function value($setting)
    {
        $value = $this->out->trimmed($setting);
        if (empty($value)) {
            $value = common_config('site', $setting);
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
