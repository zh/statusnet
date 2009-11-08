<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Site administration panel
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
 * Administer site settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class SiteadminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('Site');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('Basic settings for this StatusNet site.');
    }

    /**
     * Show the site admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new SiteAdminPanelForm($this);
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
        static $settings = array('name', 'broughtby', 'broughtbyurl', 'email');

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
            Config::save('site', $setting, $values['setting']);
        }

        $config->query('COMMIT');

        return;
    }

    function validate(&$values)
    {
        // Validate site name

        if (empty($values['name'])) {
            $this->clientError(_("Site name must have non-zero length."));
        }

        // Validate email

        $values['email'] = common_canonical_email($values['email']);

        if (empty($values['email'])) {
            $this->clientError(_('You must have a valid contact email address'));
        }
        if (!Validate::email($values['email'], common_config('email', 'check_domain'))) {
            $this->clientError(_('Not a valid email address'));
        }
    }
}

class SiteAdminPanelForm extends Form
{
    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'siteadminpanel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_site_admin_panel';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('siteadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $this->input('name', _('Site name'),
                     _('The name of your site, like "Yourcompany Microblog"'));
        $this->input('broughtby', _('Brought by'),
                     _('Text used for credits link in footer of each page'));
        $this->input('broughtbyurl', _('Brought by URL'),
                     _('URL used for credits link in footer of each page'));
        $this->input('email', _('Email'),
                     _('contact email address for your site'));
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
        $value = $this->out->trimmed($setting);
        if (empty($value)) {
            $value = common_config('site', $setting);
        }
        $this->out->input($setting, $title, $value, $instructions);
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
