<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for deleting things
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
 * @category  Personal
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
     exit(1);
}

class DeleteAction extends Action
{
    var $user         = null;
    var $notice       = null;
    var $profile      = null;
    var $user_profile = null;

    function prepare($args)
    {
        parent::prepare($args);

        $this->user   = common_current_user();
        $notice_id    = $this->trimmed('notice');
        $this->notice = Notice::staticGet($notice_id);

        if (!$this->notice) {
            common_user_error(_('No such notice.'));
            exit;
        }

        $this->profile      = $this->notice->getProfile();
        $this->user_profile = $this->user->getProfile();

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            common_user_error(_('Not logged in.'));
            exit;
        } else if ($this->notice->profile_id != $this->user_profile->id) {
            common_user_error(_('Can\'t delete this notice.'));
            exit;
        }
    }

}
