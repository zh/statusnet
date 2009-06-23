<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Show a single notice
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
 * @category  Personal
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personalgroupnav.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

/**
 * Show a single notice
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ShownoticeAction extends Action
{
    /**
     * Notice object to show
     */

    var $notice = null;

    /**
     * Profile of the notice object
     */

    var $profile = null;

    /**
     * Avatar of the profile of the notice object
     */

    var $avatar = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->arg('notice');

        $this->notice = Notice::staticGet($id);

        if (!$this->notice) {
            $this->clientError(_('No such notice.'), 404);
            return false;
        }

        $this->profile = $this->notice->getProfile();

        if (!$this->profile) {
            $this->serverError(_('Notice has no profile'), 500);
            return false;
        }

        $this->avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);

        return true;
    }

    /**
     * Is this action read-only?
     *
     * @return boolean true
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Last-modified date for page
     *
     * When was the content of this page last modified? Based on notice,
     * profile, avatar.
     *
     * @return int last-modified date as unix timestamp
     */

    function lastModified()
    {
        return max(strtotime($this->notice->modified),
                   strtotime($this->profile->modified),
                   ($this->avatar) ? strtotime($this->avatar->modified) : 0);
    }

    /**
     * An entity tag for this page
     *
     * Shows the ETag for the page, based on the notice ID and timestamps
     * for the notice, profile, and avatar. It's weak, since we change
     * the date text "one hour ago", etc.
     *
     * @return string etag
     */

    function etag()
    {
        $avtime = ($this->avatar) ?
          strtotime($this->avatar->modified) : 0;

        return 'W/"' . implode(':', array($this->arg('action'),
                                          common_language(),
                                          $this->notice->id,
                                          strtotime($this->notice->created),
                                          strtotime($this->profile->modified),
                                          $avtime)) . '"';
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */

    function title()
    {
        return sprintf(_('%1$s\'s status on %2$s'),
                       $this->profile->nickname,
                       common_exact_date($this->notice->created));
    }

    /**
     * Handle input
     *
     * Only handles get, so just show the page.
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($this->notice->is_local == 0) {
            if (!empty($this->notice->url)) {
                common_redirect($this->notice->url, 301);
            } else if (!empty($this->notice->uri) && preg_match('/^https?:/', $this->notice->uri)) {
                common_redirect($this->notice->uri, 301);
            }
        } else {
            $this->showPage();
        }
    }

    /**
     * Don't show local navigation
     *
     * @return void
     */

    function showLocalNavBlock()
    {
    }

    /**
     * Fill the content area of the page
     *
     * Shows a single notice list item.
     *
     * @return void
     */

    function showContent()
    {
        $this->elementStart('ol', array('class' => 'notices xoxo'));
        $nli = new SingleNoticeItem($this->notice, $this);
        $nli->show();
        $this->elementEnd('ol');
    }

    /**
     * Don't show page notice
     *
     * @return void
     */

    function showPageNoticeBlock()
    {
    }

    /**
     * Don't show aside
     *
     * @return void
     */

    function showAside() {
    }

    /**
     * Extra <head> content
     *
     * We show the microid(s) for the author, if any.
     *
     * @return void
     */

    function extraHead()
    {
        $user = User::staticGet($this->profile->id);

        if (!$user) {
            return;
        }

        if ($user->emailmicroid && $user->email && $this->notice->uri) {
            $id = new Microid('mailto:'. $user->email,
                              $this->notice->uri);
            $this->element('meta', array('name' => 'microid',
                                         'content' => $id->toString()));
        }

        if ($user->jabbermicroid && $user->jabber && $this->notice->uri) {
            $id = new Microid('xmpp:', $user->jabber,
                              $this->notice->uri);
            $this->element('meta', array('name' => 'microid',
                                         'content' => $id->toString()));
        }
    }
}

class SingleNoticeItem extends NoticeListItem
{
    /**
     * recipe function for displaying a single notice.
     *
     * We overload to show attachments.
     *
     * @return void
     */

    function show()
    {
        $this->showStart();
        $this->showNotice();
        $this->showNoticeAttachments();
        $this->showNoticeInfo();
        $this->showNoticeOptions();
        $this->showEnd();
    }

    function showNoticeAttachments() {
        $al = new AttachmentList($this->notice, $this->out);
        $al->show();
    }
}
