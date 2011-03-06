<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * Subscription action.
 *
 * This program is free software: you can redistribute it and/or modify
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
 * PHP version 5
 *
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/peopletageditform.php';

/**
 * Subscription action
 *
 * Subscribing to a profile. Does not work for OMB 0.1 remote subscriptions,
 * but may work for other remote subscription protocols, like OStatus.
 *
 * Takes parameters:
 *
 *    - subscribeto: a profile ID
 *    - token: session token to prevent CSRF attacks
 *    - ajax: boolean; whether to return Ajax or full-browser results
 *
 * Only works if the current user is logged in.
 *
 * @category  Action
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

class ProfilecompletionAction extends Action
{
    var $user;
    var $peopletag;
    var $field;
    var $msg;

    /**
     * Check pre-requisites and instantiate attributes
     *
     * @param Array $args array of arguments (URL, GET, POST)
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        // CSRF protection

        $token = $this->trimmed('token');

        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token.'.
                                 ' Try again, please.'));
            return false;
        }

        // Only for logged-in users

        $this->user = common_current_user();

        if (empty($this->user)) {
            $this->clientError(_('Not logged in.'));
            return false;
        }

        $id = $this->arg('peopletag_id');
        $this->peopletag = Profile_list::staticGet('id', $id);

        if (empty($this->peopletag)) {
            $this->clientError(_('No such peopletag.'));
            return false;
        }

        $field = $this->arg('field');
        if (!in_array($field, array('fulltext', 'nickname', 'fullname', 'description', 'location', 'uri'))) {
            $this->clientError(sprintf(_('Unidentified field %s'), htmlspecialchars($field)), 404);
            return false;
        }
        $this->field = $field;

        return true;
    }

    /**
     * Handle request
     *
     * Does the subscription and returns results.
     *
     * @param Array $args unused.
     *
     * @return void
     */

    function handle($args)
    {
        $this->msg = null;

        $this->startHTML('text/xml;charset=utf-8');
        $this->elementStart('head');
        $this->element('title', null, _('Search results'));
        $this->elementEnd('head');
        $this->elementStart('body');
        $profiles = $this->getResults();

        if ($this->msg !== null) {
            $this->element('p', 'error', $this->msg);
        } else {
            if (count($profiles) > 0) {
                $this->elementStart('ul', array('id' => 'profile_search_results', 'class' => 'profile-lister'));
                foreach ($profiles as $profile) {
                    $this->showProfileItem($profile);
                }
                $this->elementEnd('ul');
            } else {
                $this->element('p', 'error', _('No results.'));
            }
        }
        $this->elementEnd('body');
        $this->elementEnd('html');
    }

    function getResults()
    {
        $profiles = array();
        $q = $this->arg('q');
        $q = strtolower($q);
        if (strlen($q) < 3) {
            $this->msg = _('The search string must be atleast 3 characters long');
        }
        $page = $this->arg('page');
        $page = (int) (empty($page) ? 1 : $page);

        $profile = new Profile();
        $search_engine = $profile->getSearchEngine('profile');

        if (Event::handle('StartProfileCompletionSearch', array($this, &$profile, $search_engine))) {
            $search_engine->set_sort_mode('chron');
            $search_engine->limit((($page-1)*PROFILES_PER_PAGE), PROFILES_PER_PAGE + 1);

            if (false === $search_engine->query($q)) {
                $cnt = 0;
            }
            else {
                $cnt = $profile->find();
            }
            Event::handle('EndProfileCompletionSearch', $this, &$profile, $search_engine);
        }

        while ($profile->fetch()) {
            $profiles[] = clone($profile);
        }
        return $this->filter($profiles);
    }

    function filter($profiles)
    {
        $current = $this->user->getProfile();
        $filtered_profiles = array();
        foreach ($profiles as $profile) {
            if ($current->canTag($profile)) {
                $filtered_profiles[] = $profile;
            }
        }
        return $filtered_profiles;
    }

    function showProfileItem($profile)
    {
        $this->elementStart('li', 'entity_removable_profile');
        $item = new TaggedProfileItem($this, $profile);
        $item->show();
        $this->elementStart('span', 'entity_actions');

        if ($profile->isTagged($this->peopletag)) {
            $untag = new UntagButton($this, $profile, $this->peopletag);
            $untag->show();
        } else {
            $tag = new TagButton($this, $profile, $this->peopletag);
            $tag->show();
        }

        $this->elementEnd('span');
        $this->elementEnd('li');
    }
}
