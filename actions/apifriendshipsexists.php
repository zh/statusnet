<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show whether there is a friendship between two users
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

require_once INSTALLDIR.'/lib/api.php';

/**
 * Tests for the existence of friendship between two users. Will return true if 
 * user_a follows user_b, otherwise will return false.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiFriendshipsExistsAction extends ApiAction
{
    var $user_a = null;
    var $user_b = null;

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

        $user_a_id = $this->trimmed('user_a');
        $user_b_id = $this->trimmed('user_b');

        common_debug("user_a = " . $user_a_id);
        common_debug("user_b = " . $user_b_id);


        $this->user_a = $this->getTargetUser($user_a_id);

        if (empty($this->user_a)) {
            common_debug('gargargra');
        }

        $this->user_b = $this->getTargetUser($user_b_id);

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

        if (empty($this->user_a) || empty($this->user_b)) {
            $this->clientError(
                _('Two user ids or screen_names must be supplied.'),
                400,
                $this->format
            );
            return;
        }

        $result = $this->user_a->isSubscribed($this->user_b);

        switch ($this->format) {
        case 'xml':
            $this->initDocument('xml');
            $this->element('friends', null, $result);
            $this->endDocument('xml');
            break;
        case 'json':
            $this->initDocument('json');
            print json_encode($result);
            $this->endDocument('json');
            break;
        default:
            break;
        }
    }

}
