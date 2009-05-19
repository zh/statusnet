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
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/accountsettingsaction.php';



class DesignsettingsAction extends AccountSettingsAction
{
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
        return _('Customize the way your profile looks with a background image and a colour palette of your choice.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for changing the password
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();
        $this->elementStart('form', array('method' => 'POST',
                                          'id' => 'form_settings_design',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('designsettings')));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_design_background-image'));
        $this->element('legend', null, _('Change background image'));
        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->element('label', array('for' => 'design_ background-image_file'), 
                                _('Upload file'));
        $this->element('input', array('name' => 'design_background-image_file',
                                      'type' => 'file',
                                      'id' => 'design_background-image_file'));
        $this->element('p', 'form_guide', _('You can upload your personal background image. The maximum file size is 2Mb.'));
        $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                      'type' => 'hidden',
                                      'id' => 'MAX_FILE_SIZE',
                                      'value' => ImageFile::maxFileSizeInt()));
        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset', array('id' => 'settings_design_color'));
        $this->element('legend', null, _('Change colours'));
        $this->elementStart('ul', 'form_data');

        //This is a JSON object in the DB field. Here for testing. Remove later.
        $userSwatch = '{"body":{"background-color":"#F0F2F5"},
                        "#content":{"background-color":"#FFFFFF"},
                        "#aside_primary":{"background-color":"#CEE1E9"},
                        "html body":{"color":"#000000"},
                        "a":{"color":"#002E6E"}}';

        //Default theme swatch -- Where should this be stored?
        $defaultSwatch = array('body' => array('background-color' => '#F0F2F5'),
                               '#content' => array('background-color' => '#FFFFFF'),
                               '#aside_primary' => array('background-color' => '#CEE1E9'),
                               'html body' => array('color' => '#000000'),
                               'a' => array('color' => '#002E6E'));

        $userSwatch = ($userSwatch) ? json_decode($userSwatch, true) : $defaultSwatch;

        $s = 0;
        $labelSwatch = array('Background',
                             'Content',
                             'Sidebar',
                             'Text',
                             'Links');
        foreach($userSwatch as $propertyvalue => $value) {
            $foo = array_values($value);
            $this->elementStart('li');
            $this->element('label', array('for' => 'swatch-'.$s), _($labelSwatch[$s]));
            $this->element('input', array('name' => 'swatch-'.$s, //prefer swatch[$s] ?
                                          'type' => 'text',
                                          'id' => 'swatch-'.$s,
                                          'class' => 'swatch',
                                          'maxlength' => '7',
                                          'size' => '7',
                                          'value' => $foo[0]));
            $this->elementEnd('li');
            $s++;
        }

        $this->elementEnd('ul');
        $this->elementEnd('fieldset');

        $this->submit('save', _('Save'));
        $this->element('input', array('type' => 'reset',
                                      'value' => 'Reset',
                                      'class' => 'form_action-secondary'));

/*TODO: Check submitted form values: 
json_encode(form values)
if submitted Swatch == DefaultSwatch, don't store in DB.
else store in BD
*/
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
    /*
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        }

        $user = common_current_user();
        assert(!is_null($user)); // should already be checked

        // FIXME: scrub input

        $newpassword = $this->arg('newpassword');
        $confirm     = $this->arg('confirm');

        # Some validation

        if (strlen($newpassword) < 6) {
            $this->showForm(_('Password must be 6 or more characters.'));
            return;
        } else if (0 != strcmp($newpassword, $confirm)) {
            $this->showForm(_('Passwords don\'t match.'));
            return;
        }

        if ($user->password) {
            $oldpassword = $this->arg('oldpassword');

            if (!common_check_user($user->nickname, $oldpassword)) {
                $this->showForm(_('Incorrect old password'));
                return;
            }
        }

        $original = clone($user);

        $user->password = common_munge_password($newpassword, $user->id);

        $val = $user->validate();
        if ($val !== true) {
            $this->showForm(_('Error saving user; invalid.'));
            return;
        }

        if (!$user->update($original)) {
            $this->serverError(_('Can\'t save new password.'));
            return;
        }

        $this->showForm(_('Password saved.'), true);
        */
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
        $farbtasticGo   = common_path('js/farbtastic/farbtastic.go.js');

        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $farbtasticPack));
        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $farbtasticGo));
    }
}
