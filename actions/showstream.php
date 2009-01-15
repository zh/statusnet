<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/stream.php');

define('SUBSCRIPTIONS_PER_ROW', 4);
define('SUBSCRIPTIONS', 80);

class ShowstreamAction extends StreamAction
{

    function handle($args)
    {

        parent::handle($args);

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        # Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('showstream', $args), 301);
            return;
        }

        $user = User::staticGet('nickname', $nickname);

        if (!$user) {
            $this->no_such_user();
            return;
        }

        $profile = $user->getProfile();

        if (!$profile) {
            $this->serverError(_('User has no profile.'));
            return;
        }

        # Looks like we're good; start output

        # For YADIS discovery, we also have a <meta> tag

        header('X-XRDS-Location: '. common_local_url('xrds', array('nickname' =>
                                                                   $user->nickname)));

        common_show_header($profile->nickname,
                           array($this, 'show_header'), $user,
                           array($this, 'show_top'));

        $this->show_profile($profile);

        $this->show_notices($user);

        common_show_footer();
    }

    function show_top($user)
    {
        $cur = common_current_user();

        if ($cur && $cur->id == $user->id) {
            common_notice_form('showstream');
        }

        $this->views_menu();

        $this->show_feeds_list(array(0=>array('href'=>common_local_url('userrss', array('nickname' => $user->nickname)),
                                              'type' => 'rss',
                                              'version' => 'RSS 1.0',
                                              'item' => 'notices'),
                                     1=>array('href'=>common_local_url('usertimeline', array('nickname' => $user->nickname)),
                                              'type' => 'atom',
                                              'version' => 'Atom 1.0',
                                              'item' => 'usertimeline'),

                                     2=>array('href'=>common_local_url('foaf',array('nickname' => $user->nickname)),
                                              'type' => 'rdf',
                                              'version' => 'FOAF',
                                              'item' => 'foaf')));
    }

    function show_header($user)
    {
        # Feeds
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('api',
                                                                array('apiaction' => 'statuses',
                                                                      'method' => 'user_timeline.rss',
                                                                      'argument' => $user->nickname)),
                                     'type' => 'application/rss+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $user->nickname)));
        $this->element('link', array('rel' => 'alternate feed',
                                     'href' => common_local_url('api',
                                                                array('apiaction' => 'statuses',
                                                                      'method' => 'user_timeline.atom',
                                                                      'argument' => $user->nickname)),
                                     'type' => 'application/atom+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $user->nickname)));
        $this->element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('userrss', array('nickname' =>
                                                                               $user->nickname)),
                                     'type' => 'application/rdf+xml',
                                     'title' => sprintf(_('Notice feed for %s'), $user->nickname)));
        # FOAF
        $this->element('link', array('rel' => 'meta',
                                     'href' => common_local_url('foaf', array('nickname' =>
                                                                              $user->nickname)),
                                     'type' => 'application/rdf+xml',
                                     'title' => 'FOAF'));
        # for remote subscriptions etc.
        $this->element('meta', array('http-equiv' => 'X-XRDS-Location',
                                     'content' => common_local_url('xrds', array('nickname' =>
                                                                               $user->nickname))));
        $profile = $user->getProfile();
        if ($profile->bio) {
            $this->element('meta', array('name' => 'description',
                                         'content' => $profile->bio));
        }

        if ($user->emailmicroid && $user->email && $profile->profileurl) {
            $this->element('meta', array('name' => 'microid',
                                         'content' => "mailto+http:sha1:" . sha1(sha1('mailto:' . $user->email) . sha1($profile->profileurl))));
        }
        if ($user->jabbermicroid && $user->jabber && $profile->profileurl) {
            $this->element('meta', array('name' => 'microid',
                                         'content' => "xmpp+http:sha1:" . sha1(sha1('xmpp:' . $user->jabber) . sha1($profile->profileurl))));
        }

        # See https://wiki.mozilla.org/Microsummaries

        $this->element('link', array('rel' => 'microsummary',
                                     'href' => common_local_url('microsummary',
                                                                array('nickname' => $profile->nickname))));
    }

    function no_such_user()
    {
        $this->clientError(_('No such user.'), 404);
    }

    function show_profile($profile)
    {

        $this->elementStart('div', array('id' => 'profile', 'class' => 'vcard'));

        $this->show_personal($profile);

        $this->show_last_notice($profile);

        $cur = common_current_user();

        $this->show_subscriptions($profile);

        $this->elementEnd('div');
    }

    function show_personal($profile)
    {

        $avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
        $this->elementStart('div', array('id' => 'profile_avatar'));
        $this->element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_PROFILE_SIZE),
                                    'class' => 'avatar profile photo',
                                    'width' => AVATAR_PROFILE_SIZE,
                                    'height' => AVATAR_PROFILE_SIZE,
                                    'alt' => $profile->nickname));

        $this->elementStart('ul', array('id' => 'profile_actions'));

        $this->elementStart('li', array('id' => 'profile_subscribe'));
        $cur = common_current_user();
        if ($cur) {
            if ($cur->id != $profile->id) {
                if ($cur->isSubscribed($profile)) {
                    common_unsubscribe_form($profile);
                } else {
                    common_subscribe_form($profile);
                }
            }
        } else {
            $this->show_remote_subscribe_link($profile);
        }
        $this->elementEnd('li');

        $user = User::staticGet('id', $profile->id);
        common_profile_new_message_nudge($cur, $user, $profile);

        if ($cur && $cur->id != $profile->id) {
            $blocked = $cur->hasBlocked($profile);
            $this->elementStart('li', array('id' => 'profile_block'));
            if ($blocked) {
                common_unblock_form($profile, array('action' => 'showstream',
                                                    'nickname' => $profile->nickname));
            } else {
                common_block_form($profile, array('action' => 'showstream',
                                                  'nickname' => $profile->nickname));
            }
            $this->elementEnd('li');
        }

        $this->elementEnd('ul');

        $this->elementEnd('div');

        $this->elementStart('div', array('id' => 'profile_information'));

        if ($profile->fullname) {
            $this->element('h1', array('class' => 'fn'), $profile->fullname . ' (' . $profile->nickname . ')');
        } else {
            $this->element('h1', array('class' => 'fn nickname'), $profile->nickname);
        }

        if ($profile->location) {
            $this->element('p', 'location', $profile->location);
        }
        if ($profile->bio) {
            $this->element('p', 'description note', $profile->bio);
        }
        if ($profile->homepage) {
            $this->elementStart('p', 'website');
            $this->element('a', array('href' => $profile->homepage,
                                      'rel' => 'me', 'class' => 'url'),
                           $profile->homepage);
            $this->elementEnd('p');
        }

        $this->show_statistics($profile);

        $this->elementEnd('div');
    }

    function show_remote_subscribe_link($profile)
    {
        $url = common_local_url('remotesubscribe',
                                array('nickname' => $profile->nickname));
        $this->element('a', array('href' => $url,
                                  'id' => 'remotesubscribe'),
                       _('Subscribe'));
    }

    function show_unsubscribe_form($profile)
    {
        $this->elementStart('form', array('id' => 'unsubscribe', 'method' => 'post',
                                           'action' => common_local_url('unsubscribe')));
        $this->hidden('token', common_session_token());
        $this->element('input', array('id' => 'unsubscribeto',
                                      'name' => 'unsubscribeto',
                                      'type' => 'hidden',
                                      'value' => $profile->nickname));
        $this->element('input', array('type' => 'submit',
                                      'class' => 'submit',
                                      'value' => _('Unsubscribe')));
        $this->elementEnd('form');
    }

    function show_subscriptions($profile)
    {
        global $config;

        $subs = DB_DataObject::factory('subscription');
        $subs->subscriber = $profile->id;
        $subs->whereAdd('subscribed != ' . $profile->id);

        $subs->orderBy('created DESC');

        # We ask for an extra one to know if we need to do another page

        $subs->limit(0, SUBSCRIPTIONS + 1);

        $subs_count = $subs->find();

        $this->elementStart('div', array('id' => 'subscriptions'));

        $this->element('h2', null, _('Subscriptions'));

        if ($subs_count > 0) {

            $this->elementStart('ul', array('id' => 'subscriptions_avatars'));

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
                                                 'class' => 'subscription fn url'));
                $avatar = $other->getAvatar(AVATAR_MINI_SIZE);
                $this->element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
                                            'width' => AVATAR_MINI_SIZE,
                                            'height' => AVATAR_MINI_SIZE,
                                            'class' => 'avatar mini photo',
                                            'alt' =>  ($other->fullname) ?
                                            $other->fullname :
                                            $other->nickname));
                $this->elementEnd('a');
                $this->elementEnd('li');
            }

            $this->elementEnd('ul');
        }

        if ($subs_count > SUBSCRIPTIONS) {
            $this->elementStart('p', array('id' => 'subscriptions_viewall'));

            $this->element('a', array('href' => common_local_url('subscriptions',
                                                                 array('nickname' => $profile->nickname)),
                                      'class' => 'moresubscriptions'),
                           _('All subscriptions'));
            $this->elementEnd('p');
        }

        $this->elementEnd('div');
    }

    function show_statistics($profile)
    {

        // XXX: WORM cache this
        $subs = DB_DataObject::factory('subscription');
        $subs->subscriber = $profile->id;
        $subs_count = (int) $subs->count() - 1;

        $subbed = DB_DataObject::factory('subscription');
        $subbed->subscribed = $profile->id;
        $subbed_count = (int) $subbed->count() - 1;

        $notices = DB_DataObject::factory('notice');
        $notices->profile_id = $profile->id;
        $notice_count = (int) $notices->count();

        $this->elementStart('div', 'statistics');
        $this->element('h2', 'statistics', _('Statistics'));

        # Other stats...?
        $this->elementStart('dl', 'statistics');
        $this->element('dt', 'membersince', _('Member since'));
        $this->element('dd', 'membersince', date('j M Y',
                                                 strtotime($profile->created)));

        $this->elementStart('dt', 'subscriptions');
        $this->element('a', array('href' => common_local_url('subscriptions',
                                                             array('nickname' => $profile->nickname))),
                       _('Subscriptions'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscriptions', (is_int($subs_count)) ? $subs_count : '0');
        $this->elementStart('dt', 'subscribers');
        $this->element('a', array('href' => common_local_url('subscribers',
                                                             array('nickname' => $profile->nickname))),
                       _('Subscribers'));
        $this->elementEnd('dt');
        $this->element('dd', 'subscribers', (is_int($subbed_count)) ? $subbed_count : '0');
        $this->element('dt', 'notices', _('Notices'));
        $this->element('dd', 'notices', (is_int($notice_count)) ? $notice_count : '0');
        # XXX: link these to something
        $this->element('dt', 'tags', _('Tags'));
        $this->elementStart('dd', 'tags');
        $tags = Profile_tag::getTags($profile->id, $profile->id);

        $this->elementStart('ul', 'tags xoxo');
        foreach ($tags as $tag) {
            $this->elementStart('li');
            $this->element('a', array('rel' => 'bookmark tag',
                                      'href' => common_local_url('peopletag',
                                                                 array('tag' => $tag))),
                           $tag);
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
        $this->elementEnd('dd');

        $this->elementEnd('dl');

        $this->elementEnd('div');
    }

    function show_notices($user)
    {

        $page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        $notice = $user->getNotices(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $pnl = new ProfileNoticeList($notice);
        $cnt = $pnl->show();

        common_pagination($page>1, $cnt>NOTICES_PER_PAGE, $page,
                          'showstream', array('nickname' => $user->nickname));
    }

    function show_last_notice($profile)
    {

        $this->element('h2', null, _('Currently'));

        $notice = $profile->getCurrentNotice();

        if ($notice) {
            # FIXME: URL, image, video, audio
            $this->elementStart('p', array('class' => 'notice_current'));
            if ($notice->rendered) {
                $this->raw($notice->rendered);
            } else {
                # XXX: may be some uncooked notices in the DB,
                # we cook them right now. This can probably disappear in future
                # versions (>> 0.4.x)
                $this->raw(common_render_content($notice->content, $notice));
            }
            $this->elementEnd('p');
        }
    }
}

# We don't show the author for a profile, since we already know who it is!

class ProfileNoticeList extends NoticeList
{
    function newListItem($notice)
    {
        return new ProfileNoticeListItem($notice);
    }
}

class ProfileNoticeListItem extends NoticeListItem
{
    function showAuthor()
    {
        return;
    }
}
