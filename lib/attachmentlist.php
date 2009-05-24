<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * widget for displaying a list of notice attachments
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

/**
 * widget for displaying a list of notice attachments
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

class AttachmentList extends Widget
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
//        $this->out->elementStart('div', array('id' =>'attachments_primary'));
        $this->out->elementStart('div', array('id' =>'content'));
        $this->out->element('h2', null, _('Attachments'));
        $this->out->elementStart('ul', array('class' => 'attachments'));

        $atts = new File;
        $att = $atts->getAttachments($this->notice->id);
        foreach ($att as $n=>$attachment) {
            $item = $this->newListItem($attachment);
            $item->show();
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');

        return count($att);
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

    function newListItem($attachment)
    {
        return new AttachmentListItem($attachment, $this->out);
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

class AttachmentListItem extends Widget
{
    /** The attachment this item will show. */

    var $attachment = null;

    var $oembed = null;

    /**
     * constructor
     *
     * Also initializes the profile attribute.
     *
     * @param Notice $notice The notice we'll display
     */

    function __construct($attachment, $out=null)
    {
        parent::__construct($out);
        $this->attachment  = $attachment;
        $this->oembed = File_oembed::staticGet('file_id', $this->attachment->id);
    }

    function title() {
        if (empty($this->attachment->title)) {
            if (empty($this->oembed->title)) {
                $title = $this->attachment->url;
            } else {
                $title = $this->oembed->title;
            }
        } else {
            $title = $this->attachment->title;
        }

        return $title;
    }

    function linkTitle() {
        return 'Our page for ' . $this->title();
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
        $this->showNoticeAttachment();
        $this->showEnd();
    }

    function linkAttr() {
        return array('class' => 'attachment', 'href' => $this->attachment->url, 'id' => 'attachment-' . $this->attachment->id);
    }

    function showLink() {
        $attr = $this->linkAttr();
        $text = $this->linkTitle();
        $this->out->elementStart('h4');
        $this->out->element('a', $attr, $text);

        $this->out->elementEnd('h4');
    }

    function showNoticeAttachment()
    {
        $this->showLink();
        $this->showRepresentation();
    }

    function showRepresentation() {
        $thumbnail = File_thumbnail::staticGet('file_id', $this->attachment->id);
        if (!empty($thumbnail)) {
            $this->out->elementStart('a', $this->linkAttr()/*'href' => $this->linkTo()*/);
            $this->out->element('img', array('alt' => 'nothing to say', 'src' => $thumbnail->url, 'width' => $thumbnail->width, 'height' => $thumbnail->height));
            $this->out->elementEnd('a');
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
        $this->out->elementStart('li');
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

class Attachment extends AttachmentListItem
{
    function show() {
        $this->showNoticeAttachment();
    }

    function linkAttr() {
        return array('class' => 'external', 'href' => $this->attachment->url);
    }

    function linkTitle() {
        return 'Direct link to ' . $this->title();
    }

    function showRepresentation() {
        if (empty($this->oembed->type)) {
            if (empty($this->attachment->mimetype)) {
                $this->out->element('pre', null, 'oh well... not sure how to handle the following: ' . print_r($this->attachment, true));
            } else {
                switch ($this->attachment->mimetype) {
                case 'image/gif':
                case 'image/png':
                case 'image/jpg':
                case 'image/jpeg':
                    $this->out->element('img', array('src' => $this->attachment->url, 'alt' => 'alt'));
                    break;
                }
            }
        } else {
            switch ($this->oembed->type) {
            case 'rich':
            case 'video':
            case 'link':
                if (!empty($this->oembed->html)) {
                    $this->out->raw($this->oembed->html);
                }
                break;

            case 'photo':
                $this->out->element('img', array('src' => $this->oembed->url, 'width' => $this->oembed->width, 'height' => $this->oembed->height, 'alt' => 'alt'));
                break;

            default:
                $this->out->element('pre', null, 'oh well... not sure how to handle the following oembed: ' . print_r($this->oembed, true));
            }
        }
    }
}

