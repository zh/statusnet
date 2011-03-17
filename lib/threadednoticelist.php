<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
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
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      Notice
 * @see      NoticeListItem
 * @see      ProfileNoticeList
 */

class ThreadedNoticeList extends NoticeList
{
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
        $this->out->elementStart('ol', array('class' => 'notices threaded-notices xoxo'));

        $cnt = 0;
        $conversations = array();
        while ($this->notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $convo = $this->notice->conversation;
            if (!empty($conversations[$convo])) {
                // Seen this convo already -- skip!
                continue;
            }
            $conversations[$convo] = true;

            // Get the convo's root notice
            // @fixme stream goes in wrong direction, this needs sane caching
            //$notice = Notice::conversationStream($convo, 0, 1);
            //$notice->fetch();
            $notice = new Notice();
            $notice->conversation = $this->notice->conversation;
            $notice->orderBy('CREATED');
            $notice->limit(1);
            $notice->find(true);

            try {
                $item = $this->newListItem($notice);
                $item->show();
            } catch (Exception $e) {
                // we log exceptions and continue
                common_log(LOG_ERR, $e->getMessage());
                continue;
            }
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
        return new ThreadedNoticeListItem($notice, $this->out);
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
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 * @see      NoticeList
 * @see      ProfileNoticeListItem
 */

class ThreadedNoticeListItem extends NoticeListItem
{
    const INITIAL_ITEMS = 3;

    function showContext()
    {
        // Silence!
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
        if (!$this->repeat) {
            $notice = Notice::conversationStream($this->notice->conversation, 0, self::INITIAL_ITEMS + 2);
            $notices = array();
            $cnt = 0;
            $moreCutoff = null;
            while ($notice->fetch()) {
                if ($notice->id == $this->notice->id) {
                    // Skip!
                    continue;
                }
                $cnt++;
                if ($cnt > self::INITIAL_ITEMS) {
                    // boo-yah
                    $moreCutoff = clone($notice);
                    break;
                }
                $notices[] = clone($notice); // *grumble* inefficient as hell
            }

            if ($notices) {
                $this->out->elementStart('ul', 'notices threaded-replies xoxo');
                $item = new ThreadedNoticeListFavesItem($notice, $this->out);
                $hasFaves = $item->show();
                if ($moreCutoff) {
                    $item = new ThreadedNoticeListMoreItem($moreCutoff, $this->out);
                    $item->show();
                }
                foreach (array_reverse($notices) as $notice) {
                    $item = new ThreadedNoticeListSubItem($notice, $this->out);
                    $item->show();
                }
                // @fixme do a proper can-post check that's consistent
                // with the JS side
                if (common_current_user()) {
                    $item = new ThreadedNoticeListReplyItem($notice, $this->out);
                    $item->show();
                }
                $this->out->elementEnd('ul');
            }
        }

        parent::showEnd();
    }
}

class ThreadedNoticeListSubItem extends NoticeListItem
{

    function avatarSize()
    {
        return AVATAR_STREAM_SIZE; // @fixme would like something in between
    }

    function showNoticeLocation()
    {
        //
    }

    function showNoticeSource()
    {
        //
    }

    function showContext()
    {
        //
    }
}

/**
 * Placeholder for loading more replies...
 */
class ThreadedNoticeListMoreItem extends NoticeListItem
{

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
        $this->showMiniForm();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */

    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-reply-comments'));
    }

    function showMiniForm()
    {
        $id = $this->notice->conversation;
        $url = common_local_url('conversation', array('id' => $id)) . '#notice-' . $this->notice->id;

        $notice = new Notice();
        $notice->conversation = $id;
        $n = $notice->count() - 1;
        $msg = sprintf(_m('Show %d reply', 'Show all %d replies', $n), $n);

        $this->out->element('a', array('href' => $url), $msg);
    }
}


/**
 * Placeholder for reply form...
 * Same as get added at runtime via SN.U.NoticeInlineReplyPlaceholder
 */
class ThreadedNoticeListReplyItem extends NoticeListItem
{

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
        $this->showMiniForm();
        $this->showEnd();
    }

    /**
     * start a single notice.
     *
     * @return void
     */

    function showStart()
    {
        $this->out->elementStart('li', array('class' => 'notice-reply-placeholder'));
    }

    function showMiniForm()
    {
        $this->out->element('input', array('class' => 'placeholder',
                                           'value' => _('Write a reply...')));
    }
}

/**
 * Placeholder for showing faves...
 */
class ThreadedNoticeListFavesItem extends NoticeListItem
{
    function show()
    {
        return $this->showFaves();
    }

    function showFaves()
    {
        $this->out->text('(QQQQQQQQQQQQQQQQQQQQQQQQQQQQQQ)');
        return 0;
        // @fixme caching & scalability!
        $fave = new Fave();
        $fave->notice_id = $this->notice->id;
        $fave->find();

        $cur = common_current_user();
        $profiles = array();
        $you = false;
        while ($fave->fetch()) {
            if ($cur && $cur->id == $fave->user_id) {
                $you = true;
            } else {
                $profiles[] = $fave->user_id;
            }
        }

        $links = array();
        if ($you) {
            $links[] = _m('FAVELIST', 'You');
        }
        foreach ($profiles as $id) {
            $profile = Profile::staticGet('id', $id);
            if ($profile) {
                $links[] = sprintf('<a href="%s" title="%s">%s</a>',
                                   htmlspecialchars($profile->profileurl),
                                   htmlspecialchars($profile->getBestName()),
                                   htmlspecialchars($profile->nickname));
            }
        }

        if ($links) {
            $count = count($links);
            $msg = _m('%1$s has favored this notice', '%1$s have favored this notice', $count);
            $out = sprintf($msg, implode(', ', $links));

            $this->out->elementStart('li', array('class' => 'notice-faves'));
            $this->out->raw($out);
            $this->out->elementEnd('li');
            return $count;
        } else {
            return 0;
        }
    }
}
