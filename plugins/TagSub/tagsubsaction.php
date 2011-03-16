<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List of a user's subscriptions
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
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * A list of the user's subscriptions
 *
 * @category Social
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */
class TagSubsAction extends GalleryAction
{
    function title()
    {
        if ($this->page == 1) {
            // TRANS: Header for subscriptions overview for a user (first page).
            // TRANS: %s is a user nickname.
            return sprintf(_m('%s\'s tag subscriptions'), $this->user->nickname);
        } else {
            // TRANS: Header for subscriptions overview for a user (not first page).
            // TRANS: %1$s is a user nickname, %2$d is the page number.
            return sprintf(_m('%1$s\'s tag subscriptions, page %2$d'),
                           $this->user->nickname,
                           $this->page);
        }
    }

    function showPageNotice()
    {
        $user = common_current_user();
        if ($user && ($user->id == $this->profile->id)) {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all tag subscriptions
                           // TRANS: of the logged in user's own profile.
                           _m('You have subscribed to receive all notices on this site containing the following tags.'));
        } else {
            $this->element('p', null,
                           // TRANS: Page notice for page with an overview of all subscriptions of a user other
                           // TRANS: than the logged in user. %s is the user nickname.
                           sprintf(_m('%s has subscribed to receive all notices on this site containing the following tags.'),
                                   $this->profile->nickname));
        }
    }

    function showContent()
    {
        if (Event::handle('StartShowTagSubscriptionsContent', array($this))) {
            parent::showContent();

            $offset = ($this->page-1) * PROFILES_PER_PAGE;
            $limit =  PROFILES_PER_PAGE + 1;

            $cnt = 0;

            $tagsub = new TagSub();
            $tagsub->profile_id = $this->user->id;
            $tagsub->limit($limit);
            $tagsub->offset($offset);
            $tagsub->find();

            if ($tagsub->N) {
                $list = new TagSubscriptionsList($tagsub, $this->user, $this);
                $cnt = $list->show();
                if (0 == $cnt) {
                    $this->showEmptyListMessage();
                }
            }

            $this->pagination($this->page > 1, $cnt > PROFILES_PER_PAGE,
                              $this->page, 'tagsubs',
                              array('nickname' => $this->user->nickname));


            Event::handle('EndShowTagSubscriptionsContent', array($this));
        }
    }

    function showEmptyListMessage()
    {
        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->user->id === $current_user->id) {
                // TRANS: Subscription list text when the logged in user has no subscriptions.
                // TRANS: This message contains Markdown URLs. The link description is between
                // TRANS: square brackets, and the link between parentheses. Do not separate "]("
                // TRANS: and do not change the URL part.
                $message = _('You\'re not listening to anyone\'s notices right now, try subscribing to people you know. '.
                             'Try [people search](%%action.peoplesearch%%), look for members in groups you\'re interested '.
                             'in and in our [featured users](%%action.featured%%). '.
                             'If you\'re a [Twitter user](%%action.twittersettings%%), you can automatically subscribe to '.
                             'people you already follow there.');
            } else {
                // TRANS: Subscription list text when looking at the subscriptions for a of a user other
                // TRANS: than the logged in user that has no subscriptions. %s is the user nickname.
                $message = sprintf(_('%s is not listening to any tags.'), $this->user->nickname);
            }
        }
        else {
            // TRANS: Subscription list text when looking at the subscriptions for a of a user that has none
            // TRANS: as an anonymous user. %s is the user nickname.
            $message = sprintf(_m('%s is not listening to any tags.'), $this->user->nickname);
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }
}

// XXX SubscriptionsList and SubscriptionList are dangerously close

class TagSubscriptionsList extends SubscriptionList
{
    function newListItem($tagsub)
    {
        return new TagSubscriptionsListItem($tagsub, $this->owner, $this->action);
    }
}

class TagSubscriptionsListItem extends SubscriptionListItem
{
    function showProfile()
    {
        $this->startProfile();
        // Relevant portion!
        $cur = common_current_user();
        if (!empty($cur) && $cur->id == $this->owner->id) {
            $this->showOwnerControls();
        }
        $this->endProfile();
    }

    function showOwnerControls()
    {
        $tagsub = $this->profile; // ?
        $form = new TagSubForm($this->out, $tagsub->tag);
        $form->show();
        return;
    }
}
