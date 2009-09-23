<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show the friends timeline
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

class ApifriendstimelineAction extends TwitterapiAction
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
        return true;

    }

    function handle($args) {

        parent::handle($args);
        common_debug(var_export($args, true));

        if ($this->requiresAuth()) {
            if ($this->showBasicAuthHeader()) {
                $this->showTimeline();
            }
        } else {
            $this->showTimeline();
        }
    }

    function showTimeline()
    {
        common_debug('Auth user = ' . var_export($this->auth_user, true));

        $user = $this->getTargetUser($this->arg('id'));

        if (empty($user)) {
            $this->clientError(_('No such user!'), 404, $this->arg('format'));
            return;
        }

        $profile    = $user->getProfile();
        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s and friends"), $user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:FriendsTimeline:" . $user->id;
        $link       = common_local_url('all',
            array('nickname' => $user->nickname));
        $subtitle   = sprintf(_('Updates from %1$s and friends on %2$s!'),
            $user->nickname, $sitename);

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        if (!empty($this->auth_user) && $this->auth_user->id == $user->id) {
            $notice = $user->noticeInbox(($page-1)*$count,
                $count, $since_id, $max_id, $since);
        } else {
            $notice = $user->noticesWithFriends(($page-1)*$count,
                $count, $since_id, $max_id, $since);
        }

        switch($this->arg('format')) {
        case 'xml':
            $this->show_xml_timeline($notice);
            break;
        case 'rss':
            $this->show_rss_timeline($notice, $title, $link, $subtitle);
            break;
        case 'atom':

            $target_id = $this->arg('id');

            if (isset($target_id)) {
                $selfuri = common_root_url() .
                    'api/statuses/friends_timeline/' .
                        $target_id . '.atom';
            } else {
                $selfuri = common_root_url() .
                    'api/statuses/friends_timeline.atom';
            }
            $this->show_atom_timeline($notice, $title, $id, $link,
                $subtitle, null, $selfuri);
            break;
        case 'json':
            $this->show_json_timeline($notice);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    function requiresAuth()
    {
        return true;
    }

    /**
     * Is this page read-only?
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this page last modified?
     *
     */

    function lastModified()
    {

    }

}