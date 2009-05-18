<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * FIXME
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
 * @category  Widget
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * FIXME
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class FrequentAttachmentSection extends AttachmentSection
{
    function getAttachments() {
        $notice_tag = new Notice_tag;
        $query = 'select file_id, count(file_id) as c from notice_tag join file_to_post on post_id = notice_id where tag="' . $notice_tag->escape($this->out->tag) . '" group by file_id order by c desc';
        $notice_tag->query($query);
        return $notice_tag;
    }

    function title()
    {
        return sprintf(_('Attachments frequently tagged with %s'), $this->out->tag);
    }

    function divId()
    {
        return 'frequent_attachments';
    }
}

