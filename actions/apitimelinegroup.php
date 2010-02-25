<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a group's notices
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiprivateauth.php';

/**
 * Returns the most recent notices (default 20) posted to the group specified by ID
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiTimelineGroupAction extends ApiPrivateAuthAction
{

    var $group   = null;
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

        $this->group   = $this->getTargetGroup($this->arg('id'));

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

        if (empty($this->group)) {
            $this->clientError(_('Group not found!'), 404, $this->format);
            return false;
        }

        $this->notices = $this->getNotices();
        $this->showTimeline();
    }

    /**
     * Show the timeline of notices
     *
     * @return void
     */

    function showTimeline()
    {
        $sitename   = common_config('site', 'name');
        $avatar     = $this->group->homepage_logo;
        $title      = sprintf(_("%s timeline"), $this->group->nickname);
        $taguribase = TagURI::base();
        $id         = "tag:$taguribase:GroupTimeline:" . $this->group->id;

        $subtitle   = sprintf(
            _('Updates from %1$s on %2$s!'),
            $this->group->nickname,
            $sitename
        );

        $logo = ($avatar) ? $avatar : User_group::defaultLogo(AVATAR_PROFILE_SIZE);

        switch($this->format) {
        case 'xml':
            $this->showXmlTimeline($this->notices);
            break;
        case 'rss':
                $this->showRssTimeline(
                $this->notices,
                $title,
                $this->group->homeUrl(),
                $subtitle,
                null,
                $logo
            );
            break;
        case 'atom':

            header('Content-Type: application/atom+xml; charset=utf-8');

            try {

                // If this was called using an integer ID, i.e.: using the canonical
                // URL for this group's feed, then pass the Group object into the feed, 
                // so the OStatus plugin, and possibly other plugins, can access it. 
                // Feels sorta hacky. -- Z

                $atom = null;
                $id = $this->arg('id');

                if (strval(intval($id)) === strval($id)) {
                    $atom = new AtomGroupNoticeFeed($this->group);
                } else {
                    $atom = new AtomGroupNoticeFeed();
                }

                $atom->setId($id);
                $atom->setTitle($title);
                $atom->setSubtitle($subtitle);
                $atom->setLogo($logo);
                $atom->setUpdated('now');

                $atom->addAuthorRaw($this->group->asAtomAuthor());
                $atom->setActivitySubject($this->group->asActivitySubject());

                $atom->addLink($this->group->homeUrl());

                $id = $this->arg('id');
                $aargs = array('format' => 'atom');
                if (!empty($id)) {
                    $aargs['id'] = $id;
                }

                $atom->addLink(
                    $this->getSelfUri('ApiTimelineGroup', $aargs),
                    array('rel' => 'self', 'type' => 'application/atom+xml')
                );

                $atom->addEntryFromNotices($this->notices);

                //$this->raw($atom->getString());
                print $atom->getString(); // temp hack until PuSH feeds are redone cleanly

            } catch (Atom10FeedException $e) {
                $this->serverError(
                    'Could not generate feed for group - ' . $e->getMessage()
                );
                return;
            }

            break;
        case 'json':
            $this->showJsonTimeline($this->notices);
            break;
        default:
            $this->clientError(
                _('API method not found.'),
                404,
                $this->format
            );
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

        $notice = $this->group->getNotices(
            ($this->page-1) * $this->count,
            $this->count,
            $this->since_id,
            $this->max_id,
            $this->since
        );

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
     * Returns an Etag based on the action name, language, group ID and
     * timestamps of the first and last notice in the timeline
     *
     * @return string etag
     */

    function etag()
    {
        if (!empty($this->notices) && (count($this->notices) > 0)) {

            $last = count($this->notices) - 1;

            return '"' . implode(
                ':',
                array($this->arg('action'),
                      common_language(),
                      $this->group->id,
                      strtotime($this->notices[0]->created),
                      strtotime($this->notices[$last]->created))
            )
            . '"';
        }

        return null;
    }

}
