<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Update the authenticating user's profile image
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
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';

/**
 * Updates the authenticating user's profile image. Note that this API method
 * expects raw multipart data, not a URL to an image.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiAccountUpdateProfileImageAction extends ApiAuthAction
{

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = $this->auth_user;

        return true;
    }

    /**
     * Handle the request
     *
     * Check whether the credentials are valid and output the result
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
             $msg = _('The server was unable to handle that much POST ' .
                    'data (%s bytes) due to its current configuration.');

            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        if (empty($this->user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
            return;
        }

        try {
            $imagefile = ImageFile::fromUpload('image');
        } catch (Exception $e) {
            $this->clientError($e->getMessage(), 400, $this->format);
            return;
        }

        $filename = Avatar::filename(
            $user->id,
            image_type_to_extension($imagefile->type),
            null,
            'tmp'.common_timestamp()
        );

        $filepath = Avatar::path($filename);

        move_uploaded_file($imagefile->filepath, $filepath);

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            $this->clientError(_('User has no profile.'));
            return;
        }

        $profile->setOriginal($filename);

        common_broadcast_profile($profile);

        $twitter_user = $this->twitterUserArray($profile, true);

        if ($this->format == 'xml') {
            $this->initDocument('xml');
            $this->showTwitterXmlUser($twitter_user);
            $this->endDocument('xml');
        } elseif ($this->format == 'json') {
            $this->initDocument('json');
            $this->showJsonObjects($twitter_user);
            $this->endDocument('json');
        }
    }

}
