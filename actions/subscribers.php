<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * List a user's subscribers
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
 * @category  Social
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * List a user's subscribers
 *
 * @category Social
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class SubscribersAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            return sprintf(_('%s subscribers'), $this->user->nickname);
        } else {
            return sprintf(_('%s subscribers, page %d'),
                           $this->user->nickname,
                           $this->page);
        }
    }

    function showPageNotice()
    {
        $user =& common_current_user();
        if ($user && ($user->id == $this->profile->id)) {
            $this->element('p', null,
                           _('These are the people who listen to '.
                             'your notices.'));
        } else {
            $this->element('p', null,
                           sprintf(_('These are the people who '.
                                     'listen to %s\'s notices.'),
                                   $this->profile->nickname));
        }
    }

    function showContent()
    {
        $offset = ($this->page-1) * PROFILES_PER_PAGE;
        $limit =  PROFILES_PER_PAGE + 1;

        if ($this->tag) {
            $subscribers = $this->user->getTaggedSubscribers($this->tag, $offset, $limit);
        } else {
            $subscribers = $this->user->getSubscribers($offset, $limit);
        }

        if ($subscribers) {
            $subscribers_list = new SubscribersList($subscribers, $this->user, $this);
            $subscribers_list->show();
        }

        $subscribers->free();

        $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                          $this->page, 'subscribers',
                          array('nickname' => $this->user->nickname));
    }
}

class SubscribersList extends ProfileList
{
    function showBlockForm()
    {
        $bf = new BlockForm($this->out, $profile,
                            array('action' => 'subscribers',
                                  'nickname' => $this->owner->nickname));
        $bf->show();
    }

    function isReadOnly()
    {
        return true;
    }
}
