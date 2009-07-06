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
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/favorform.php';
require_once INSTALLDIR.'/lib/disfavorform.php';
require_once INSTALLDIR.'/lib/attachmentlist.php';

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
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */

class NoticeList extends Widget
{
    /** the current stream of notices being displayed. */

    var $notice = null;

    /**
     * constructor
     *
     * @param Notice $notice stream of notices from DB_DataObject
     */

    function __construct($notice, $out=null)
    {
        parent::__construct($out);
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
        $this->out->elementStart('div', array('id' =>'notices_primary'));
        $this->out->element('h2', null, _('Notices'));
        $this->out->elementStart('ol', array('class' => 'notices xoxo'));

        $cnt = 0;

        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $item = $this->newListItem($this->notice);
            $item->show();
        }

        $this->out->elementEnd('ol');
        $this->out->elementEnd('div');

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
        return new NoticeListItem($notice, $this->out);
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

class NoticeListItem extends Widget
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

    function __construct($notice, $out=null)
    {
        parent::__construct($out);
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
        $this->showNotice();
        $this->showNoticeInfo();
        $this->showNoticeOptions();
        $this->showEnd();
    }

    function showNotice()
    {
        $this->out->elementStart('div', 'entry-title');
        $this->showAuthor();
        $this->showContent();
        $this->out->elementEnd('div');
    }

    function showNoticeInfo()
    {
        $this->out->elementStart('div', 'entry-content');
        $this->showNoticeLink();
        $this->showNoticeSource();
        $this->showContext();
        $this->out->elementEnd('div');
    }

    function showNoticeOptions()
    {
        $user = common_current_user();
        if ($user) {
            $this->out->elementStart('div', 'notice-options');
            $this->showFaveForm();
            $this->showReplyLink();
            $this->showDeleteLink();
            $this->out->elementEnd('div');
        }
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
        $this->out->elementStart('li', array('class' => 'hentry notice',
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
                $disfavor = new DisfavorForm($this->out, $this->notice);
                $disfavor->show();
            } else {
                $favor = new FavorForm($this->out, $this->notice);
                $favor->show();
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
        $this->out->elementStart('span', 'vcard author');
        $attrs = array('href' => $this->profile->profileurl,
                       'class' => 'url');
        if (!empty($this->profile->fullname)) {
            $attrs['title'] = $this->profile->fullname . ' (' . $this->profile->nickname . ') ';
        }
        $this->out->elementStart('a', $attrs);
        $this->showAvatar();
        $this->showNickname();
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');
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
        if ('shownotice' === $this->out->trimmed('action')) {
            $avatar_size = AVATAR_PROFILE_SIZE;
        } else {
            $avatar_size = AVATAR_STREAM_SIZE;
        }
        $avatar = $this->profile->getAvatar($avatar_size);

        $this->out->element('img', array('src' => ($avatar) ?
                                         $avatar->displayUrl() :
                                         Avatar::defaultImage($avatar_size),
                                         'class' => 'avatar photo',
                                         'width' => $avatar_size,
                                         'height' => $avatar_size,
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
        $this->out->element('span', array('class' => 'nickname fn'),
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
        $this->out->elementStart('p', array('class' => 'entry-content'));
        if ($this->notice->rendered) {
            $this->out->raw($this->notice->rendered);
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->out->raw(common_render_content($this->notice->content, $this->notice));
        }
        $this->out->elementEnd('p');
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
        $this->out->elementStart('dl', 'timestamp');
        $this->out->element('dt', null, _('Published'));
        $this->out->elementStart('dd', null);
        $this->out->elementStart('a', array('rel' => 'bookmark',
                                            'href' => $noticeurl));
        $dt = common_date_iso8601($this->notice->created);
        $this->out->element('abbr', array('class' => 'published',
                                          'title' => $dt),
                            common_date_string($this->notice->created));

        $this->out->elementEnd('a');
        $this->out->elementEnd('dd');
        $this->out->elementEnd('dl');
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
            $this->out->elementStart('dl', 'device');
            $this->out->element('dt', null, _('From'));
            $source_name = _($this->notice->source);
            switch ($this->notice->source) {
             case 'web':
             case 'xmpp':
             case 'mail':
             case 'omb':
             case 'system':
             case 'api':
                $this->out->element('dd', null, $source_name);
                break;
             default:
                $ns = Notice_source::staticGet($this->notice->source);
                if ($ns) {
                    $this->out->elementStart('dd', null);
                    $this->out->element('a', array('href' => $ns->url,
                                                   'rel' => 'external'),
                                        $ns->name);
                    $this->out->elementEnd('dd');
                } else {
                    $this->out->element('dd', null, $source_name);
                }
                break;
            }
            $this->out->elementEnd('dl');
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

    function showContext()
    {
        // XXX: also show context if there are replies to this notice
        if (!empty($this->notice->conversation)
            && $this->notice->conversation != $this->notice->id) {
            $convurl = common_local_url('conversation',
                                         array('id' => $this->notice->conversation));
            $this->out->elementStart('dl', 'response');
            $this->out->element('dt', null, _('To'));
            $this->out->elementStart('dd');
            $this->out->element('a', array('href' => $convurl.'#notice-'.$this->notice->id),
                                _('in context'));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
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
        if (common_logged_in()) {
            $reply_url = common_local_url('newnotice',
                                          array('replyto' => $this->profile->nickname));

            $this->out->elementStart('dl', 'notice_reply');
            $this->out->element('dt', null, _('Reply to this notice'));
            $this->out->elementStart('dd');
            $this->out->elementStart('a', array('href' => $reply_url,
                                                'title' => _('Reply to this notice')));
            $this->out->text(_('Reply'));
            $this->out->element('span', 'notice_id', $this->notice->id);
            $this->out->elementEnd('a');
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
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
            $this->out->elementStart('dl', 'notice_delete');
            $this->out->element('dt', null, _('Delete this notice'));
            $this->out->elementStart('dd');
            $this->out->element('a', array('href' => $deleteurl,
                                           'title' => _('Delete this notice')), _('Delete'));
            $this->out->elementEnd('dd');
            $this->out->elementEnd('dl');
        }
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
        $this->out->elementEnd('li');
    }
}
