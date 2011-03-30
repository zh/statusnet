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
    const VERSION         = '0.1';
    const IMPORTDELICIOUS = 'BookmarkPlugin:IMPORTDELICIOUS';

    /**
     * Authorization for importing delicious bookmarks
     *
     * By default, everyone can import bookmarks except silenced people.
     *
     * @param Profile $profile Person whose rights to check
     * @param string  $right   Right to check; const value
     * @param boolean &$result Result of the check, writeable
     *
     * @return boolean hook value
     */

    function onUserRightsCheck($profile, $right, &$result)
    {
        if ($right == self::IMPORTDELICIOUS) {
            $result = !$profile->isSilenced();
            return false;
        }
        return true;
    }

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
                             array(new ColumnDef('id',
                                                 'char',
                                                 36,
                                                 false,
                                                 'PRI'),
                                   new ColumnDef('profile_id',
                                                 'integer',
                                                 null,
                                                 false,
                                                 'MUL'),
                                   new ColumnDef('url',
                                                 'varchar',
                                                 255,
                                                 false,
                                                 'MUL'),
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
                                   new ColumnDef('created',
                                                 'datetime',
                                                 null,
                                                 false,
                                                 'MUL')));

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
        $action->cssLink($this->path('bookmark.css'));
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
        case 'ShowbookmarkAction':
        case 'NewbookmarkAction':
        case 'BookmarkpopupAction':
        case 'NoticebyurlAction':
        case 'ImportdeliciousAction':
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

        $m->connect('main/bookmark/popup',
                    array('action' => 'bookmarkpopup'));

        $m->connect('main/bookmark/import',
                    array('action' => 'importdelicious'));

        $m->connect('bookmark/:id',
                    array('action' => 'showbookmark'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('notice/by-url/:id',
                    array('action' => 'noticebyurl'),
                    array('id' => '[0-9]+'));

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

            // XXX: only show the bookmark URL for non-single-page stuff

            if ($out instanceof ShowbookmarkAction) {
            } else {
                $out->elementStart('h3');
                $out->element('a',
                              array('href' => $att->url,
                                    'class' => 'bookmark-title entry-title'),
                              $nb->title);
                $out->elementEnd('h3');

                $countUrl = common_local_url('noticebyurl',
                                             array('id' => $att->id));

                $out->element('a', array('class' => 'bookmark-notice-count',
                                         'href' => $countUrl),
                              $att->noticeCount());
            }

            // Replies look like "for:" tags

            $replies = $nli->notice->getReplies();
            $tags = $nli->notice->getTags();

            if (!empty($replies) || !empty($tags)) {

                $out->elementStart('ul', array('class' => 'bookmark-tags'));
            
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
            }

            if (!empty($nb->description)) {
                $out->element('p',
                              array('class' => 'bookmark-description'),
                              $nb->description);
            }

            if (common_config('attachments', 'show_thumbs')) {
                $haveThumbs = false;
                foreach ($atts as $check) {
                    $thumbnail = File_thumbnail::staticGet('file_id', $check->id);
                    if (!empty($thumbnail)) {
                        $haveThumbs = true;
                        break;
                    }
                }
                if ($haveThumbs) {
                    $al = new InlineAttachmentList($notice, $out);
                    $al->show();
                }
            }

            $out->elementStart('div', array('class' => 'bookmark-info entry-content'));

            $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);

            $out->element('img', 
                          array('src' => ($avatar) ?
                                $avatar->displayUrl() :
                                Avatar::defaultImage(AVATAR_MINI_SIZE),
                                'class' => 'avatar photo bookmark-avatar',
                                'width' => AVATAR_MINI_SIZE,
                                'height' => AVATAR_MINI_SIZE,
                                'alt' => $profile->getBestName()));

            $out->raw('&nbsp;');

            $out->element('a', 
                          array('href' => $profile->profileurl,
                                'title' => $profile->getBestName()),
                          $profile->nickname);

            $nli->showNoticeLink();
            $nli->showNoticeSource();
            $nli->showNoticeLocation();
            $nli->showContext();
            $nli->showRepeat();

            $out->elementEnd('div');

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

            $object->extra[] = array('link', $attrs, null);
                                                   
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

                $object->extra[] = array('link', $attrs, null);
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
        $versions[] = array('name' => 'Bookmark',
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

    function onStartHandleFeedEntryWithProfile($activity, $oprofile)
    {
        common_log(LOG_INFO, "BookmarkPlugin called for new feed entry.");

        if (self::_isPostBookmark($activity)) {

            common_log(LOG_INFO, 
                       "Importing activity {$activity->id} as a bookmark.");

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

    /**
     * Handle a posted bookmark from Salmon
     *
     * @param Activity $activity activity to handle
     * @param mixed    $target   user or group targeted
     *
     * @return boolean hook value
     */

    function onStartHandleSalmonTarget($activity, $target)
    {
        if (self::_isPostBookmark($activity)) {

            $this->log(LOG_INFO, "Checking {$activity->id} as a valid Salmon slap.");

            if ($target instanceof User_group) {
                $uri = $target->getUri();
                if (!in_array($uri, $activity->context->attention)) {
                    throw new ClientException(_("Bookmark not posted ".
                                                "to this group."));
                }
            } else if ($target instanceof User) {
                $uri      = $target->uri;
                $original = null;
                if (!empty($activity->context->replyToID)) {
                    $original = Notice::staticGet('uri', 
                                                  $activity->context->replyToID); 
                }
                if (!in_array($uri, $activity->context->attention) &&
                    (empty($original) ||
                     $original->profile_id != $target->id)) {
                    throw new ClientException(_("Bookmark not posted ".
                                                "to this user."));
                }
            } else {
                throw new ServerException(_("Don't know how to handle ".
                                            "this kind of target."));
            }

            $author = Ostatus_profile::ensureActivityObjectProfile($activity->actor);

            self::_postRemoteBookmark($author,
                                      $activity);

            return false;
        }

        return true;
    }

    /**
     * Handle bookmark posted via AtomPub
     *
     * @param Activity &$activity Activity that was posted
     * @param User     $user      User that posted it
     * @param Notice   &$notice   Resulting notice
     *
     * @return boolean hook value
     */

    function onStartAtomPubNewActivity(&$activity, $user, &$notice)
    {
        if (self::_isPostBookmark($activity)) {
            $options = array('source' => 'atompub');
            $notice  = self::_postBookmark($user->getProfile(),
                                           $activity,
                                           $options);
            return false;
        }

        return true;
    }

    /**
     * Handle bookmark imported from a backup file
     *
     * @param User           $user     User to import for
     * @param ActivityObject $author   Original author per import file
     * @param Activity       $activity Activity to import
     * @param boolean        $trusted  Is this a trusted user?
     * @param boolean        &$done    Is this done (success or unrecoverable error)
     *
     * @return boolean hook value
     */

    function onStartImportActivity($user, $author, $activity, $trusted, &$done)
    {
        if (self::_isPostBookmark($activity)) {

            $bookmark = $activity->objects[0];

            $this->log(LOG_INFO,
                       'Importing Bookmark ' . $bookmark->id . 
                       ' for user ' . $user->nickname);

            $options = array('uri' => $bookmark->id,
                             'url' => $bookmark->link,
                             'source' => 'restore');

            $saved = self::_postBookmark($user->getProfile(), $activity, $options);

            if (!empty($saved)) {
                $done = true;
            }

            return false;
        }

        return true;
    }

    /**
     * Show a link to our delicious import page on profile settings form
     *
     * @param Action $action Profile settings action being shown
     *
     * @return boolean hook value
     */

    function onEndProfileSettingsActions($action)
    {
        $user = common_current_user();
        
        if (!empty($user) && $user->hasRight(self::IMPORTDELICIOUS)) {
            $action->elementStart('li');
            $action->element('a',
                             array('href' => common_local_url('importdelicious')),
                             _('Import del.icio.us bookmarks'));
            $action->elementEnd('li');
        }

        return true;
    }

    /**
     * Output our CSS class for bookmark notice list elements
     *
     * @param NoticeListItem $nli The item being shown
     *
     * @return boolean hook value
     */

    function onStartOpenNoticeListItemElement($nli)
    {
        $nb = Bookmark::getByNotice($nli->notice);
        if (!empty($nb)) {
            $id = (empty($nli->repeat)) ? $nli->notice->id : $nli->repeat->id;
            $nli->out->elementStart('li', array('class' => 'hentry notice bookmark',
                                                 'id' => 'notice-' . $id));
            Event::handle('EndOpenNoticeListItemElement', array($nli));
            return false;
        }
        return true;
    }

    /**
     * Save a remote bookmark (from Salmon or PuSH)
     *
     * @param Ostatus_profile $author   Author of the bookmark
     * @param Activity        $activity Activity to save
     *
     * @return Notice resulting notice.
     */

    static private function _postRemoteBookmark(Ostatus_profile $author,
                                                Activity $activity)
    {
        $bookmark = $activity->objects[0];

        $options = array('uri' => $bookmark->id,
                         'url' => $bookmark->link,
                         'is_local' => Notice::REMOTE_OMB,
                         'source' => 'ostatus');
        
        return self::_postBookmark($author->localProfile(), $activity, $options);
    }

    /**
     * Save a bookmark from an activity
     *
     * @param Profile  $profile  Profile to use as author
     * @param Activity $activity Activity to save
     * @param array    $options  Options to pass to bookmark-saving code
     *
     * @return Notice resulting notice
     */

    static private function _postBookmark(Profile $profile,
                                          Activity $activity,
                                          $options=array())
    {
        $bookmark = $activity->objects[0];

        $relLinkEls = ActivityUtils::getLinks($bookmark->element, 'related');

        if (count($relLinkEls) < 1) {
            throw new ClientException(_('Expected exactly 1 link '.
                                        'rel=related in a Bookmark.'));
        }

        if (count($relLinkEls) > 1) {
            common_log(LOG_WARNING,
                       "Got too many link rel=related in a Bookmark.");
        }

        $linkEl = $relLinkEls[0];

        $url = $linkEl->getAttribute('href');

        $tags = array();

        foreach ($activity->categories as $category) {
            $tags[] = common_canonical_tag($category->term);
        }

        if (!empty($activity->time)) {
            $options['created'] = common_sql_date($activity->time);
        }

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

        $options['groups']  = array();
        $options['replies'] = array();

        foreach ($replies as $replyURI) {
            $other = Profile::fromURI($replyURI);
            if (!empty($other)) {
                $options['replies'][] = $replyURI;
            } else {
                $group = User_group::staticGet('uri', $replyURI);
                if (!empty($group)) {
                    $options['groups'][] = $replyURI;
                }
            }
        }

        // Maintain direct reply associations
        // @fixme what about conversation ID?

        if (!empty($activity->context->replyToID)) {
            $orig = Notice::staticGet('uri',
                                      $activity->context->replyToID);
            if (!empty($orig)) {
                $options['reply_to'] = $orig->id;
            }
        }

        return Bookmark::saveNew($profile,
                                 $bookmark->title,
                                 $url,
                                 $tags,
                                 $bookmark->summary,
                                 $options);
    }

    /**
     * Test if an activity represents posting a bookmark
     *
     * @param Activity $activity Activity to test
     *
     * @return true if it's a Post of a Bookmark, else false
     */

    static private function _isPostBookmark($activity)
    {
        return ($activity->verb == ActivityVerb::POST &&
                $activity->objects[0]->type == ActivityObject::BOOKMARK);
    }
}
