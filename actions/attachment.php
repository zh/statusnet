<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Show notice attachments
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

//require_once INSTALLDIR.'/lib/personalgroupnav.php';
//require_once INSTALLDIR.'/lib/feedlist.php';
require_once INSTALLDIR.'/lib/attachmentlist.php';

/**
 * Show notice attachments
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class AttachmentAction extends Action
{
    /**
     * Attachment object to show
     */

    var $attachment = null;

    /**
     * Profile of the notice object
     */

//    var $profile = null;

    /**
     * Avatar of the profile of the notice object
     */

//    var $avatar = null;

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

        $id = $this->arg('attachment');

        $this->attachment = File::staticGet($id);

        if (!$this->attachment) {
            $this->clientError(_('No such attachment.'), 404);
            return false;
        }
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
     * Title of the page
     *
     * @return string title of the page
     */
    function title()
    {
        $a = new Attachment($this->attachment);
        return $a->title();
    }



    /**
     * Last-modified date for page
     *
     * When was the content of this page last modified? Based on notice,
     * profile, avatar.
     *
     * @return int last-modified date as unix timestamp
     */
/*
    function lastModified()
    {
        return max(strtotime($this->notice->created),
                   strtotime($this->profile->modified),
                   ($this->avatar) ? strtotime($this->avatar->modified) : 0);
    }
*/

    /**
     * An entity tag for this page
     *
     * Shows the ETag for the page, based on the notice ID and timestamps
     * for the notice, profile, and avatar. It's weak, since we change
     * the date text "one hour ago", etc.
     *
     * @return string etag
     */
/*
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
*/


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
        $this->showPage();
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
        $this->elementStart('ul', array('class' => 'attachments'));
        $ali = new Attachment($this->attachment, $this);
        $cnt = $ali->show();
        $this->elementEnd('ul');
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
     * Show aside: this attachments appears in what notices
     *
     * @return void
     */

    function showAside() {
        $notice = new Notice;
        $f2p = new File_to_post;
        $f2p->file_id = $this->attachment->id;
        $notice->joinAdd($f2p);
        $notice->orderBy('created desc');
        $x = $notice->find();
        $this->elementStart('ol');
        while($notice->fetch()) {
            $this->elementStart('li');
            $profile = $notice->getProfile();
            $this->element('a', array('href' => $notice->uri), $profile->nickname . ' on ' . $notice->created);
            $this->elementEnd('li');
        }
        $this->elementEnd('ol');
        $notice->free();
        $f2p->free();

        $notice_tag = new Notice_tag;
        $attachment = new File;

        $query = 'select tag,count(tag) as c from notice_tag join file_to_post on (notice_tag.notice_id=post_id) join notice on notice_id = notice.id where file_id=' . $notice_tag->escape($this->attachment->id) . ' group by tag order by c desc';

        $notice_tag->query($query);
        $this->elementStart('ol');
        while($notice_tag->fetch()) {
            $this->elementStart('li');
            $href = common_local_url('tag', array('tag' => $notice_tag->tag));
            $this->element('a', array('href' => $href), $notice_tag->tag . ' (' . $notice_tag->c . ')');
            $this->elementEnd('li');
        }
        $this->elementEnd('ol');
    }
}
