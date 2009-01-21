<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Upload an avatar
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
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/accountsettingsaction.php';

/**
 * Upload an avatar
 *
 * We use jQuery to crop the image after upload.
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class AvatarsettingsAction extends AccountSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('Avatar');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Set your personal avatar.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for uploading an avatar.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            $this->serverError(_('User without matching profile'));
            return;
        }

        $original = $profile->getOriginalAvatar();

        $this->elementStart('form', array('enctype' => 'multipart/form-data',
                                          'method' => 'POST',
                                          'id' => 'avatar',
                                          'action' =>
                                          common_local_url('avatarsettings')));
        $this->hidden('token', common_session_token());

        if ($original) {
            $this->elementStart('div',
                                array('id' => 'avatar_original',
                                      'class' => 'avatar_view'));
            $this->element('h3', null, _("Original:"));
            $this->elementStart('div', array('id'=>'avatar_original_view'));
            $this->element('img', array('src' => $original->url,
                                        'class' => 'avatar original',
                                        'width' => $original->width,
                                        'height' => $original->height,
                                        'alt' => $user->nickname));
            $this->elementEnd('div');
            $this->elementEnd('div');
        }

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);

        if ($avatar) {
            $this->elementStart('div',
                                array('id' => 'avatar_preview',
                                      'class' => 'avatar_view'));
            $this->element('h3', null, _("Preview:"));
            $this->elementStart('div', array('id'=>'avatar_preview_view'));
            $this->element('img', array('src' => $original->url,//$avatar->url,
                                        'class' => 'avatar profile',
                                        'width' => AVATAR_PROFILE_SIZE,
                                        'height' => AVATAR_PROFILE_SIZE,
                                        'alt' => $user->nickname));
            $this->elementEnd('div');
            $this->elementEnd('div');

            foreach (array('avatar_crop_x', 'avatar_crop_y',
                           'avatar_crop_w', 'avatar_crop_h') as $crop_info) {
                $this->element('input', array('name' => $crop_info,
                                              'type' => 'hidden',
                                              'id' => $crop_info));
            }
            $this->submit('crop', _('Crop'));
        }

        $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                      'type' => 'hidden',
                                      'id' => 'MAX_FILE_SIZE',
                                      'value' => MAX_AVATAR_SIZE));

        $this->elementStart('p');

        $this->element('input', array('name' => 'avatarfile',
                                      'type' => 'file',
                                      'id' => 'avatarfile'));
        $this->elementEnd('p');

        $this->submit('upload', _('Upload'));
        $this->elementEnd('form');

    }

    /**
     * Handle a post
     *
     * We mux on the button name to figure out what the user actually wanted.
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. '.
                               'Try again, please.'));
            return;
        }

        if ($this->arg('upload')) {
            $this->uploadAvatar();
        } else if ($this->arg('crop')) {
            $this->cropAvatar();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Handle an image upload
     *
     * Does all the magic for handling an image upload, and crops the
     * image by default.
     *
     * @return void
     */

    function uploadAvatar()
    {
        switch ($_FILES['avatarfile']['error']) {
        case UPLOAD_ERR_OK: // success, jump out
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $this->showForm(_('That file is too big.'));
            return;
        case UPLOAD_ERR_PARTIAL:
            @unlink($_FILES['avatarfile']['tmp_name']);
            $this->showForm(_('Partial upload.'));
            return;
        default:
            $this->showForm(_('System error uploading file.'));
            return;
        }

        $info = @getimagesize($_FILES['avatarfile']['tmp_name']);

        if (!$info) {
            @unlink($_FILES['avatarfile']['tmp_name']);
            $this->showForm(_('Not an image or corrupt file.'));
            return;
        }

        switch ($info[2]) {
        case IMAGETYPE_GIF:
        case IMAGETYPE_JPEG:
        case IMAGETYPE_PNG:
            break;
        default:
            $this->showForm(_('Unsupported image file format.'));
            return;
        }

        $user = common_current_user();

        $profile = $user->getProfile();

        if ($profile->setOriginal($_FILES['avatarfile']['tmp_name'])) {
            $this->showForm(_('Avatar updated.'), true);
        } else {
            $this->showForm(_('Failed updating avatar.'));
        }

        @unlink($_FILES['avatarfile']['tmp_name']);
    }

    /**
     * Handle the results of jcrop.
     *
     * @return void
     */

    function cropAvatar()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $x = $this->arg('avatar_crop_x');
        $y = $this->arg('avatar_crop_y');
        $w = $this->arg('avatar_crop_w');
        $h = $this->arg('avatar_crop_h');

        if ($profile->crop_avatars($x, $y, $w, $h)) {
            $this->showForm(_('Avatar updated.'), true);
        } else {
            $this->showForm(_('Failed updating avatar.'));
        }
    }

    /**
     * Add the jCrop stylesheet
     *
     * @return void
     */

    function showStylesheets()
    {
        parent::showStylesheets();
        $jcropStyle =
          common_path('js/jcrop/jquery.Jcrop.css?version='.LACONICA_VERSION);

        $this->element('link', array('rel' => 'stylesheet',
                                     'type' => 'text/css',
                                     'href' => $jcropStyle,
                                     'media' => 'screen, projection, tv'));
    }

    /**
     * Add the jCrop scripts
     *
     * @return void
     */

    function showScripts()
    {
        parent::showScripts();

        $jcropPack = common_path('js/jcrop/jquery.Jcrop.pack.js');
        $jcropGo   = common_path('js/jcrop/jquery.Jcrop.go.js');

        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $jcropPack));
        $this->element('script', array('type' => 'text/javascript',
                                       'src' => $jcropGo));
    }
}
