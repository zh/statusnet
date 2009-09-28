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

require_once INSTALLDIR.'/lib/apibareauth.php';

/**
 * Returns the most recent notices (default 20) posted by the target user.
 * This is the equivalent of 'You and friends' page accessed via Web.
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiFriendsTimelineAction extends ApiBareAuthAction
{

    var $user    = null;
    var $notices = null;

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

        $this->page     = (int)$this->arg('page', 1);
        $this->count    = (int)$this->arg('count', 20);
        $this->max_id   = (int)$this->arg('max_id', 0);
        $this->since_id = (int)$this->arg('since_id', 0);
        $this->since    = $this->arg('since');

        if ($this->requiresAuth()) {
            if ($this->checkBasicAuthUser() == false) {
                return;
            }
        }

        $this->user = $this->getTargetUser($this->arg('id'));

        if (empty($this->user)) {
            $this->clientError(_('No such user!'), 404, $this->arg('format'));
            return;
        }

        $this->notices = $this->getNotices();

        return true;
    }

    /**
     * Handle the request
     *
     * Just show the notices
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        $this->showTimeline();
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */

    function showTimeline()
    {
        $profile    = $this->user->getProfile();
        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s and friends"), $this->user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:FriendsTimeline:" . $this->user->id;
        $link       = common_local_url(
            'all', array('nickname' => $this->user->nickname)
        );
        $subtitle   = sprintf(
            _('Updates from %1$s and friends on %2$s!'),
            $this->user->nickname, $sitename
        );

        switch($this->arg('format')) {
        case 'xml':
            $this->show_xml_timeline($this->notices);
            break;
        case 'rss':
            $this->show_rss_timeline($this->notices, $title, $link, $subtitle);
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

            $this->show_atom_timeline(
                $this->notices, $title, $id, $link,
                $subtitle, null, $selfuri
            );
            break;
        case 'json':
            $this->show_json_timeline($this->notices);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
            break;
        }
    }

    /**
     * Get notices
     *
     * @return array notices
     */

    function getNotices()
    {
        $notices = array();

        if (!empty($this->auth_user) && $this->auth_user->id == $this->user->id) {
            $notice = $this->user->noticeInbox(
                ($this->page-1) * $this->count,
                $this->count, $this->since_id,
                $this->max_id, $this->since
            );
        } else {
            $notice = $this->user->noticesWithFriends(
                ($this->page-1) * $this->count,
                $this->count, $this->since_id,
                $this->max_id, $this->since
            );
        }

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        return $notices;
    }

    /**
     * Is this action read only?
     *
     * @param array $args other arguments
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * When was this feed last modified?
     *
     * @return string datestamp of the latest notice in the stream
     */

    function lastModified()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {
            return strtotime($this->notices[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this stream
     *
     * Returns an Etag based on the action name, language, user ID, and
     * timestamps of the first and last notice in the timeline
     *
     * @return string etag
     */

    function etag()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {

            $last = count($this->notices) - 1;

            return implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->user->id,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }

}
