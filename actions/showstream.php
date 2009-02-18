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
require_once INSTALLDIR.'/lib/profileminilist.php';
require_once INSTALLDIR.'/lib/groupminilist.php';
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

    function isReadOnly()
    {
        return true;
    }

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

        common_set_returnto($this->selfUrl());

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
        $user =& common_current_user();
        if ($user && ($user->id == $this->profile->id)) {
            $this->element('h1', NULL, _("Your profile"));
        } else {
            $this->element('h1', NULL, sprintf(_('%s\'s profile'), $this->profile->nickname));
        }
    }

    function showPageNoticeBlock()
    {
        return;
    }

    function getFeeds()
    {
        return array(new Feed(Feed::RSS1,
                              common_local_url('userrss',
                                               array('nickname' => $this->user->nickname)),
                              sprintf(_('Notice feed for %s (RSS 1.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::RSS2,
                              common_local_url('api',
                                               array('apiaction' => 'statuses',
                                                     'method' => 'user_timeline',
                                                     'argument' => $this->user->nickname.'.rss')),
                              sprintf(_('Notice feed for %s (RSS 2.0)'),
                                      $this->user->nickname)),
                     new Feed(Feed::ATOM,
                              common_local_url('api',
                                               array('apiaction' => 'statuses',
                                                     'method' => 'user_timeline',
                                                     'argument' => $this->user->nickname.'.atom')),
                              sprintf(_('Notice feed for %s (Atom)'),
                                      $this->user->nickname)),
                     new Feed(Feed::FOAF,
                              common_local_url('foaf', array('nickname' =>
                                                             $this->user->nickname)),
                              sprintf(_('FOAF for %s'), $this->user->nickname)));
    }

    function extraHead()
    {
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
        $this->elementStart('div', 'entity_profile vcard author');
        $this->element('h2', null, _('User profile'));

        $avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);
        $this->elementStart('dl', 'entity_depiction');
        $this->element('dt', null, _('Photo'));
        $this->elementStart('dd');
        $this->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_PROFILE_SIZE),
                                    'class' => 'photo avatar',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $this->profile->nickname));
        $this->elementEnd('dd');

        $user = User::staticGet('id', $this->profile->id);
        $cur = common_current_user();
        if ($cur && $cur->id == $user->id) {
            $this->elementStart('dd');
            $this->element('a', array('href' => common_local_url('avatarsettings')), _('Edit Avatar'));
            $this->elementEnd('dd');
        }

        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_nickname');
        $this->element('dt', null, _('Nickname'));
        $this->elementStart('dd');
        $hasFN = ($this->profile->fullname) ? 'nickname url uid' : 'fn nickname url uid';
        $this->element('a', array('href' => $this->profile->profileurl,
                                  'rel' => 'me', 'class' => $hasFN),
                       $this->profile->nickname);
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if ($this->profile->fullname) {
            $this->elementStart('dl', 'entity_fn');
            $this->element('dt', null, _('Full name'));
            $this->elementStart('dd');
            $this->element('span', 'fn', $this->profile->fullname);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->profile->location) {
            $this->elementStart('dl', 'entity_location');
            $this->element('dt', null, _('Location'));
            $this->element('dd', 'label', $this->profile->location);
            $this->elementEnd('dl');
        }

        if ($this->profile->homepage) {
            $this->elementStart('dl', 'entity_url');
            $this->element('dt', null, _('URL'));
            $this->elementStart('dd');
            $this->element('a', array('href' => $this->profile->homepage,
                                      'rel' => 'me', 'class' => 'url'),
                           $this->profile->homepage);
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }

        if ($this->profile->bio) {
            $this->elementStart('dl', 'entity_note');
            $this->element('dt', null, _('Note'));
            $this->element('dd', 'note', $this->profile->bio);
            $this->elementEnd('dl');
        }

        $tags = Profile_tag::getTags($this->profile->id, $this->profile->id);
        if (count($tags) > 0) {
            $this->elementStart('dl', 'entity_tags');
            $this->element('dt', null, _('Tags'));
            $this->elementStart('dd');
            $this->elementStart('ul', 'tags xoxo');
            foreach ($tags as $tag) {
                $this->elementStart('li');
                // Avoid space by using raw output.
                $pt = '<span class="mark_hash">#</span><a rel="tag" href="' .
                      common_local_url('peopletag', array('tag' => $tag)) .
                      '">' . $tag . '</a>';
                $this->raw($pt);
                $this->elementEnd('li');
            }
            $this->elementEnd('ul');
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');

        $this->elementStart('div', 'entity_actions');
        $this->element('h2', null, _('User actions'));
        $this->elementStart('ul');
        $cur = common_current_user();

        if ($cur && $cur->id == $this->profile->id) {
            $this->elementStart('li', 'entity_edit');
            $this->element('a', array('href' => common_local_url('profilesettings'),
                                      'title' => _('Edit profile settings')),
                           _('Edit'));
            $this->elementEnd('li');
        }

        if ($cur) {
            if ($cur->id != $this->profile->id) {
                $this->elementStart('li', 'entity_subscribe');
                if ($cur->isSubscribed($this->profile)) {
                    $usf = new UnsubscribeForm($this, $this->profile);
                    $usf->show();
                } else {
                    $sf = new SubscribeForm($this, $this->profile);
                    $sf->show();
                }
                $this->elementEnd('li');
            }
        } else {
            $this->elementStart('li', 'entity_subscribe');
            $this->showRemoteSubscribeLink();
            $this->elementEnd('li');
        }

        if ($cur && $cur->id != $user->id && $cur->mutuallySubscribed($user)) {
            $this->elementStart('li', 'entity_send-a-message');
            $this->element('a', array('href' => common_local_url('newmessage', array('to' => $user->id)),
                                      'title' => _('Send a direct message to this user')),
                           _('Message'));
            $this->elementEnd('li');

            if ($user->email && $user->emailnotifynudge) {
                $this->elementStart('li', 'entity_nudge');
                $nf = new NudgeForm($this, $user);
                $nf->show();
                $this->elementEnd('li');
            }
        }

        if ($cur && $cur->id != $this->profile->id) {
            $blocked = $cur->hasBlocked($this->profile);
            $this->elementStart('li', 'entity_block');
            if ($blocked) {
                $ubf = new UnblockForm($this, $this->profile);
                $ubf->show();
            } else {
                $bf = new BlockForm($this, $this->profile);
                $bf->show();
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
                                  'class' => 'entity_remote_subscribe'),
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
        $this->showSubscriptions();
        $this->showSubscribers();
        $this->showGroups();
        $this->showStatistics();
        $cloud = new PersonalTagCloudSection($this, $this->user);
        $cloud->show();
    }

    function showSubscriptions()
    {
        $profile = $this->user->getSubscriptions(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscriptions',
                                         'class' => 'section'));

        $this->element('h2', null, _('Subscriptions'));

        if ($profile) {
            $pml = new ProfileMiniList($profile, $this->user, $this);
            $cnt = $pml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > PROFILES_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('subscriptions',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All subscriptions'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->user->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));

        $this->element('h2', null, _('Subscribers'));

        if ($profile) {
            $pml = new ProfileMiniList($profile, $this->user, $this);
            $cnt = $pml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > PROFILES_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('subscribers',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All subscribers'));
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

        $this->elementStart('div', array('id' => 'entity_statistics',
                                         'class' => 'section'));

        $this->element('h2', null, _('Statistics'));

        // Other stats...?
        $this->elementStart('dl', 'entity_member-since');
        $this->element('dt', null, _('Member since'));
        $this->element('dd', null, date('j M Y',
                                        strtotime($this->profile->created)));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscriptions');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscriptions',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscriptions'));
        $this->elementEnd('dt');
        $this->element('dd', null, (is_int($subs_count)) ? $subs_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_subscribers');
        $this->elementStart('dt');
        $this->element('a', array('href' => common_local_url('subscribers',
                                                             array('nickname' => $this->profile->nickname))),
                       _('Subscribers'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscribers', (is_int($subbed_count)) ? $subbed_count : '0');
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_notices');
        $this->element('dt', null, _('Notices'));
        $this->element('dd', null, (is_int($notice_count)) ? $notice_count : '0');
        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

    function showGroups()
    {
        $groups = $this->user->getGroups(0, GROUPS_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_groups',
                                         'class' => 'section'));

        $this->element('h2', null, _('Groups'));

        if ($groups) {
            $gml = new GroupMiniList($groups, $this->user, $this);
            $cnt = $gml->show();
            if ($cnt == 0) {
                $this->element('p', null, _('(None)'));
            }
        }

        if ($cnt > GROUPS_PER_MINILIST) {
            $this->elementStart('p');
            $this->element('a', array('href' => common_local_url('usergroups',
                                                                 array('nickname' => $this->profile->nickname)),
                                      'class' => 'more'),
                           _('All groups'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function showAnonymousMessage()
    {
		$m = sprintf(_('**%s** has an account on %%%%site.name%%%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                       'based on the Free Software [Laconica](http://laconi.ca/) tool. ' .
                       '[Join now](%%%%action.register%%%%) to follow **%s**\'s notices and many more! ([Read more](%%%%doc.help%%%%))'),
                     $this->user->nickname, $this->user->nickname);
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($m));
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
