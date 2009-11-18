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

    /* The default site design */
    var $design = null;

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
        $this->design = Design::siteDesign();

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
        if ($this->arg('save')) {
            $this->saveDesignSettings();
        } else if ($this->arg('defaults')) {
            $this->restoreDefaults();
        } else {
            $this->success = false;
            $this->message = 'Unexpected form submission.';
        }
    }

    /**
     * Save the new design settings
     *
     * @return void
     */

    function saveDesignSettings()
    {

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
            $msg = _('The server was unable to handle that much POST ' .
                'data (%s bytes) due to its current configuration.');
            $this->success = false;
            $this->msg     = $e->getMessage(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // check for an image upload

        $bgimage = $this->saveBackgroundImage();

        static $settings = array('theme');
        $values = array();

        foreach ($settings as $setting) {
            $values[$setting] = $this->trimmed($setting);
        }

        // This throws an exception on validation errors
        try {
            $bgcolor = new WebColor($this->trimmed('design_background'));
            $ccolor  = new WebColor($this->trimmed('design_content'));
            $sbcolor = new WebColor($this->trimmed('design_sidebar'));
            $tcolor  = new WebColor($this->trimmed('design_text'));
            $lcolor  = new WebColor($this->trimmed('design_links'));
        } catch (WebColorException $e) {
            $this->success = false;
            $this->msg = $e->getMessage();
            return;
        }

        $onoff = $this->arg('design_background-image_onoff');

        $on   = false;
        $off  = false;

        if ($onoff == 'on') {
            $on = true;
        } else {
            $off = true;
        }

        $tile = $this->boolean('design_background-image_repeat');

        $this->validate($values);

        // assert(all values are valid);

        $config = new Config();

        $config->query('BEGIN');

        foreach ($settings as $setting) {
            Config::save('site', $setting, $values[$setting]);
        }

        if (isset($bgimage)) {
            Config::save('design', 'backgroundimage', $bgimage);
        }

        Config::save('design', 'backgroundcolor', $bgcolor->intValue());
        Config::save('design', 'contentcolor', $ccolor->intValue());
        Config::save('design', 'sidebarcolor', $sbcolor->intValue());
        Config::save('design', 'textcolor', $tcolor->intValue());
        Config::save('design', 'linkcolor', $lcolor->intValue());

        // Hack to use Design's bit setter
        $scratch = new Design();
        $scratch->setDisposition($on, $off, $tile);

        Config::save('design', 'disposition', $scratch->disposition);

        $config->query('COMMIT');

        return;

    }

    /**
     * Delete a design setting
     *
     * @return mixed $result false if something didn't work
     */

    function deleteSetting($section, $setting)
    {
        $config = new Config();

        $config->section = $section;
        $config->setting = $setting;

        if ($config->find(true)) {
            $result = $config->delete();
            if (!$result) {
                common_log_db_error($config, 'DELETE', __FILE__);
                $this->clientError(_("Unable to delete design setting."));
                return null;
            }
        }

        return $result;
    }

    /**
      * Restore the default design
      *
      * @return void
      */

    function restoreDefaults()
    {
        $this->deleteSetting('site', 'theme');

        $settings = array(
            'theme', 'backgroundimage', 'backgroundcolor', 'contentcolor',
            'sidebarcolor', 'textcolor', 'linkcolor', 'disposition'
        );

        foreach ($settings as $setting) {
            $this->deleteSetting('design', $setting);
        }
    }

    /**
     * Save the background image if the user uploaded one
     *
     * @return string $filename the filename of the image
     */

    function saveBackgroundImage()
    {
        $filename = null;

        if ($_FILES['design_background-image_file']['error'] ==
            UPLOAD_ERR_OK) {

            $filepath = null;

            try {
                $imagefile =
                    ImageFile::fromUpload('design_background-image_file');
            } catch (Exception $e) {
                $this->success = false;
                $this->msg     = $e->getMessage();
                return;
            }

            // Note: site design background image has a special filename

            $filename = Design::filename('site-design-background',
                image_type_to_extension($imagefile->type),
                    common_timestamp());

            $filepath = Design::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            // delete any old backround img laying around

            if (isset($this->design->backgroundimage)) {
                @unlink(Design::path($design->backgroundimage));
            }

            return $filename;
        }
    }

    /**
     * Attempt to validate setting values
     *
     * @return void
     */

    function validate(&$values)
    {
        if (!in_array($values['theme'], Theme::listAvailable())) {
            $this->clientError(sprintf(_("Theme not available: %s"), $values['theme']));
        }
    }

    /**
     * Add the Farbtastic stylesheet
     *
     * @return void
     */

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('css/farbtastic.css','base','screen, projection, tv');
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */

    function showScripts()
    {
        parent::showScripts();

        $this->script('js/farbtastic/farbtastic.js');
        $this->script('js/userdesign.go.js');

        $this->autofocus('design_background-image_file');
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
     * HTTP method used to submit the form
     *
     * For image data we need to send multipart/form-data
     * so we set that here too
     *
     * @return string the method to use for submitting
     */

    function method()
    {
        $this->enctype = 'multipart/form-data';

        return 'post';
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

        $design = $this->out->design;

        $themes = Theme::listAvailable();

        asort($themes);

        $themes = array_combine($themes, $themes);

        $this->out->elementStart('ul', 'form_data');

        $this->out->elementStart('li');
        $this->out->dropdown('theme', _('Theme'),
                             $themes, _('Theme for the site.'),
                             false, $this->value('theme'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->element('label', array('for' => 'design_background-image_file'),
                                _('Background'));
        $this->out->element('input', array('name' => 'design_background-image_file',
                                     'type' => 'file',
                                     'id' => 'design_background-image_file'));
        $this->out->element('p', 'form_guide',
            sprintf(_('You can upload a background image for the site. ' .
              'The maximum file size is %1$s.'), ImageFile::maxFileSize()));
        $this->out->element('input', array('name' => 'MAX_FILE_SIZE',
                                          'type' => 'hidden',
                                          'id' => 'MAX_FILE_SIZE',
                                          'value' => ImageFile::maxFileSizeInt()));
        $this->out->elementEnd('li');

        if (!empty($design->backgroundimage)) {

            $this->out->elementStart('li', array('id' =>
                'design_background-image_onoff'));

            $this->out->element('img', array('src' =>
                Design::url($design->backgroundimage)));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_on',
                           'class' => 'radio',
                           'value' => 'on');

            if ($design->disposition & BACKGROUND_ON) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_on',
                                          'class' => 'radio'),
                                          _('On'));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_off',
                           'class' => 'radio',
                           'value' => 'off');

            if ($design->disposition & BACKGROUND_OFF) {
                $attrs['checked'] = 'checked';
            }

            $this->out->element('input', $attrs);

            $this->out->element('label', array('for' => 'design_background-image_off',
                                          'class' => 'radio'),
                                          _('Off'));
            $this->out->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->out->elementEnd('li');

            $this->out->elementStart('li');
            $this->out->checkbox('design_background-image_repeat',
                            _('Tile background image'),
                            ($design->disposition & BACKGROUND_TILE) ? true : false);
            $this->out->elementEnd('li');
        }

        $this->out->elementEnd('ul');

        $this->out->elementStart('fieldset', array('id' => 'settings_design_color'));
        $this->out->element('legend', null, _('Change colours'));
        $this->out->elementStart('ul', 'form_data');

        try {

            $bgcolor = new WebColor($design->backgroundcolor);

            $this->out->elementStart('li');
            $this->out->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->out->element('input', array('name' => 'design_background',
                                          'type' => 'text',
                                          'id' => 'swatch-1',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->out->elementEnd('li');

            $ccolor = new WebColor($design->contentcolor);

            $this->out->elementStart('li');
            $this->out->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->out->element('input', array('name' => 'design_content',
                                          'type' => 'text',
                                          'id' => 'swatch-2',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->out->elementEnd('li');

            $sbcolor = new WebColor($design->sidebarcolor);

            $this->out->elementStart('li');
            $this->out->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->out->element('input', array('name' => 'design_sidebar',
                                        'type' => 'text',
                                        'id' => 'swatch-3',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->out->elementEnd('li');

            $tcolor = new WebColor($design->textcolor);

            $this->out->elementStart('li');
            $this->out->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->out->element('input', array('name' => 'design_text',
                                        'type' => 'text',
                                        'id' => 'swatch-4',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->out->elementEnd('li');

            $lcolor = new WebColor($design->linkcolor);

            $this->out->elementStart('li');
            $this->out->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->out->element('input', array('name' => 'design_links',
                                         'type' => 'text',
                                         'id' => 'swatch-5',
                                         'class' => 'swatch',
                                         'maxlength' => '7',
                                         'size' => '7',
                                         'value' => ''));
            $this->out->elementEnd('li');

        } catch (WebColorException $e) {
            common_log(LOG_ERR, 'Bad color values in site design: ' .
                $e->getMessage());
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('fieldset');

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
        $this->out->submit('defaults', _('Use defaults'), 'submit form_action-default',
                'defaults', _('Restore default designs'));

        $this->out->element('input', array('id' => 'settings_design_reset',
                                         'type' => 'reset',
                                         'value' => 'Reset',
                                         'class' => 'submit form_action-primary',
                                         'title' => _('Reset back to default')));

        $this->out->submit('save', _('Save'), 'submit form_action-secondary',
                'save', _('Save design'));    }
}
