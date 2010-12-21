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

        $schema->ensureTable('notice_bookmark',
                             array(new ColumnDef('notice_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('title',
                                                 'varchar',
                                                 255),
                                   new ColumnDef('description',
                                                 'text')));

        return true;
    }

    /**
     * When a notice is deleted, delete the related Notice_bookmark
     *
     * @param Notice $notice Notice being deleted
     * 
     * @return boolean hook value
     */

    function onNoticeDeleteRelated($notice)
    {
        $nb = Notice_bookmark::staticGet('notice_id', $notice->id);

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
        $action->style('.bookmark_tags li { display: inline; }');
        $action->style('.bookmark_mentions li { display: inline; }');
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
        case 'Notice_bookmark':
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
        $nb = Notice_bookmark::staticGet('notice_id',
                                         $nli->notice->id);

        if (!empty($nb)) {
            $att = $nli->notice->attachments();
            $nli->out->elementStart('h3');
            $nli->out->element('a',
                               array('href' => $att[0]->url),
                               $nb->title);
            $nli->out->elementEnd('h3');
            $nli->out->element('p',
                               array('class' => 'bookmark_description'),
                               $nb->description);
            $nli->out->elementStart('p');
            $nli->out->element('a', array('href' => $nli->profile->profileurl,
                                          'class' => 'bookmark_author',
                                          'title' => $nli->profile->getBestName()),
                               $nli->profile->getBestName());
            $nli->out->elementEnd('p');
            $tags = $nli->notice->getTags();
            $nli->out->elementStart('ul', array('class' => 'bookmark_tags'));
            foreach ($tags as $tag) {
                $nli->out->elementStart('li');
                $nli->out->element('a', 
                                   array('rel' => 'tag',
                                         'href' => Notice_tag::url($tag)),
                                   $tag);
                $nli->out->elementEnd('li');
                $nli->out->text(' ');
            }
            $nli->out->elementEnd('ul');
            $replies = $nli->notice->getReplies();
            if (!empty($replies)) {
                $nli->out->elementStart('ul', array('class' => 'bookmark_mentions'));
                foreach ($replies as $reply) {
                    $other = Profile::staticGet('id', $reply);
                    $nli->out->elementStart('li');
                    $nli->out->element('a', array('rel' => 'tag',
                                                  'href' => $other->profileurl,
                                                  'title' => $other->getBestName()),
                                       sprintf('for:%s', $other->nickname));
                    $nli->out->elementEnd('li');
                    $nli->out->text(' ');
                }
                $nli->out->elementEnd('ul');
            }
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
        $nb = Notice_bookmark::staticGet('notice_id',
                                         $notice->id);
                                         
        if (!empty($nb)) {

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
}

