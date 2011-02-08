<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Change user password
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
 * @author    Sarven Capadisli <csarven@status.net>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/accountsettingsaction.php';
require_once INSTALLDIR . '/lib/webcolor.php';

/**
 * Base class for setting a user or group design
 *
 * Shows the design setting form and also handles some things like saving
 * background images, and fetching a default design
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class DesignSettingsAction extends AccountSettingsAction
{
    var $submitaction = null;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */
    function title()
    {
        // TRANS: Page title for profile design page.
        return _('Profile design');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */
    function getInstructions()
    {
        // TRANS: Instructions for profile design page.
        return _('Customize the way your profile looks ' .
        'with a background image and a colour palette of your choice.');
    }

    /**
     * Shows the design settings form
     *
     * @param Design $design a working design to show
     *
     * @return nothing
     */
    function showDesignForm($design)
    {
        $this->elementStart('form', array('method' => 'post',
                                          'enctype' => 'multipart/form-data',
                                          'id' => 'form_settings_design',
                                          'class' => 'form_settings',
                                          'action' => $this->submitaction));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' =>
            'settings_design_background-image'));
        // TRANS: Fieldset legend on profile design page.
        $this->element('legend', null, _('Change background image'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                      'type' => 'hidden',
                                      'id' => 'MAX_FILE_SIZE',
                                      'value' => ImageFile::maxFileSizeInt()));
        $this->element('label', array('for' => 'design_background-image_file'),
                                // TRANS: Label in form on profile design page.
                                // TRANS: Field contains file name on user's computer that could be that user's custom profile background image.
                                _('Upload file'));
        $this->element('input', array('name' => 'design_background-image_file',
                                      'type' => 'file',
                                      'id' => 'design_background-image_file'));
        // TRANS: Instructions for form on profile design page.
        $this->element('p', 'form_guide', _('You can upload your personal ' .
            'background image. The maximum file size is 2MB.'));
        $this->elementEnd('li');

        if (!empty($design->backgroundimage)) {
            $this->elementStart('li', array('id' =>
                'design_background-image_onoff'));

            $this->element('img', array('src' =>
                Design::url($design->backgroundimage)));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_on',
                           'class' => 'radio',
                           'value' => 'on');

            if ($design->disposition & BACKGROUND_ON) {
                $attrs['checked'] = 'checked';
            }

            $this->element('input', $attrs);

            $this->element('label', array('for' => 'design_background-image_on',
                                          'class' => 'radio'),
                                          // TRANS: Radio button on profile design page that will enable use of the uploaded profile image.
                                          _m('RADIO','On'));

            $attrs = array('name' => 'design_background-image_onoff',
                           'type' => 'radio',
                           'id' => 'design_background-image_off',
                           'class' => 'radio',
                           'value' => 'off');

            if ($design->disposition & BACKGROUND_OFF) {
                $attrs['checked'] = 'checked';
            }

            $this->element('input', $attrs);

            $this->element('label', array('for' => 'design_background-image_off',
                                          'class' => 'radio'),
                                          // TRANS: Radio button on profile design page that will disable use of the uploaded profile image.
                                          _m('RADIO','Off'));
            // TRANS: Form guide for a set of radio buttons on the profile design page that will enable or disable
            // TRANS: use of the uploaded profile image.
            $this->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->elementEnd('li');

            $this->elementStart('li');
            $this->checkbox('design_background-image_repeat',
                            // TRANS: Checkbox label on profile design page that will cause the profile image to be tiled.
                            _('Tile background image'),
                            ($design->disposition & BACKGROUND_TILE) ? true : false);
            $this->elementEnd('li');
        }

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset', array('id' => 'settings_design_color'));
        // TRANS: Fieldset legend on profile design page to change profile page colours.
        $this->element('legend', null, _('Change colours'));
        $this->elementStart('ul', 'form_data');

        try {
            $bgcolor = new WebColor($design->backgroundcolor);

            $this->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page background colour.
            $this->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->element('input', array('name' => 'design_background',
                                          'type' => 'text',
                                          'id' => 'swatch-1',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->elementEnd('li');

            $ccolor = new WebColor($design->contentcolor);

            $this->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page content colour.
            $this->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->element('input', array('name' => 'design_content',
                                          'type' => 'text',
                                          'id' => 'swatch-2',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => ''));
            $this->elementEnd('li');

            $sbcolor = new WebColor($design->sidebarcolor);

            $this->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page sidebar colour.
            $this->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->element('input', array('name' => 'design_sidebar',
                                        'type' => 'text',
                                        'id' => 'swatch-3',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->elementEnd('li');

            $tcolor = new WebColor($design->textcolor);

            $this->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page text colour.
            $this->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->element('input', array('name' => 'design_text',
                                        'type' => 'text',
                                        'id' => 'swatch-4',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => ''));
            $this->elementEnd('li');

            $lcolor = new WebColor($design->linkcolor);

            $this->elementStart('li');
            // TRANS: Label on profile design page for setting a profile page links colour.
            $this->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->element('input', array('name' => 'design_links',
                                         'type' => 'text',
                                         'id' => 'swatch-5',
                                         'class' => 'swatch',
                                         'maxlength' => '7',
                                         'size' => '7',
                                         'value' => ''));
            $this->elementEnd('li');

        } catch (WebColorException $e) {
            common_log(LOG_ERR, 'Bad color values in design ID: ' .$design->id);
        }

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        // TRANS: Button text on profile design page to immediately reset all colour settings to default.
        $this->submit('defaults', _('Use defaults'), 'submit form_action-default',
            // TRANS: Title for button on profile design page to reset all colour settings to default.
            'defaults', _('Restore default designs'));

        $this->element('input', array('id' => 'settings_design_reset',
                                     'type' => 'reset',
                                     // TRANS: Button text on profile design page to reset all colour settings to default without saving.
                                     'value' => _m('BUTTON','Reset'),
                                     'class' => 'submit form_action-primary',
                                     // TRANS: Title for button on profile design page to reset all colour settings to default without saving.
                                     'title' => _('Reset back to default')));

        // TRANS: Button text on profile design page to save settings.
        $this->submit('save', _m('BUTTON','Save'), 'submit form_action-secondary',
            // TRANS: Title for button on profile design page to save settings.
            'save', _('Save design'));

        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    /**
     * Handle a post
     *
     * Validate input and save changes. Reload the form with a success
     * or error message.
     *
     * @return void
     */
    function handlePost()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // Workaround for PHP returning empty $_POST and $_FILES when POST
            // length > post_max_size in php.ini

            if (empty($_FILES)
                && empty($_POST)
                && ($_SERVER['CONTENT_LENGTH'] > 0)
            ) {
                // TRANS: Form validation error in design settings form. POST should remain untranslated.
                $msg = _m('The server was unable to handle that much POST data (%s byte) due to its current configuration.',
                          'The server was unable to handle that much POST data (%s bytes) due to its current configuration.',
                          intval($_SERVER['CONTENT_LENGTH']));

                $this->showForm(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
                return;
            }
        }

        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->saveDesign();
        } else if ($this->arg('defaults')) {
            $this->restoreDefaults();
        } else {
            // TRANS: Unknown form validation error in design settings form.
            $this->showForm(_('Unexpected form submission.'));
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
        $this->cssLink('js/farbtastic/farbtastic.css',null,'screen, projection, tv');
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */
    function showScripts()
    {
        parent::showScripts();

        $this->script('farbtastic/farbtastic.js');
        $this->script('userdesign.go.js');

        $this->autofocus('design_background-image_file');
    }

    /**
     * Save the background image, if any, and set its disposition
     *
     * @param Design $design a working design to attach the img to
     *
     * @return nothing
     */
    function saveBackgroundImage($design)
    {
        // Now that we have a Design ID we can add a file to the design.
        // XXX: This is an additional DB hit, but figured having the image
        // associated with the Design rather than the User was worth
        // it. -- Zach

        if ($_FILES['design_background-image_file']['error'] ==
            UPLOAD_ERR_OK) {

            $filepath = null;

            try {
                $imagefile =
                    ImageFile::fromUpload('design_background-image_file');
            } catch (Exception $e) {
                $this->showForm($e->getMessage());
                return;
            }

            $filename = Design::filename($design->id,
                image_type_to_extension($imagefile->type),
                    common_timestamp());

            $filepath = Design::path($filename);

            move_uploaded_file($imagefile->filepath, $filepath);

            // delete any old backround img laying around

            if (isset($design->backgroundimage)) {
                @unlink(Design::path($design->backgroundimage));
            }

            $original = clone($design);

            $design->backgroundimage = $filename;

            // default to on, no tile

            $design->setDisposition(true, false, false);

            $result = $design->update($original);

            if ($result === false) {
                common_log_db_error($design, 'UPDATE', __FILE__);
                // TRANS: Error message displayed if design settings could not be saved.
                $this->showForm(_('Couldn\'t update your design.'));
                return;
            }
        }
    }

    /**
     * Restore the user or group design to system defaults
     *
     * @return nothing
     */
    function restoreDefaults()
    {
        $design = $this->getWorkingDesign();

        if (!empty($design)) {

            $result = $design->delete();

            if ($result === false) {
                common_log_db_error($design, 'DELETE', __FILE__);
                // TRANS: Error message displayed if design settings could not be saved after clicking "Use defaults".
                $this->showForm(_('Couldn\'t update your design.'));
                return;
            }
        }

        // TRANS: Success message displayed if design settings were saved after clicking "Use defaults".
        $this->showForm(_('Design defaults restored.'), true);
    }
}
