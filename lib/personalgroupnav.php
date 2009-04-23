<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for all actions (~views)
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
 * @category  Action
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

require_once INSTALLDIR.'/lib/widget.php';

/**
 * Base class for all actions
 *
 * This is the base class for all actions in the package. An action is
 * more or less a "view" in an MVC framework.
 *
 * Actions are responsible for extracting and validating parameters; using
 * model classes to read and write to the database; and doing ouput.
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      HTMLOutputter
 */

class PersonalGroupNav extends Widget
{
    var $action = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */

    function __construct($action=null)
    {
        parent::__construct($action);
        $this->action = $action;
    }

    /**
     * Show the menu
     *
     * @return void
     */

    function show()
    {
        $user = null;
	
	// FIXME: we should probably pass this in
	
        $action = $this->action->trimmed('action');
        $nickname = $this->action->trimmed('nickname');

        if ($nickname) {
            $user = User::staticGet('nickname', $nickname);
            $user_profile = $user->getProfile();
        } else {
            $user_profile = false;
        }

        $this->out->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartPersonalGroupNav', array($this))) {
            $this->out->menuItem(common_local_url('all', array('nickname' =>
                                                           $nickname)),
                             _('Personal'),
                             sprintf(_('%s and friends'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                             $action == 'all', 'nav_timeline_personal');
            $this->out->menuItem(common_local_url('replies', array('nickname' =>
                                                                  $nickname)),
                             _('Replies'),
                             sprintf(_('Replies to %s'), (($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname)),
                             $action == 'replies', 'nav_timeline_replies');
            $this->out->menuItem(common_local_url('showstream', array('nickname' =>
                                                                  $nickname)),
                             _('Profile'),
                             ($user_profile && $user_profile->fullname) ? $user_profile->fullname : $nickname,
                             $action == 'showstream', 'nav_profile');
            $this->out->menuItem(common_local_url('showfavorites', array('nickname' =>
                                                                  $nickname)),
                             _('Favorites'),
                             sprintf(_('%s\'s favorite notices'), ($user_profile) ? $user_profile->getBestName() : _('User')),
                             $action == 'showfavorites', 'nav_timeline_favorites');

            $cur = common_current_user();

            if ($cur && $cur->id == $user->id) {

                $this->out->menuItem(common_local_url('inbox', array('nickname' =>
                                                                         $nickname)),
                                 _('Inbox'),
                                 _('Your incoming messages'),
                                 $action == 'inbox');
                $this->out->menuItem(common_local_url('outbox', array('nickname' =>
                                                                         $nickname)),
                                 _('Outbox'),
                                 _('Your sent messages'),
                                 $action == 'outbox');
            }
            Event::handle('EndPersonalGroupNav', array($this));
        }
        $this->out->elementEnd('ul');
    }
}
