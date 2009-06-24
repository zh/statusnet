<?php
/**
 * Laconica, the distributed open-source microblogging tool
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
 * @package   Laconica
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/accountsettingsaction.php';
require_once INSTALLDIR . '/lib/webcolor.php';

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
        return _('Profile design');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Customize the way your profile looks ' .
        'with a background image and a colour palette of your choice.');
    }

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
        $this->element('legend', null, _('Change background image'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('label', array('for' => 'design_background-image_file'),
                                _('Upload file'));
        $this->element('input', array('name' => 'design_background-image_file',
                                      'type' => 'file',
                                      'id' => 'design_background-image_file'));
        $this->element('p', 'form_guide', _('You can upload your personal ' .
            'background image. The maximum file size is 2Mb.'));
        $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                      'type' => 'hidden',
                                      'id' => 'MAX_FILE_SIZE',
                                      'value' => ImageFile::maxFileSizeInt()));
        $this->elementEnd('li');

        if (!empty($design->backgroundimage)) {

            $this->elementStart('li', array('id' => 'design_background-image_onoff'));

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
                                          _('On'));

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
                                          _('Off'));
            $this->element('p', 'form_guide', _('Turn background image on or off.'));
            $this->elementEnd('li');

            $this->elementStart('li');
            $this->checkbox('design_background-image_repeat',
                            _('Tile background image'),
                            ($design->disposition & BACKGROUND_TILE) ? true : false );
            $this->elementEnd('li');
        }

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset', array('id' => 'settings_design_color'));
        $this->element('legend', null, _('Change colours'));
        $this->elementStart('ul', 'form_data');

        try {

            $bgcolor = new WebColor($design->backgroundcolor);

            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-1'), _('Background'));
            $this->element('input', array('name' => 'design_background',
                                          'type' => 'text',
                                          'id' => 'swatch-1',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => '#' . $bgcolor->hexValue()));
            $this->elementEnd('li');

            $ccolor = new WebColor($design->contentcolor);

            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-2'), _('Content'));
            $this->element('input', array('name' => 'design_content',
                                          'type' => 'text',
                                          'id' => 'swatch-2',
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => '#' . $ccolor->hexValue()));
            $this->elementEnd('li');

            $sbcolor = new WebColor($design->sidebarcolor);

            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-3'), _('Sidebar'));
            $this->element('input', array('name' => 'design_sidebar',
                                        'type' => 'text',
                                        'id' => 'swatch-3',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => '#' . $sbcolor->hexValue()));
            $this->elementEnd('li');

            $tcolor = new WebColor($design->textcolor);

            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-4'), _('Text'));
            $this->element('input', array('name' => 'design_text',
                                        'type' => 'text',
                                        'id' => 'swatch-4',
                                        'class' => 'swatch',
                                        'maxlength' => '7',
                                        'size' => '7',
                                        'value' => '#' . $tcolor->hexValue()));
            $this->elementEnd('li');

            $lcolor = new WebColor($design->linkcolor);

            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-5'), _('Links'));
            $this->element('input', array('name' => 'design_links',
                                         'type' => 'text',
                                         'id' => 'swatch-5',
                                         'class' => 'swatch',
                                         'maxlength' => '7',
                                         'size' => '7',
                                         'value' => '#' . $lcolor->hexValue()));

           $this->elementEnd('li');

       } catch (WebColorException $e) {
           common_log(LOG_ERR, 'Bad color values in design ID: ' .
               $design->id);
       }

       $this->elementEnd('ul');
       $this->elementEnd('fieldset');

       $this->element('input', array('id' => 'settings_design_reset',
                                     'type' => 'reset',
                                     'value' => 'Reset',
                                     'class' => 'submit form_action-primary',
                                     'title' => _('Reset back to default')));

        $this->submit('save', _('Save'), 'submit form_action-secondary',
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
        // XXX: Robin's workaround for a bug in PHP where $_POST
        // and $_FILE are empty in the case that the uploaded
        // file is bigger than PHP is configured to handle.

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (empty($_POST) && $_SERVER['CONTENT_LENGTH']) {

                $msg = _('The server was unable to handle that much POST ' .
                    'data (%s bytes) due to its current configuration.');

                $this->showForm(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
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
        } else if ($this->arg('reset')) {
            $this->resetDesign();
        } else {
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
        $farbtasticStyle =
          common_path('theme/base/css/farbtastic.css?version='.LACONICA_VERSION);

        $this->element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => $farbtasticStyle,
                                     'media' => 'screen, projection, tv'));
    }

    /**
     * Add the Farbtastic scripts
     *
     * @return void
     */

    function showScripts()
    {
        parent::showScripts();

        $farbtasticPack = common_path('js/farbtastic/farbtastic.js');
        $userDesignGo   = common_path('js/userdesign.go.js');

        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $farbtasticPack));
        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $userDesignGo));
    }

    /**
     * Get a default user design
     *
     * @return Design design
     */

    function defaultDesign()
    {
        $defaults = common_config('site', 'design');

        $design = new Design();

        try {

            $color = new WebColor();

            $color->parseColor($defaults['backgroundcolor']);
            $design->backgroundcolor = $color->intValue();

            $color->parseColor($defaults['contentcolor']);
            $design->contentcolor = $color->intValue();

            $color->parseColor($defaults['sidebarcolor']);
            $design->sidebarcolor = $color->intValue();

            $color->parseColor($defaults['textcolor']);
            $design->textcolor = $color->intValue();

            $color->parseColor($defaults['linkcolor']);
            $design->linkcolor = $color->intValue();

            $design->backgroundimage = $defaults['backgroundimage'];

            $design->disposition = $defaults['disposition'];

        } catch (WebColorException $e) {
            common_log(LOG_ERR, _('Bad default color settings: ' .
                $e->getMessage()));
        }

        return $design;
    }

    function saveBackgroundImage($design) {

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

            $original = clone($design);
            $design->backgroundimage = $filename;

            // default to on, no tile

            $design->setDisposition(true, false, false);

            $result = $design->update($original);

            if ($result === false) {
                common_log_db_error($design, 'UPDATE', __FILE__);
                $this->showForm(_('Couldn\'t update your design.'));
                return;
            }
        }
    }

}
