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

class ApiGroupListAction extends ApiBareAuthAction
{
    var $user     = null;
    var $page     = null;
    var $count    = null;
    var $max_id   = null;
    var $since_id = null;
    var $since    = null;
    var $groups   = null;

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
        
        $this->user   = $this->getTargetUser($id);
        $this->groups = $this->getGroups();

        return true;
    }

    /**
     * Handle the request
     *
     * Show the user's groups
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

        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s's groups"), $this->user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:Groups";
        $link       = common_local_url(
            'usergroups',
            array('nickname' => $this->user->nickname)
        );
        $subtitle   = sprintf(
            _("Groups %s is a member of on %s."),
            $this->user->nickname,
            $sitename
        );

        switch($this->format) {
        case 'xml':
            $this->show_xml_groups($this->groups);
            break;
        case 'rss':
            $this->show_rss_groups($this->groups, $title, $link, $subtitle);
            break;
        case 'atom':
            $selfuri = common_root_url() . 'api/statusnet/groups/list/' .
                $this->user->id . '.atom';
            $this->show_atom_groups(
                $this->groups,
                $title,
                $id,
                $link,
                $subtitle,
                $selfuri
            );
            break;
        case 'json':
            $this->show_json_groups($this->groups);
            break;
        default:
            $this->clientError(
                _('API method not found!'),
                404,
                $this->format
            );
            break;
        }

    }

    /**
     * Get groups
     *
     * @return array groups
     */

    function getGroups()
    {
        $groups = array();

        $group = $this->user->getGroups(
            ($this->page - 1) * $this->count,
            $this->count,
            $this->since_id,
            $this->max_id,
            $this->since
        );

        while ($group->fetch()) {
            $groups[] = clone($group);
        }

        return $groups;
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
     * @return string datestamp of the latest group the user has joined
     */

    function lastModified()
    {
        if (!empty($this->groups) && (count($this->groups) > 0)) {
            return strtotime($this->groups[0]->created);
        }

        return null;
    }

    /**
     * An entity tag for this list of groups
     *
     * Returns an Etag based on the action name, language, user ID and
     * timestamps of the first and last group the user has joined
     *
     * @return string etag
     */

    function etag()
    {
        if (!empty($this->groups) && (count($this->groups) > 0)) {

            $last = count($this->groups) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->user->id,
                      strtotime($this->groups[0]->created),
                      strtotime($this->groups[$last]->created))
            )
            . '"';
        }

        return null;
    }

}
