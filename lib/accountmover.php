<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A class for moving an account to a new server
 * 
 * PHP version 5
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
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Moves an account from this server to another
 *
 * @category  Account
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class AccountMover
{
    private $_user    = null;
    private $_profile = null;
    private $_remote  = null;
    private $_sink    = null;
    
    function __construct($user, $remote, $password)
    {
        $this->_user    = $user;
        $this->_profile = $user->getProfile();

        $remote = Discovery::normalize($remote);

        $oprofile = Ostatus_profile::ensureProfileURI($remote);

        if (empty($oprofile)) {
            throw new Exception("Can't locate account {$remote}");
        }

        $this->_remote = $oprofile->localProfile();

        list($svcDocUrl, $username) = self::getServiceDocument($remote);

        $this->_sink = new ActivitySink($svcDocUrl, $username, $password);
    }

    static function getServiceDocument($remote)
    {
        $discovery = new Discovery();

        $xrd = $discovery->lookup($remote);

        if (empty($xrd)) {
            throw new Exception("Can't find XRD for $remote");
        } 

        $svcDocUrl = null;
        $username  = null;

        foreach ($xrd->links as $link) {
            if ($link['rel'] == 'http://apinamespace.org/atom' &&
                $link['type'] == 'application/atomsvc+xml') {
                $svcDocUrl = $link['href'];
                if (!empty($link['property'])) {
                    foreach ($link['property'] as $property) {
                        if ($property['type'] == 'http://apinamespace.org/atom/username') {
                            $username = $property['value'];
                            break;
                        }
                    }
                }
                break;
            }
        }

        if (empty($svcDocUrl)) {
            throw new Exception("No AtomPub API service for $remote.");
        }

        return array($svcDocUrl, $username);
    }

    function move()
    {
        $stream = new UserActivityStream($this->_user);

        $acts = array_reverse($stream->activities);

        // Reverse activities to run in correct chron order

        foreach ($acts as $act) {
            $this->_moveActivity($act);
        }
    }

    private function _moveActivity($act)
    {
        switch ($act->verb) {
        case ActivityVerb::FAVORITE:
            // push it, then delete local
            $this->_sink->postActivity($act);
            $notice = Notice::staticGet('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $fave = Fave::pkeyGet(array('user_id' => $this->_user->id,
                                            'notice_id' => $notice->id));
                $fave->delete();
            }
            break;
        case ActivityVerb::POST:
            // XXX: send a reshare, not a post
            common_log(LOG_INFO, "Pushing notice {$act->objects[0]->id} to {$this->_remote->getURI()}");
            $this->_sink->postActivity($act);
            $notice = Notice::staticGet('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $notice->delete();
            }
            break;
        case ActivityVerb::JOIN:
            $this->_sink->postActivity($act);
            $group = User_group::staticGet('uri', $act->objects[0]->id);
            if (!empty($group)) {
                Group_member::leave($group->id, $this->_user->id);
            }
            break;
        case ActivityVerb::FOLLOW:
            if ($act->actor->id == $this->_user->uri) {
                $this->_sink->postActivity($act);
                $other = Profile::fromURI($act->objects[0]->id);
                if (!empty($other)) {
                    Subscription::cancel($this->_profile, $other);
                }
            } else {
                $otherUser = User::staticGet('uri', $act->actor->id);
                if (!empty($otherUser)) {
                    $otherProfile = $otherUser->getProfile();
                    Subscription::start($otherProfile, $this->_remote);
                    Subscription::cancel($otherProfile, $this->_user->getProfile());
                } else {
                    // It's a remote subscription. Do something here!
                }
            }
            break;
        }
    }

    /**
     * Log some data
     * 
     * Add a header for our class so we know who did it.
     *
     * @param int    $level   Log level, like LOG_ERR or LOG_INFO
     * @param string $message Message to log
     *
     * @return void
     */

    protected function log($level, $message)
    {
        common_log($level, "AccountMover: " . $message);
    }
}
