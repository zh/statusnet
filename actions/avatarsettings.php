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
 * We use jCrop plugin for jQuery to crop the image after upload.
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class AvatarsettingsAction extends AccountSettingsAction
{
    var $mode = null;
    var $imagefile = null;
    var $filename = null;

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
        return _('You can upload your personal avatar.');
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
        if ($this->mode == 'crop') {
            $this->showCropForm();
        } else {
            $this->showUploadForm();
        }
    }

    function showUploadForm()
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
                                          'method' => 'post',
                                          'id' => 'form_settings_avatar',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('avatarsettings')));
        $this->elementStart('fieldset');
        $this->element('legend', null, _('Avatar settings'));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');
        if ($original) {
            $this->elementStart('li', array('id' => 'avatar_original',
                                            'class' => 'avatar_view'));
            $this->element('h2', null, _("Original"));
            $this->elementStart('div', array('id'=>'avatar_original_view'));
            $this->element('img', array('src' => $original->url,
                                        'width' => $original->width,
                                        'height' => $original->height,
                                        'alt' => $user->nickname));
            $this->elementEnd('div');
            $this->elementEnd('li');
        }

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);

        if ($avatar) {
            $this->elementStart('li', array('id' => 'avatar_preview',
                                            'class' => 'avatar_view'));
            $this->element('h2', null, _("Preview"));
            $this->elementStart('div', array('id'=>'avatar_preview_view'));
            $this->element('img', array('src' => $original->url,
                                        'width' => AVATAR_PROFILE_SIZE,
                                        'height' => AVATAR_PROFILE_SIZE,
                                        'alt' => $user->nickname));
            $this->elementEnd('div');
            $this->elementEnd('li');
        }

        $this->elementStart('li', array ('id' => 'settings_attach'));
        $this->element('input', array('name' => 'avatarfile',
                                      'type' => 'file',
                                      'id' => 'avatarfile'));
        $this->element('input', array('name' => 'MAX_FILE_SIZE',
                                      'type' => 'hidden',
                                      'id' => 'MAX_FILE_SIZE',
                                      'value' => MAX_AVATAR_SIZE));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementStart('ul', 'form_actions');
        $this->elementStart('li');
        $this->submit('upload', _('Upload'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->elementEnd('fieldset');
        $this->elementEnd('form');

    }

    function showCropForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        if (!$profile) {
            common_log_db_error($user, 'SELECT', __FILE__);
            $this->serverError(_('User without matching profile'));
            return;
        }

        $original = $profile->getOriginalAvatar();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_avatar',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('avatarsettings')));
        $this->elementStart('fieldset');
        $this->element('legend', null, _('Avatar settings'));
        $this->hidden('token', common_session_token());

        $this->elementStart('ul', 'form_data');

        $this->elementStart('li',
                            array('id' => 'avatar_original',
                                  'class' => 'avatar_view'));
        $this->element('h2', null, _("Original"));
        $this->elementStart('div', array('id'=>'avatar_original_view'));
        $this->element('img', array('src' => common_avatar_url($this->filedata['filename']),
                                    'width' => $this->filedata['width'],
                                    'height' => $this->filedata['height'],
                                    'alt' => $user->nickname));
        $this->elementEnd('div');
        $this->elementEnd('li');

        $this->elementStart('li',
                            array('id' => 'avatar_preview',
                                  'class' => 'avatar_view'));
        $this->element('h2', null, _("Preview"));
        $this->elementStart('div', array('id'=>'avatar_preview_view'));
        $this->element('img', array('src' => common_avatar_url($this->filedata['filename']),
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $user->nickname));
        $this->elementEnd('div');

        foreach (array('avatar_crop_x', 'avatar_crop_y',
                       'avatar_crop_w', 'avatar_crop_h') as $crop_info) {
            $this->element('input', array('name' => $crop_info,
                                          'type' => 'hidden',
                                          'id' => $crop_info));
        }
        $this->submit('crop', _('Crop'));

        $this->elementEnd('li');
        $this->elementEnd('ul');
        $this->elementEnd('fieldset');
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
        try {
            $imagefile = ImageFile::fromUpload('avatarfile');
        } catch (Exception $e) {
            $this->showForm($e->getMessage());
            return;
        }

        $cur = common_current_user();

        $filename = common_avatar_filename($cur->id,
                                           image_type_to_extension($imagefile->type),
                                           null,
                                           'tmp'.common_timestamp());

        $filepath = common_avatar_path($filename);

        move_uploaded_file($imagefile->filename, $filepath);

        $filedata = array('filename' => $filename,
                          'filepath' => $filepath,
                          'width' => $imagefile->width,
                          'height' => $imagefile->height,
                          'type' => $imagefile->type);

        $_SESSION['FILEDATA'] = $filedata;

        $this->filedata = $filedata;

        $this->mode = 'crop';

        $this->showForm(_('Pick a square area of the image to be your avatar'),
                        true);
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

        $filedata = $_SESSION['FILEDATA'];

        if (!$filedata) {
            $this->serverError(_('Lost our file data.'));
            return;
        }

        $x = $this->arg('avatar_crop_x');
        $y = $this->arg('avatar_crop_y');
        $w = ($this->arg('avatar_crop_w')) ? $this->arg('avatar_crop_w') : $filedata['width'];
        $h = ($this->arg('avatar_crop_h')) ? $this->arg('avatar_crop_h') : $filedata['height'];

        $filepath = common_avatar_path($filedata['filename']);

        if (!file_exists($filepath)) {
            $this->serverError(_('Lost our file.'));
            return;
        }

        switch ($filedata['type']) {
        case IMAGETYPE_GIF:
            $image_src = imagecreatefromgif($filepath);
            break;
        case IMAGETYPE_JPEG:
            $image_src = imagecreatefromjpeg($filepath);
            break;
        case IMAGETYPE_PNG:
            $image_src = imagecreatefrompng($filepath);
            break;
         default:
            $this->serverError(_('Unknown file type'));
            return;
        }

        common_debug("W = $w, H = $h, X = $x, Y = $y");

        $image_dest = imagecreatetruecolor($w, $h);

        $background = imagecolorallocate($image_dest, 0, 0, 0);
        ImageColorTransparent($image_dest, $background);
        imagealphablending($image_dest, false);

        imagecopyresized($image_dest, $image_src, 0, 0, $x, $y, $w, $h, $w, $h);

        $cur = common_current_user();

        $filename = common_avatar_filename($cur->id,
                                           image_type_to_extension($filedata['type']),
                                           null,
                                           common_timestamp());

        $filepath = common_avatar_path($filename);

        switch ($filedata['type']) {
        case IMAGETYPE_GIF:
            imagegif($image_dest, $filepath);
            break;
        case IMAGETYPE_JPEG:
            imagejpeg($image_dest, $filepath);
            break;
        case IMAGETYPE_PNG:
            imagepng($image_dest, $filepath);
            break;
         default:
            $this->serverError(_('Unknown file type'));
            return;
        }

        $user = common_current_user();

        $profile = $cur->getProfile();

        if ($profile->setOriginal($filepath)) {
            @unlink(common_avatar_path($filedata['filename']));
            unset($_SESSION['FILEDATA']);
            $this->mode = 'upload';
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
          common_path('theme/base/css/jquery.Jcrop.css?version='.LACONICA_VERSION);

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
