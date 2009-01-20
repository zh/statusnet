<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * User profile page
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
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
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
 * User profile page
 *
 * When I created this page, "show stream" seemed like the best name for it.
 * Now, it seems like a really bad name.
 *
 * It shows a stream of the user's posts, plus lots of profile info, links
 * to subscriptions and stuff, etc.
 *
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ShowstreamAction extends Action
{
    var $user = null;
    var $page = null;
    var $profile = null;

    function title()
    {
        if ($this->page == 1) {
            return $this->user->nickname;
        } else {
            return sprintf(_("%s, page %d"),
                           $this->user->nickname,
                           $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('showstream', $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        return true;
    }

    function handle($args)
    {

        // Looks like we're good; start output

        // For YADIS discovery, we also have a <meta> tag

        header('X-XRDS-Location: '. common_local_url('xrds', array('nickname' =>
                                                                   $this->user->nickname)));

        $this->showPage();
    }

    function showContent()
    {
        $this->showProfile();
        $this->showNotices();
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showPageTitle()
    {
         $this->element('h1', NULL, $this->profile->nickname._("'s profile"));
    }

    function showPageNoticeBlock()
    {
        return;
    }

    function showExportData()
    {
        $fl = new FeedList($this);
        $fl->show(array(0=>array('href'=>common_local_url('userrss',
                                                          array('nickname' => $this->user->nickname)),
                                 'type' => 'rss',
                                 'version' => 'RSS 1.0',
                                 'item' => 'notices'),
                        1=>array('href'=>common_local_url('usertimeline',
                                                          array('nickname' => $this->user->nickname)),
                                 'type' => 'atom',
                                 'version' => 'Atom 1.0',
                                 'item' => 'usertimeline'),
                        2=>array('href'=>common_local_url('foaf',
                                                          array('nickname' => $this->user->nickname)),
                                 'type' => 'rdf',
                                 'version' => 'FOAF',
                                 'item' => 'foaf')));
    }

    function showFeeds()
    {
        // Feeds
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('api',
                                                                array('apiaction' => 'statuses',
                                                                      'method' => 'user_timeline.rss',
                                                                      'argument' => $this->user->nickname)),
                                     'type' => 'application/rss+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $this->user->nickname)));
        $this->element('link', array('rel' => 'alternate feed',
                                     'href' => common_local_url('api',
                                                                array('apiaction' => 'statuses',
                                                                      'method' => 'user_timeline.atom',
                                                                      'argument' => $this->user->nickname)),
                                     'type' => 'application/atom+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $this->user->nickname)));
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('userrss', array('nickname' =>
                                                                               $this->user->nickname)),
                                     'type' => 'application/rdf+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $this->user->nickname)));
    }

    function extraHead()
    {
        // FOAF
        $this->element('link', array('rel' => 'meta',
                                     'href' => common_local_url('foaf', array('nickname' =>
                                                                              $this->user->nickname)),
                                     'type' => 'application/rdf+xml',
                                     'title' => 'FOAF'));
        // for remote subscriptions etc.
        $this->element('meta', array('http-equiv' => 'X-XRDS-Location',
                                     'content' => common_local_url('xrds', array('nickname' =>
                                                                               $this->user->nickname))));

        if ($this->profile->bio) {
            $this->element('meta', array('name' => 'description',
                                         'content' => $this->profile->bio));
        }

        if ($this->user->emailmicroid && $this->user->email && $this->profile->profileurl) {
            $id = new Microid('mailto:'.$this->user->email,
                              $this->selfUrl());
            $this->element('meta', array('name' => 'microid',
                                         'content' => $id->toString()));
        }
        if ($this->user->jabbermicroid && $this->user->jabber && $this->profile->profileurl) {
            $id = new Microid('xmpp:'.$this->user->jabber,
                              $this->selfUrl());
            $this->element('meta', array('name' => 'microid',
                                         'content' => $id->toString()));
        }

        // See https://wiki.mozilla.org/Microsummaries

        $this->element('link', array('rel' => 'microsummary',
                                     'href' => common_local_url('microsummary',
                                                                array('nickname' => $this->profile->nickname))));
    }

    function showProfile()
    {
        $this->elementStart('div', array('id' => 'user_profile', 'class' => 'vcard author'));
        $this->element('h2', null, _('User profile'));

        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        $this->elementStart('dl', 'user_depiction');
        $this->element('dt', null, _('Photo'));
        $this->elementStart('dd');
        $this->element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_PROFILE_SIZE),
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $this->profile->nickname));
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'user_nickname');
        $this->element('dt', null, _('Nickname'));
        $this->elementStart('dd');
        $hasFN = ($this->profile->fullname) ? 'nickname url uid' : 'fn nickname url uid';
        $this->element('a', array('href' => $this->profile->profileurl,
                                  'rel' => 'me', 'class' => $hasFN),
                            $this->profile->nickname);
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if ($this->profile->fullname) {
            $this->elementStart('dl', 'user_fn');
            $this->element('dt', null, _('Full name'));
            $this->elementStart('dd');
            $this->element('span', 'fn', $this->profile->fullname);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->profile->location) {
            $this->elementStart('dl', 'user_location');
            $this->element('dt', null, _('Location'));
            $this->element('dd', 'location', $this->profile->location);
            $this->elementEnd('dl');
        }

        if ($this->profile->homepage) {
            $this->elementStart('dl', 'user_url');
            $this->element('dt', null, _('URL'));
            $this->elementStart('dd');
            $this->element('a', array('href' => $this->profile->homepage,
                                      'rel' => 'me', 'class' => 'url'),
                           $this->profile->homepage);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->profile->bio) {
            $this->elementStart('dl', 'user_note');
            $this->element('dt', null, _('Note'));
            $this->element('dd', 'note', $this->profile->bio);
            $this->elementEnd('dl');
        }

        $tags = Profile_tag::getTags($this->profile->id, $this->profile->id);
        if (count($tags) > 0) {
            $this->elementStart('dl', 'user_tags');
            $this->element('dt', null, _('Tags'));
            $this->elementStart('dd', 'tags');
            $this->elementStart('ul', 'tags xoxo');
            foreach ($tags as $tag) {
                $this->elementStart('li');
                $this->element('span', 'mark_hash', '#');
                $this->element('a', array('rel' => 'tag',
                                          'href' => common_local_url('peopletag',
                                                                     array('tag' => $tag))),
                               $tag);
                $this->elementEnd('li');
            }
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');


        $this->elementStart('div', array('id' => 'user_actions'));
        $this->element('h2', null, _('User actions'));
        $this->elementStart('ul');
        $this->elementStart('li', array('id' => 'user_subscribe'));
        $cur = common_current_user();
        if ($cur) {
            if ($cur->id != $this->profile->id) {
                if ($cur->isSubscribed($this->profile)) {
                    $sf = new SubscribeForm($this, $this->profile);
                    $sf->show();
                } else {
                    $usf = new UnsubscribeForm($this, $this->profile);
                    $usf->show();
                }
            }
        } else {
            $this->showRemoteSubscribeLink();
        }
        $this->elementEnd('li');

        common_profile_new_message_nudge($cur, $this->user, $this->profile);

        if ($cur && $cur->id != $this->profile->id) {
            $blocked = $cur->hasBlocked($this->profile);
            $this->elementStart('li', array('id' => 'user_block'));
            if ($blocked) {
                $bf = new BlockForm($this, $this->profile);
                $bf->show();
            } else {
                $ubf = new UnblockForm($this, $this->profile);
                $ubf->show();
            }
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
        $this->elementEnd('div');
    }

    function showRemoteSubscribeLink()
    {
        $url = common_local_url('remotesubscribe',
                                array('nickname' => $this->profile->nickname));
        $this->element('a', array('href' => $url,
                                  'id' => 'remotesubscribe'),
                       _('Subscribe'));
    }

    function showNotices()
    {
        $notice = $this->user->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $pnl = new ProfileNoticeList($notice, $this);
        $cnt = $pnl->show();

        $this->pagination($this->page>1, $cnt>NOTICES_PER_PAGE, $this->page,
                          'showstream', array('nickname' => $this->user->nickname));
    }

    function showSections()
    {
        $this->showStatistics();
        $this->showSubscriptions();
    }

    function showSubscriptions()
    {
        $subs = new Subscription();
        $subs->subscriber = $this->profile->id;
        $subs->whereAdd('subscribed != ' . $this->profile->id);

        $subs->orderBy('created DESC');

        // We ask for an extra one to know if we need to do another page

        $subs->limit(0, SUBSCRIPTIONS + 1);

        $subs_count = $subs->find();

        $this->elementStart('div', array('id' => 'user_subscriptions',
                                         'class' => 'section'));

        $this->element('h2', null, _('Subscriptions'));

        if ($subs_count > 0) {

            $this->elementStart('ul', 'users');

            for ($i = 0; $i < min($subs_count, SUBSCRIPTIONS); $i++) {

                if (!$subs->fetch()) {
                    common_debug('Weirdly, broke out of subscriptions loop early', __FILE__);
                    break;
                }

                $other = Profile::staticGet($subs->subscribed);

                if (!$other) {
                    common_log_db_error($subs, 'SELECT', __FILE__);
                    continue;
                }

                $this->elementStart('li', 'vcard');
                $this->elementStart('a', array('title' => ($other->fullname) ?
                                                $other->fullname :
                                                $other->nickname,
                                                'href' => $other->profileurl,
                                                'rel' => 'contact',
                                                 'class' => 'url'));
                $avatar = $other->getAvatar(AVATAR_MINI_SIZE);
                $this->element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
                                            'width' => AVATAR_MINI_SIZE,
                                            'height' => AVATAR_MINI_SIZE,
                                            'class' => 'avatar photo',
                                            'alt' =>  ($other->fullname) ?
                                            $other->fullname :
                                            $other->nickname));
                $this->element('span', 'fn nickname', $other->nickname);
                $this->elementEnd('a');
                $this->elementEnd('li');
            }

            $this->elementEnd('ul');
        }

        if ($subs_count > SUBSCRIPTIONS) {
            $this->elementStart('p');

            $this->element('a', array('href' => common_local_url('subscriptions',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'mores'),
                           _('All subscriptions'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function showStatistics()
    {
        // XXX: WORM cache this
        $subs = new Subscription();
        $subs->subscriber = $this->profile->id;
        $subs_count = (int) $subs->count() - 1;

        $subbed = new Subscription();
        $subbed->subscribed = $this->profile->id;
        $subbed_count = (int) $subbed->count() - 1;

        $notices = new Notice();
        $notices->profile_id = $this->profile->id;
        $notice_count = (int) $notices->count();

        $this->elementStart('div', array('id' => 'user_statistics',
                                         'class' => 'section'));

        $this->element('h2', null, _('Statistics'));

        // Other stats...?
        $this->elementStart('dl', 'user_member-since');
        $this->element('dt', null, _('Member since'));
        $this->element('dd', null, date('j M Y',
                                                 strtotime($this->profile->created)));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'user_subscriptions');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscriptions',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscriptions'));
        $this->elementEnd('dt');
        $this->element('dd', null, (is_int($subs_count)) ? $subs_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'user_subscribers');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscribers',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscribers'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscribers', (is_int($subbed_count)) ? $subbed_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'user_notices');
        $this->element('dt', null, _('Notices'));
        $this->element('dd', null, (is_int($notice_count)) ? $notice_count : '0');
        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

}

// We don't show the author for a profile, since we already know who it is!

class ProfileNoticeList extends NoticeList
{
    function newListItem($notice)
    {
        return new ProfileNoticeListItem($notice, $this->out);
    }
}

class ProfileNoticeListItem extends NoticeListItem
{
    function showAuthor()
    {
        return;
    }
}
