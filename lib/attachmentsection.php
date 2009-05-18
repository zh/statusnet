<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of attachments
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

define('ATTACHMENTS_PER_SECTION', 6);

/**
 * Base class for sections showing lists of attachments
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

class AttachmentSection extends Section
{
    function showContent()
    {
        $attachments = $this->getAttachments();

        $cnt = 0;

        $this->out->elementStart('ul', 'attachments');

        while ($attachments->fetch() && ++$cnt <= ATTACHMENTS_PER_SECTION) {
            $this->showAttachment($attachments);
        }

        $this->out->elementEnd('ul');

        return ($cnt > ATTACHMENTS_PER_SECTION);
    }

    function getAttachments()
    {
        return null;
    }

    function showAttachment($attachment)
    {
        $this->out->elementStart('li');
        $this->out->element('a', array('class' => 'attachment', 'href' => common_local_url('attachment', array('attachment' => $attachment->file_id))), "Attachment tagged {$attachment->c} times");
        $this->out->elementEnd('li');
    }
}

