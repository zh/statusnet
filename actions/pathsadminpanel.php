<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Paths administration panel
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
 * @copyright 2008-2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Paths settings
 *
 * @category Admin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class PathsadminpanelAction extends AdminPanelAction
{

    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return _('Paths');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */

    function getInstructions()
    {
        return _('Path and server settings for this StatusNet site.');
    }

    /**
     * Show the paths admin panel form
     *
     * @return void
     */

    function showForm()
    {
        $form = new PathsAdminPanelForm($this);
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
        static $settings = array(
            'site' => array('path', 'locale_path', 'ssl', 'sslserver'),
            'theme' => array('server', 'dir', 'path'),
            'avatar' => array('server', 'dir', 'path'),
            'background' => array('server', 'dir', 'path')
        );

	// XXX: If we're only going to have one boolean on thi page we
	// can remove some of the boolean processing code --Z

	static $booleans = array('site' => array('fancy'));

        $values = array();

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = $this->trimmed("$section-$setting");
            }
        }

        foreach ($booleans as $section => $parts) {
            foreach ($parts as $setting) {
                $values[$section][$setting] = ($this->boolean($setting)) ? 1 : 0;
            }
        }

        $this->validate($values);

        // assert(all values are valid);

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $section => $parts) {
            foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
        }

	foreach ($booleans as $section => $parts) {
	    foreach ($parts as $setting) {
                Config::save($section, $setting, $values[$section][$setting]);
            }
	}

	$config->query('COMMIT');

        return;
    }

    /**
     * Attempt to validate setting values
     *
     * @return void
     */

    function validate(&$values)
    {

        // Validate theme dir

        if (!empty($values['theme']['dir']) && !is_readable($values['theme']['dir'])) {
            $this->clientError(sprintf(_("Theme directory not readable: %s."), $values['theme']['dir']));
        }

        // Validate avatar dir

        if (empty($values['avatar']['dir']) || !is_writable($values['avatar']['dir'])) {
            $this->clientError(sprintf(_("Avatar directory not writable: %s."), $values['avatar']['dir']));
        }

        // Validate background dir

        if (empty($values['background']['dir']) || !is_writable($values['background']['dir'])) {
            $this->clientError(sprintf(_("Background directory not writable: %s."), $values['background']['dir']));
        }

        // Validate locales dir

        // XXX: What else do we need to validate for lacales path here? --Z

        if (!empty($values['site']['locale_path']) && !is_readable($values['site']['locale_path'])) {
            $this->clientError(sprintf(_("Locales directory not readable: %s."), $values['site']['locale_path']));
        }

        // Validate SSL setup

        if (mb_strlen($values['site']['sslserver']) > 255) {
            $this->clientError(_('Invalid SSL server. The maximum length is 255 characters.'));
        }
    }

}

class PathsAdminPanelForm extends AdminForm
{

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return 'form_paths_admin_panel';
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
        return common_local_url('pathsadminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
	$this->out->elementStart('fieldset', array('id' => 'settings_paths_locale'));
        $this->out->element('legend', null, _('Site'), 'site');
        $this->out->elementStart('ul', 'form_data');

	$this->li();
        $this->input('server', _('Server'), _('Site\'s server hostname.'));
        $this->unli();

        $this->li();
        $this->input('path', _('Path'), _('Site path'));
        $this->unli();

        $this->li();
        $this->input('locale_path', _('Path to locales'), _('Directory path to locales'), 'site');
        $this->unli();

	$this->li();
        $this->out->checkbox('fancy', _('Fancy URLs'),
                             (bool) $this->value('fancy'),
                             _('Use fancy (more readable and memorable) URLs?'));
	$this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' => 'settings_paths_theme'));
        $this->out->element('legend', null, _('Theme'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('server', _('Theme server'), 'Server for themes', 'theme');
        $this->unli();

        $this->li();
        $this->input('path', _('Theme path'), 'Web path to themes', 'theme');
        $this->unli();

        $this->li();
        $this->input('dir', _('Theme directory'), 'Directory where themes are located', 'theme');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');
        $this->out->elementStart('fieldset', array('id' => 'settings_avatar-paths'));
        $this->out->element('legend', null, _('Avatars'));

        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('server', _('Avatar server'), 'Server for avatars', 'avatar');
        $this->unli();

        $this->li();
        $this->input('path', _('Avatar path'), 'Web path to avatars', 'avatar');
        $this->unli();

        $this->li();
        $this->input('dir', _('Avatar directory'), 'Directory where avatars are located', 'avatar');
        $this->unli();

        $this->out->elementEnd('ul');

        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' =>
            'settings_design_background-paths'));
        $this->out->element('legend', null, _('Backgrounds'));
        $this->out->elementStart('ul', 'form_data');

        $this->li();
        $this->input('server', _('Background server'), 'Server for backgrounds', 'background');
        $this->unli();

        $this->li();
        $this->input('path', _('Background path'), 'Web path to backgrounds', 'background');
        $this->unli();

        $this->li();
        $this->input('dir', _('Background directory'), 'Directory where backgrounds are located', 'background');
        $this->unli();

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

        $this->out->elementStart('fieldset', array('id' => 'settings_admin_ssl'));
        $this->out->element('legend', null, _('SSL'));
        $this->out->elementStart('ul', 'form_data');
        $this->li();
        $ssl = array('never' => _('Never'),
                     'sometimes' => _('Sometimes'),
                     'always' => _('Always'));

        common_debug("site ssl = " . $this->value('site', 'ssl'));

        $this->out->dropdown('site-ssl', _('Use SSL'),
                             $ssl, _('When to use SSL'),
                             false, $this->value('ssl', 'site'));
        $this->unli();

        $this->li();
        $this->input('sslserver', _('SSL server'),
                     _('Server to direct SSL requests to'), 'site');
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
        $this->out->submit('save', _('Save'), 'submit',
                'save', _('Save paths'));
    }

    /**
     * Utility to simplify some of the duplicated code around
     * params and settings. Overriding the input() in the base class
     * to handle a whole bunch of cases of settings with the same
     * name under different sections.
     *
     * @param string $setting      Name of the setting
     * @param string $title        Title to use for the input
     * @param string $instructions Instructions for this field
     * @param string $section      config section, default = 'site'
     *
     * @return void
     */

    function input($setting, $title, $instructions, $section='site')
    {
        $this->out->input("$section-$setting", $title, $this->value($setting, $section), $instructions);
    }

}
