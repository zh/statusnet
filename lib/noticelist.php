<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notices
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
 * @category  UI
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * widget for displaying a list of notices
 *
 * There are a number of actions that display a list of notices, in
 * reverse chronological order. This widget abstracts out most of the
 * code for UI for notice lists. It's overridden to hide some
 * data for e.g. the profile page.
 *
 * @category UI
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 * @see      Notice
 * @see      StreamAction
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */

class NoticeList
{
    /** the current stream of notices being displayed. */

    var $notice = null;

    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */

    function __construct($notice)
    {
        $this->notice = $notice;
    }

    /**
     * show the list of notices
     *
     * "Uses up" the stream by looping through it. So, probably can't
     * be called twice on the same list.
     *
     * @return int count of notices listed.
     */

    function show()
    {
        common_element_start('ul', array('id' => 'notices'));

        $cnt = 0;

        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $item = $this->newListItem($this->notice);
            $item->show();
        }

        common_element_end('ul');

        return $cnt;
    }

    /**
     * returns a new list item for the current notice
     *
     * Recipe (factory?) method; overridden by sub-classes to give
     * a different list item class.
     *
     * @param Notice $notice the current notice
     *
     * @return NoticeListItem a list item for displaying the notice
     */

    function newListItem($notice)
    {
        return new NoticeListItem($notice);
    }
}

/**
 * widget for displaying a single notice
 *
 * This widget has the core smarts for showing a single notice: what to display,
 * where, and under which circumstances. Its key method is show(); this is a recipe
 * that calls all the other show*() methods to build up a single notice. The
 * ProfileNoticeListItem subclass, for example, overrides showAuthor() to skip
 * author info (since that's implicit by the data in the page).
 *
 * @category UI
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */

class NoticeListItem
{
    /** The notice this item will show. */

    var $notice = null;

    /** The profile of the author of the notice, extracted once for convenience. */

    var $profile = null;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */

    function __construct($notice)
    {
        $this->notice  = $notice;
        $this->profile = $notice->getProfile();
    }

    /**
     * recipe function for displaying a single notice.
     *
     * This uses all the other methods to correctly display a notice. Override
     * it or one of the others to fine-tune the output.
     *
     * @return void
     */

    function show()
    {
        $this->showStart();
        $this->showFaveForm();
        $this->showAuthor();
        $this->showContent();
        $this->startTimeSection();
        $this->showNoticeLink();
        $this->showNoticeSource();
        $this->showReplyTo();
        $this->showReplyLink();
        $this->showDeleteLink();
        $this->endTimeSection();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */

    function showStart()
    {
        // XXX: RDFa
        // TODO: add notice_type class e.g., notice_video, notice_image
        common_element_start('li', array('class' => 'hentry notice',
                                         'id' => 'notice-' . $this->notice->id));
    }

    /**
     * show the "favorite" form
     *
     * @return void
     */

    function showFaveForm()
    {
        $user = common_current_user();
        if ($user) {
            if ($user->hasFave($this->notice)) {
                common_disfavor_form($this->notice);
            } else {
                common_favor_form($this->notice);
            }
        }
    }

    /**
     * show the author of a notice
     *
     * By default, this shows the avatar and (linked) nickname of the author.
     *
     * @return void
     */

    function showAuthor()
    {
        common_element_start('span', 'vcard author');
        common_element_start('a', array('href' => $this->profile->profileurl),
                                        'class' => 'url');
        $this->showAvatar();
        $this->showNickname();
        common_element_end('a');
        common_element_end('span');
    }

    /**
     * show the avatar of the notice's author
     *
     * This will use the default avatar if no avatar is assigned for the author.
     * It makes a link to the author's profile.
     *
     * @return void
     */

    function showAvatar()
    {
        $avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);

        common_element('img', array('src' => ($avatar) ?
                                    common_avatar_display_url($avatar) :
                                    common_default_avatar(AVATAR_STREAM_SIZE),
                                    'class' => 'avatar photo',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($this->profile->fullname) ?
                                    $this->profile->fullname :
                                    $this->profile->nickname));
    }

    /**
     * show the nickname of the author
     *
     * Links to the author's profile page
     *
     * @return void
     */

    function showNickname()
    {
        common_element('span', array('class' => 'nickname fn'),
                       $this->profile->nickname);
    }

    /**
     * show the content of the notice
     *
     * Shows the content of the notice. This is pre-rendered for efficiency
     * at save time. Some very old notices might not be pre-rendered, so
     * they're rendered on the spot.
     *
     * @return void
     */

    function showContent()
    {
        // FIXME: URL, image, video, audio
        common_element_start('p', array('class' => 'content entry-title'));
        if ($this->notice->rendered) {
            common_raw($this->notice->rendered);
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            common_raw(common_render_content($this->notice->content, $this->notice));
        }
        common_element_end('p');
    }

    /**
     * show the "time" section of a notice
     *
     * This is the greyed-out section that appears beneath the content, including
     * links to delete or reply to the notice. Probably should be called something
     * else.
     *
     * @return void
     */

    function startTimeSection()
    {
        common_element_start('p', 'time');
    }

    /**
     * show the link to the main page for the notice
     *
     * Displays a link to the page for a notice, with "relative" time. Tries to
     * get remote notice URLs correct, but doesn't always succeed.
     *
     * @return void
     */

    function showNoticeLink()
    {
        $noticeurl = common_local_url('shownotice',
                                      array('notice' => $this->notice->id));
        // XXX: we need to figure this out better. Is this right?
        if (strcmp($this->notice->uri, $noticeurl) != 0 &&
            preg_match('/^http/', $this->notice->uri)) {
            $noticeurl = $this->notice->uri;
        }
        common_element_start('a', array('class' => 'permalink',
                                        'rel' => 'bookmark',
                                        'href' => $noticeurl));
        $dt = common_date_iso8601($this->notice->created);
        common_element('abbr', array('class' => 'published',
                                     'title' => $dt),
                       common_date_string($this->notice->created));
        common_element_end('a');
    }

    /**
     * Show the source of the notice
     *
     * Either the name (and link) of the API client that posted the notice,
     * or one of other other channels.
     *
     * @return void
     */

    function showNoticeSource()
    {
        if ($this->notice->source) {
            common_element('span', null, _(' from '));
            $source_name = _($this->notice->source);
            switch ($this->notice->source) {
            case 'web':
            case 'xmpp':
            case 'mail':
            case 'omb':
            case 'api':
                common_element('span', 'noticesource', $source_name);
                break;
            default:
                $ns = Notice_source::staticGet($this->notice->source);
                if ($ns) {
                    common_element('a', array('href' => $ns->url),
                                   $ns->name);
                } else {
                    common_element('span', 'noticesource', $source_name);
                }
                break;
            }
        }
    }

    /**
     * show link to notice this notice is a reply to
     *
     * If this notice is a reply, show a link to the notice it is replying to. The
     * heavy lifting for figuring out replies happens at save time.
     *
     * @return void
     */

    function showReplyTo()
    {
        if ($this->notice->reply_to) {
            $replyurl = common_local_url('shownotice',
                                         array('notice' => $this->notice->reply_to));
            common_text(' (');
            common_element('a', array('class' => 'inreplyto',
                                      'href' => $replyurl),
                           _('in reply to...'));
            common_text(')');
        }
    }

    /**
     * show a link to reply to the current notice
     *
     * Should either do the reply in the current notice form (if available), or
     * link out to the notice-posting form. A little flakey, doesn't always work.
     *
     * @return void
     */

    function showReplyLink()
    {
        $reply_url = common_local_url('newnotice',
                                      array('replyto' => $this->profile->nickname));

        $reply_js =
          'return doreply("'.$this->profile->nickname.'",'.$this->notice->id.');';

        common_element_start('a',
                             array('href' => $reply_url,
                                   'onclick' => $reply_js,
                                   'title' => _('reply'),
                                   'class' => 'replybutton'));
        common_raw(' &#8594;');
        common_element_end('a');
    }

    /**
     * if the user is the author, let them delete the notice
     *
     * @return void
     */

    function showDeleteLink()
    {
        $user = common_current_user();
        if ($user && $this->notice->profile_id == $user->id) {
            $deleteurl = common_local_url('deletenotice',
                                          array('notice' => $this->notice->id));
            common_element_start('a', array('class' => 'deletenotice',
                                            'href' => $deleteurl,
                                            'title' => _('delete')));
            common_raw(' &#215;');
            common_element_end('a');
        }
    }

    /**
     * end the time section
     *
     * @return void
     */

    function endTimeSection()
    {
        common_element_end('p');
    }

    /**
     * finish the notice
     *
     * Close the last elements in the notice list item
     *
     * @return void
     */

    function showEnd()
    {
        common_element_end('li');
    }
}
