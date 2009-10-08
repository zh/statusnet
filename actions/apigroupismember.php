<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Check to see whether a user a member of a group
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

require_once INSTALLDIR . '/lib/apibareauth.php';

/**
 * Returns whether a user is a member of a specified group.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiGroupIsMemberAction extends ApiBareAuthAction
{
    var $format  = null;
    var $user    = null;
    var $group   = null;

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

        if ($this->requiresAuth()) {
            if ($this->checkBasicAuthUser() == false) {
                return;
            }
        }

        $this->user   = $this->getTargetUser(null);
        $this->group  = $this->getTargetGroup(null);
        $this->format = $this->arg('format');

        return true;
    }

    /**
     * Handle the request
     *
     * Save the new message
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if (empty($this->user)) {
            $this->clientError(_('No such user!'), 404, $this->format);
            return;
        }

        if (empty($this->group)) {
            $this->clientError('Group not found!', 404, $this->format);
            return false;
        }

        $is_member = $this->user->isMember($this->group);

        switch($this->format) {
        case 'xml':
            $this->init_document('xml');
            $this->element('is_member', null, $is_member);
            $this->end_document('xml');
            break;
        case 'json':
            $this->init_document('json');
            $this->show_json_objects(array('is_member' => $is_member));
            $this->end_document('json');
            break;
        default:
            $this->clientError(
                _('API method not found!'),
                400,
                $this->format
            );
            break;
        }
    }

}
