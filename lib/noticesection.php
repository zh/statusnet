<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for sections showing lists of notices
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('NOTICES_PER_SECTION', 6);

/**
 * Base class for sections showing lists of notices
 *
 * These are the widgets that show interesting data about a person
 * group, or site.
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class NoticeSection extends Section
{
    function showContent()
    {
        $notices = $this->getNotices();
        $cnt = 0;
        $this->out->elementStart('ol', 'notices xoxo');
        while ($notices->fetch() && ++$cnt <= NOTICES_PER_SECTION) {
            $this->showNotice($notices);
        }

        $this->out->elementEnd('ol');
        return ($cnt > NOTICES_PER_SECTION);
    }

    function getNotices()
    {
        return null;
    }

    function showNotice($notice)
    {
        $profile = $notice->getProfile();
        if (empty($profile)) {
            common_log(LOG_WARNING, sprintf("Notice %d has no profile",
                                            $notice->id));
            return;
        }
        $this->out->elementStart('li', 'hentry notice');
        $this->out->elementStart('div', 'entry-title');
        $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);
        $this->out->elementStart('span', 'vcard author');
        $this->out->elementStart('a', array('title' => ($profile->fullname) ?
                                            $profile->fullname :
                                            $profile->nickname,
                                            'href' => $profile->profileurl,
                                            'class' => 'url'));
        $this->out->element('img', array('src' => (($avatar) ? $avatar->displayUrl() :  Avatar::defaultImage(AVATAR_MINI_SIZE)),
                                         'width' => AVATAR_MINI_SIZE,
                                         'height' => AVATAR_MINI_SIZE,
                                         'class' => 'avatar photo',
                                         'alt' =>  ($profile->fullname) ?
                                         $profile->fullname :
                                         $profile->nickname));
        $this->out->text(' ');
        $this->out->element('span', 'fn nickname', $profile->nickname);
        $this->out->elementEnd('a');
        $this->out->elementEnd('span');

        $this->out->elementStart('p', 'entry-content');
        $this->out->raw($notice->rendered);

        $notice_link_cfg = common_config('site', 'notice_link');
        if ('direct' === $notice_link_cfg) {
            $this->out->text(' (');
            $this->out->element('a', array('href' => $notice->uri), 'see');
            $this->out->text(')');
        } elseif ('attachment' === $notice_link_cfg) {
            if ($count = $notice->hasAttachments()) {
            // link to attachment(s) pages
                if (1 === $count) {
                    $f2p = File_to_post::staticGet('post_id', $notice->id);
                    $href = common_local_url('attachment', array('attachment' => $f2p->file_id));
                    $att_class = 'attachment';
                } else {
                    $href = common_local_url('attachments', array('notice' => $notice->id));
                    $att_class = 'attachments';
                }

                $clip = Theme::path('images/icons/clip.png', 'base');
                $this->out->elementStart('a', array('class' => $att_class, 'style' => "font-style: italic;", 'href' => $href, 'title' => "# of attachments: $count"));
                $this->out->raw(" ($count&nbsp");
                $this->out->element('img', array('style' => 'display: inline', 'align' => 'top', 'width' => 20, 'height' => 20, 'src' => $clip, 'alt' => 'alt'));
                $this->out->text(')');
                $this->out->elementEnd('a');
            } else {
                $this->out->text(' (');
                $this->out->element('a', array('href' => $notice->uri), 'see');
                $this->out->text(')');
            }
        }

        $this->out->elementEnd('p');
        if (!empty($notice->value)) {
            $this->out->elementStart('p');
            $this->out->text($notice->value);
            $this->out->elementEnd('p');
        }
        $this->out->elementEnd('div');
        $this->out->elementEnd('li');
    }
}
