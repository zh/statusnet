<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a user's profile information
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

require_once INSTALLDIR.'/lib/twitterapi.php';

/**
 * Ouputs information for a user, specified by ID or screen name.
 * The user's most recent status will be returned inline.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiUserShowAction extends TwitterApiAction
{

    var $format = null;
    var $user   = null;

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

        $this->format = $this->arg('format');

        $email = $this->arg('email');

        // XXX: email field deprecated in Twitter's API

        if (!empty($email)) {
            $this->user = User::staticGet('email', $email);
        } else {
            $this->user = $this->getTargetUser($this->arg('id'));
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Check the format and show the user info
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if (empty($this->user)) {
            $this->clientError(_('Not found.'), 404, $this->format);
            return;
        }

        if (!in_array($this->format, array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        $profile = $this->user->getProfile();

        if (empty($profile)) {
            $this->clientError(_('User has no profile.'));
            return;
        }

        $twitter_user = $this->twitter_user_array($this->user->getProfile(), true);

        if ($this->format == 'xml') {
            $this->init_document('xml');
            $this->show_twitter_xml_user($twitter_user);
            $this->end_document('xml');
        } elseif ($this->format == 'json') {
            $this->init_document('json');
            $this->show_json_objects($twitter_user);
            $this->end_document('json');
        }

    }

}
