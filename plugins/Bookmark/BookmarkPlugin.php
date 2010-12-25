<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to enable social-bookmarking functionality
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
 * @category  SocialBookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Bookmark plugin main class
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarkPlugin extends Plugin
{
    const VERSION = '0.1';

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing user-submitted flags on profiles

        $schema->ensureTable('bookmark',
                             array(new ColumnDef('profile_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('url',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('title',
                                                 'varchar',
                                                 255),
                                   new ColumnDef('description',
                                                 'text'),
                                   new ColumnDef('uri',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'UNI'),
                                   new ColumnDef('url_crc32',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('created',
                                                 'datetime',
                                                 null,
                                                 false,
                                                 'MUL')));

        try {
            $schema->createIndex('bookmark', 
                                 array('profile_id', 
                                       'url_crc32'),
                                 'bookmark_profile_url_idx');
        } catch (Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
        }

        return true;
    }

    /**
     * When a notice is deleted, delete the related Bookmark
     *
     * @param Notice $notice Notice being deleted
     * 
     * @return boolean hook value
     */

    function onNoticeDeleteRelated($notice)
    {
        $nb = Bookmark::getByNotice($notice);

        if (!empty($nb)) {
            $nb->delete();
        }

        return true;
    }

    /**
     * Show the CSS necessary for this plugin
     *
     * @param Action $action the action being run
     *
     * @return boolean hook value
     */

    function onEndShowStyles($action)
    {
        $action->cssLink('plugins/Bookmark/bookmark.css');
        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'NewbookmarkAction':
        case 'BookmarkpopupAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'Bookmark':
            include_once $dir.'/'.$cls.'.php';
            return false;
        case 'BookmarkForm':
        case 'DeliciousBackupImporter':
        case 'DeliciousBookmarkImporter':
            include_once $dir.'/'.strtolower($cls).'.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onRouterInitialized($m)
    {
        $m->connect('main/bookmark/new',
                    array('action' => 'newbookmark'),
                    array('id' => '[0-9]+'));

        $m->connect('main/bookmark/popup', array('action' => 'bookmarkpopup'));

        $m->connect('bookmark/:user/:created/:crc32',
                    array('action' => 'showbookmark'),
                    array('user' => '[0-9]+',
                          'created' => '[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',
                          'crc32' => '[0-9A-F]{8}'));

        return true;
    }

    /**
     * Output the HTML for a bookmark in a list
     *
     * @param NoticeListItem $nli The list item being shown.
     *
     * @return boolean hook value
     */

    function onStartShowNoticeItem($nli)
    {
        $nb = Bookmark::getByNotice($nli->notice);

        if (!empty($nb)) {

            $out     = $nli->out;
            $notice  = $nli->notice;
            $profile = $nli->profile;

            $atts = $notice->attachments();

            if (count($atts) < 1) {
                // Something wrong; let default code deal with it.
                return true;
            }

            $att = $atts[0];

            $out->elementStart('h3');
            $out->element('a',
                          array('href' => $att->url),
                          $nb->title);
            $out->elementEnd('h3');

            $out->elementStart('ul', array('class' => 'bookmark_tags'));
            
            // Replies look like "for:" tags

            $replies = $nli->notice->getReplies();

            if (!empty($replies)) {
                foreach ($replies as $reply) {
                    $other = Profile::staticGet('id', $reply);
                    $out->elementStart('li');
                    $out->element('a', array('rel' => 'tag',
                                             'href' => $other->profileurl,
                                             'title' => $other->getBestName()),
                                  sprintf('for:%s', $other->nickname));
                    $out->elementEnd('li');
                    $out->text(' ');
                }
            }

            $tags = $nli->notice->getTags();

            foreach ($tags as $tag) {
                $out->elementStart('li');
                $out->element('a', 
                              array('rel' => 'tag',
                                    'href' => Notice_tag::url($tag)),
                              $tag);
                $out->elementEnd('li');
                $out->text(' ');
            }

            $out->elementEnd('ul');

            $out->element('p',
                          array('class' => 'bookmark_description'),
                          $nb->description);

            $nli->showNoticeAttachments();

            $out->elementStart('p', array('style' => 'float: left'));

            $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);

            $out->element('img', array('src' => ($avatar) ?
                                       $avatar->displayUrl() :
                                       Avatar::defaultImage(AVATAR_MINI_SIZE),
                                       'class' => 'avatar photo bookmark_avatar',
                                       'width' => AVATAR_MINI_SIZE,
                                       'height' => AVATAR_MINI_SIZE,
                                       'alt' => $profile->getBestName()));
            $out->raw('&nbsp;');
            $out->element('a', array('href' => $profile->profileurl,
                                     'title' => $profile->getBestName()),
                          $profile->nickname);

            $nli->showNoticeLink();
            $nli->showNoticeSource();
            $nli->showNoticeLocation();
            $nli->showContext();
            $nli->showRepeat();

            $out->elementEnd('p');

            $nli->showNoticeOptions();

            return false;
        }
        return true;
    }

    /**
     * Render a notice as a Bookmark object
     *
     * @param Notice         $notice  Notice to render
     * @param ActivityObject &$object Empty object to fill
     *
     * @return boolean hook value
     */
     
    function onStartActivityObjectFromNotice($notice, &$object)
    {
        common_log(LOG_INFO,
                   "Checking {$notice->uri} to see if it's a bookmark.");

        $nb = Bookmark::getByNotice($notice);
                                         
        if (!empty($nb)) {

            common_log(LOG_INFO,
                       "Formatting notice {$notice->uri} as a bookmark.");

            $object->id      = $notice->uri;
            $object->type    = ActivityObject::BOOKMARK;
            $object->title   = $nb->title;
            $object->summary = $nb->description;
            $object->link    = $notice->bestUrl();

            // Attributes of the URL

            $attachments = $notice->attachments();

            if (count($attachments) != 1) {
                throw new ServerException(_('Bookmark notice with the '.
                                            'wrong number of attachments.'));
            }

            $target = $attachments[0];

            $attrs = array('rel' => 'related',
                           'href' => $target->url);

            if (!empty($target->title)) {
                $attrs['title'] = $target->title;
            }

            $object->extra[] = array('link', $attrs);
                                                   
            // Attributes of the thumbnail, if any

            $thumbnail = $target->getThumbnail();

            if (!empty($thumbnail)) {
                $tattrs = array('rel' => 'preview',
                                'href' => $thumbnail->url);

                if (!empty($thumbnail->width)) {
                    $tattrs['media:width'] = $thumbnail->width;
                }

                if (!empty($thumbnail->height)) {
                    $tattrs['media:height'] = $thumbnail->height;
                }

                $object->extra[] = array('link', $attrs);
            }

            return false;
        }

        return true;
    }

    /**
     * Add our two queue handlers to the queue manager
     *
     * @param QueueManager $qm current queue manager
     * 
     * @return boolean hook value
     */

    function onEndInitializeQueueManager($qm)
    {
        $qm->connect('dlcsback', 'DeliciousBackupImporter');
        $qm->connect('dlcsbkmk', 'DeliciousBookmarkImporter');
        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version data
     * 
     * @return value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Sample',
                            'version' => self::VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Bookmark',
                            'rawdescription' =>
                            _m('Simple extension for supporting bookmarks.'));
        return true;
    }

    /**
     * Load our document if requested
     *
     * @param string &$title  Title to fetch
     * @param string &$output HTML to output
     *
     * @return boolean hook value
     */

    function onStartLoadDoc(&$title, &$output)
    {
        if ($title == 'bookmarklet') {
            $filename = INSTALLDIR.'/plugins/Bookmark/bookmarklet';

            $c      = file_get_contents($filename);
            $output = common_markup_to_html($c);
            return false; // success!
        }

        return true;
    }

    /**
     * Handle a posted bookmark from PuSH
     *
     * @param Activity        $activity activity to handle
     * @param Ostatus_profile $oprofile Profile for the feed
     *
     * @return boolean hook value
     */

    function onStartHandleFeedEntryWithProfile($activity, $oprofile) {

        common_log(LOG_INFO, "BookmarkPlugin called for new feed entry.");

        if ($activity->verb == ActivityVerb::POST &&
            $activity->objects[0]->type == ActivityObject::BOOKMARK) {

            common_log(LOG_INFO, "Importing activity {$activity->id} as a bookmark.");

            $author = $oprofile->checkAuthorship($activity);

            if (empty($author)) {
                throw new ClientException(_('Can\'t get author for activity.'));
            }

            self::_postRemoteBookmark($author,
                                      $activity);

            return false;
        }

        return true;
    }

    static private function _postRemoteBookmark(Ostatus_profile $author, Activity $activity)
    {
        $bookmark = $activity->objects[0];

        $relLinkEls = ActivityUtils::getLinks($bookmark->element, 'related');

        if (count($relLinkEls) < 1) {
            throw new ClientException(_('Expected exactly 1 link rel=related in a Bookmark.'));
        }

        if (count($relLinkEls) > 1) {
            common_log(LOG_WARNING, "Got too many link rel=related in a Bookmark.");
        }

        $linkEl = $relLinkEls[0];

        $url = $linkEl->getAttribute('href');

        $tags = array();

        foreach ($activity->categories as $category) {
            $tags[] = common_canonical_tag($category->term);
        }

        $options = array('uri' => $bookmark->id,
                         'url' => $bookmark->link,
                         'created' => common_sql_time($activity->time),
                         'is_local' => Notice::REMOTE_OMB,
                         'source' => 'ostatus');

        // Fill in location if available

        $location = $activity->context->location;

        if ($location) {
            $options['lat'] = $location->lat;
            $options['lon'] = $location->lon;
            if ($location->location_id) {
                $options['location_ns'] = $location->location_ns;
                $options['location_id'] = $location->location_id;
            }
        }

        $replies = $activity->context->attention;
        $options['groups'] = $author->filterReplies($author, $replies);
        $options['replies'] = $replies;

        // Maintain direct reply associations
        // @fixme what about conversation ID?

        if (!empty($activity->context->replyToID)) {
            $orig = Notice::staticGet('uri',
                                      $activity->context->replyToID);
            if (!empty($orig)) {
                $options['reply_to'] = $orig->id;
            }
        }

        Bookmark::saveNew($author->localProfile(),
                                 $bookmark->title,
                                 $url,
                                 $tags,
                                 $bookmark->summary,
                                 $options);
    }
}

