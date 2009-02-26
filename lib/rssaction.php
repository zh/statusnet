<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for RSS 1.0 feed actions
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
 * @category  Mail
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Earle Martin <earle@downlode.org>
 * @copyright 2008-9 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) { exit(1); }

define('DEFAULT_RSS_LIMIT', 48);

class Rss10Action extends Action
{
    # This will contain the details of each feed item's author and be used to generate SIOC data.

    var $creators = array();
    var $limit = DEFAULT_RSS_LIMIT;
    var $notices = null;

    /**
     * Constructor
     *
     * Just wraps the Action constructor.
     *
     * @param string  $output URI to output to, default = stdout
     * @param boolean $indent Whether to indent output, default true
     *
     * @see Action::__construct
     */

    function __construct($output='php://output', $indent=true)
    {
        parent::__construct($output, $indent);
    }

    /**
     * Do we need to write to the database?
     *
     * @return boolean true
     */

    function isReadonly()
    {
        return true;
    }

    /**
     * Read arguments and initialize members
     *
     * @param array $args Arguments from $_REQUEST
     * @return boolean success
     */

    function prepare($args)
    {
        parent::prepare($args);
        $this->limit = (int) $this->trimmed('limit');
        if ($this->limit == 0) {
            $this->limit = DEFAULT_RSS_LIMIT;
        }
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */

    function handle($args)
    {
        // Get the list of notices
        $this->notices = $this->getNotices();
        // Parent handling, including cache check
        parent::handle($args);
        $this->showRss($this->limit);
    }

    /**
     * Get the notices to output in this stream
     *
     * @return array an array of Notice objects sorted in reverse chron
     */

    function getNotices()
    {
        return array();
    }

    /**
     * Get a description of the channel
     *
     * Returns an array with the following
     * @return array
     */

    function getChannel()
    {
        return array('url' => '',
                     'title' => '',
                     'link' => '',
                     'description' => '');
    }

    function getImage()
    {
        return null;
    }

    function showRss($limit=0)
    {
        $notices = $this->getNotices($limit);

        $this->initRss();
        $this->showChannel($notices);
        $this->showImage();

        foreach ($notices as $n) {
            $this->showItem($n);
        }

        $this->showCreators();
        $this->endRss();
    }

    function showChannel($notices)
    {

        $channel = $this->getChannel();
        $image = $this->getImage();

        $this->elementStart('channel', array('rdf:about' => $channel['url']));
        $this->element('title', null, $channel['title']);
        $this->element('link', null, $channel['link']);
        $this->element('description', null, $channel['description']);
        $this->element('cc:licence', array('rdf:resource' => common_config('license','url')));

        if ($image) {
            $this->element('image', array('rdf:resource' => $image));
        }

        $this->elementStart('items');
        $this->elementStart('rdf:Seq');

        foreach ($notices as $notice) {
            $this->element('sioct:MicroblogPost', array('rdf:resource' => $notice->uri));
        }

        $this->elementEnd('rdf:Seq');
        $this->elementEnd('items');

        $this->elementEnd('channel');
    }

    function showImage()
    {
        $image = $this->getImage();
        if ($image) {
            $channel = $this->getChannel();
            $this->elementStart('image', array('rdf:about' => $image));
            $this->element('title', null, $channel['title']);
            $this->element('link', null, $channel['link']);
            $this->element('url', null, $image);
            $this->elementEnd('image');
        }
    }

    function showItem($notice)
    {
        $profile = Profile::staticGet($notice->profile_id);
        $nurl = common_local_url('shownotice', array('notice' => $notice->id));
        $creator_uri = common_profile_uri($profile);
        $this->elementStart('item', array('rdf:about' => $notice->uri));
        $title = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
        $this->element('title', null, $title);
        $this->element('link', null, $nurl);
        $this->element('description', null, $profile->nickname."'s status on ".common_exact_date($notice->created));
        $this->element('dc:date', null, common_date_w3dtf($notice->created));
        $this->element('dc:creator', null, ($profile->fullname) ? $profile->fullname : $profile->nickname);
        $this->element('sioc:has_creator', array('rdf:resource' => $creator_uri));
        $this->element('laconica:postIcon', array('rdf:resource' => $profile->avatarUrl()));
        $this->element('cc:licence', array('rdf:resource' => common_config('license', 'url')));
        $this->elementEnd('item');
        $this->creators[$creator_uri] = $profile;
    }

    function showCreators()
    {
        foreach ($this->creators as $uri => $profile) {
            $id = $profile->id;
            $nickname = $profile->nickname;
            $this->elementStart('sioc:User', array('rdf:about' => $uri));
            $this->element('foaf:nick', null, $nickname);
            if ($profile->fullname) {
                $this->element('foaf:name', null, $profile->fullname);
            }
            $this->element('sioc:id', null, $id);
            $avatar = $profile->avatarUrl();
            $this->element('sioc:avatar', array('rdf:resource' => $avatar));
            $this->elementEnd('sioc:User');
        }
    }

    function initRss()
    {
        $channel = $this->getChannel();
        header('Content-Type: application/rdf+xml');

        $this->startXml();
        $this->elementStart('rdf:RDF', array('xmlns:rdf' =>
                                              'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
                                              'xmlns:dc' =>
                                              'http://purl.org/dc/elements/1.1/',
                                              'xmlns:cc' =>
                                              'http://web.resource.org/cc/',
                                              'xmlns:content' =>
                                              'http://purl.org/rss/1.0/modules/content/',
                                              'xmlns:foaf' =>
                                              'http://xmlns.com/foaf/0.1/',
                                              'xmlns:sioc' =>
                                              'http://rdfs.org/sioc/ns#',
                                              'xmlns:sioct' =>
                                              'http://rdfs.org/sioc/types#',
                                              'xmlns:laconica' =>
                                              'http://laconi.ca/ont/',
                                              'xmlns' => 'http://purl.org/rss/1.0/'));
        $this->elementStart('sioc:Site', array('rdf:about' => common_root_url()));
        $this->element('sioc:name', null, common_config('site', 'name'));
        $this->elementStart('sioc:container_of');
        $this->element('sioc:Container', array('rdf:about' =>
                                               $channel['url']));
        $this->elementEnd('sioc:container_of');
        $this->elementEnd('sioc:Site');
    }

    function endRss()
    {
        $this->elementEnd('rdf:RDF');
    }

    /**
     * When was this page last modified?
     *
     */

    function lastModified()
    {
        if (empty($this->notices)) {
            return null;
        }

        if (count($this->notices) == 0) {
            return null;
        }

        // FIXME: doesn't handle modified profiles, avatars, deleted notices

        return strtotime($this->notices[0]->created);
    }
}

