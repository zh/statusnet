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

class ShowstreamAction extends StreamAction {

	function handle($args) {

		parent::handle($args);

		$nickname = common_canonical_nickname($this->arg('nickname'));
		$user = User::staticGet('nickname', $nickname);

		if (!$user) {
			$this->no_such_user();
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_t('User record exists without profile.'));
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

		$this->show_notices($profile);

		common_show_footer();
	}

	function show_top($user) {

		$cur = common_current_user();

		if ($cur && $cur->id == $user->id) {
			common_notice_form('showstream');
		}

		$this->views_menu();
	}

	function show_header($user) {
		common_element('link', array('rel' => 'alternate',
									 'href' => common_local_url('userrss', array('nickname' =>
																			   $user->nickname)),
									 'type' => 'application/rss+xml',
									 'title' => _t('Notice feed for ') . $user->nickname));
		common_element('link', array('rel' => 'meta',
									 'href' => common_local_url('foaf', array('nickname' =>
																			  $user->nickname)),
									 'type' => 'application/rdf+xml',
									 'title' => 'FOAF'));
		# for remote subscriptions etc.
		common_element('meta', array('http-equiv' => 'X-XRDS-Location',
									 'content' => common_local_url('xrds', array('nickname' =>
																			   $user->nickname))));
	}

	function no_such_user() {
		common_user_error('No such user');
	}

	function show_profile($profile) {

		common_element_start('div', array('id' => 'profile'));

		$this->show_personal($profile);

		$this->show_last_notice($profile);

		$cur = common_current_user();

		$this->show_subscriptions($profile);

		common_element_end('div');
	}

	function show_personal($profile) {

		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
		common_element_start('div', array('id' => 'profile_avatar'));
		common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_PROFILE_SIZE),
									'class' => 'avatar profile',
									'width' => AVATAR_PROFILE_SIZE,
									'height' => AVATAR_PROFILE_SIZE,
									'alt' => $profile->nickname));
		$cur = common_current_user();
		if ($cur) {
			if ($cur->id != $profile->id) {
				if ($cur->isSubscribed($profile)) {
					$this->show_unsubscribe_form($profile);
				} else {
					$this->show_subscribe_form($profile);
				}
			}
		} else {
			$this->show_remote_subscribe_link($profile);
		}
		common_element_end('div');

		common_element_start('div', array('id' => 'profile_information'));

		if ($profile->fullname) {
			common_element('h1', NULL, $profile->fullname . ' (' . $profile->nickname . ')');
		} else {
			common_element('h1', NULL, $profile->nickname);
		}

		
		if ($profile->location) {
			common_element('p', 'location', $profile->location);
		}
		if ($profile->bio) {
			common_element('p', 'description', $profile->bio);
		}
		if ($profile->homepage) {
			common_element_start('p', 'website');
			common_element('a', array('href' => $profile->homepage),
						   $profile->homepage);
			common_element_end('p');
		}

		$this->show_statistics($profile);

		common_element_end('div');
	}

	function show_subscribe_form($profile) {
		common_element_start('form', array('id' => 'subscribe', 'method' => 'post',
										   'action' => common_local_url('subscribe')));
		common_element('input', array('id' => 'subscribeto',
									  'name' => 'subscribeto',
									  'type' => 'hidden',
									  'value' => $profile->nickname));
		common_element('input', array('type' => 'submit',
									  'class' => 'submit',
									  'value' => _t('Subscribe')));
		common_element_end('form');
	}

	function show_remote_subscribe_link($profile) {
		$url = common_local_url('remotesubscribe',
		                        array('nickname' => $profile->nickname));
		common_element('a', array('href' => $url,
								  'id' => 'remotesubscribe'),
					   _t('Subscribe'));
	}

	function show_unsubscribe_form($profile) {
		common_element_start('form', array('id' => 'unsubscribe', 'method' => 'post',
										   'action' => common_local_url('unsubscribe')));
		common_element('input', array('id' => 'unsubscribeto',
									  'name' => 'unsubscribeto',
									  'type' => 'hidden',
									  'value' => $profile->nickname));
		common_element('input', array('type' => 'submit',
									  'class' => 'submit',
									  'value' => _t('Unsubscribe')));
		common_element_end('form');
	}

	function show_subscriptions($profile) {
		global $config;

		$subs = DB_DataObject::factory('subscription');
		$subs->subscriber = $profile->id;
		$subs->orderBy('created DESC');

		# We ask for an extra one to know if we need to do another page

		$subs->limit(0, SUBSCRIPTIONS + 1);

		$subs_count = $subs->find();

		common_element_start('div', array('id' => 'subscriptions'));

		common_element('h2', NULL, _t('Subscriptions'));

		if ($subs_count > 0) {

			common_element_start('ul', array('id' => 'subscriptions_avatars'));

			for ($i = 0; $i < min($subs_count, SUBSCRIPTIONS); $i++) {

				if (!$subs->fetch()) {
					common_debug('Weirdly, broke out of subscriptions loop early', __FILE__);
					break;
				}

				$other = Profile::staticGet($subs->subscribed);

				common_element_start('li');
				common_element_start('a', array('title' => ($other->fullname) ?
												$other->fullname :
												$other->nickname,
												'href' => $other->profileurl,
												'class' => 'subscription'));
				$avatar = $other->getAvatar(AVATAR_MINI_SIZE);
				common_element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
											'width' => AVATAR_MINI_SIZE,
											'height' => AVATAR_MINI_SIZE,
											'class' => 'avatar mini',
											'alt' =>  ($other->fullname) ?
											$other->fullname :
											$other->nickname));
				common_element_end('a');
				common_element_end('li');
			}

			common_element_end('ul');
		}

		if ($subs_count > SUBSCRIPTIONS) {
			common_element_start('p', array('id' => 'subscriptions_viewall'));

			common_element('a', array('href' => common_local_url('subscriptions',
																 array('nickname' => $profile->nickname)),
									  'class' => 'moresubscriptions'),
						   _t('All subscriptions'));
			common_element_end('p');
		}

		common_element_end('div');
	}

	function show_statistics($profile) {

		// XXX: WORM cache this
		$subs = DB_DataObject::factory('subscription');
		$subs->subscriber = $profile->id;
		$subs_count = (int) $subs->count();

		$subbed = DB_DataObject::factory('subscription');
		$subbed->subscribed = $profile->id;
		$subbed_count = (int) $subbed->count();

		$notices = DB_DataObject::factory('notice');
		$notices->profile_id = $profile->id;
		$notice_count = (int) $notices->count();

		common_element_start('div', 'statistics');
		common_element('h2', 'statistics', _t('Statistics'));

		# Other stats...?
		common_element_start('dl', 'statistics');
		common_element('dt', 'membersince', _t('Member since'));
		common_element('dd', 'membersince', date('j M Y', 
												 strtotime($profile->created)));
		
		common_element_start('dt', 'subscriptions');
		common_element('a', array('href' => common_local_url('subscriptions',
															 array('nickname' => $profile->nickname))),
					   _t('Subscriptions'));
		common_element_end('dt');
		common_element('dd', 'subscriptions', $subs_count);
		common_element_start('dt', 'subscribers');
		common_element('a', array('href' => common_local_url('subscribers',
															 array('nickname' => $profile->nickname))),
					   _t('Subscribers'));
		common_element_end('dt');
		common_element('dd', 'subscribers', $subbed_count);
		common_element('dt', 'notices', _t('Notices'));
		common_element('dd', 'notices', $notice_count);
		common_element_end('dl');

		common_element_end('div');
	}

	function show_notices($profile) {

		$notice = DB_DataObject::factory('notice');
		$notice->profile_id = $profile->id;

		$notice->orderBy('created DESC');

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		$notice->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

		$cnt = $notice->find();

		if ($cnt > 0) {
			common_element_start('ul', array('id' => 'notices'));

			for ($i = 0; $i < min($cnt, NOTICES_PER_PAGE); $i++) {
				if ($notice->fetch()) {
					$this->show_notice($notice);
				} else {
					// shouldn't happen!
					break;
				}
			}

			common_element_end('ul');
		}
		common_pagination($page>1, $cnt>NOTICES_PER_PAGE, $page,
						  'showstream', array('nickname' => $profile->nickname));
	}

	function show_last_notice($profile) {

		common_element('h2', NULL, _t('Currently'));

		$notice = DB_DataObject::factory('notice');
		$notice->profile_id = $profile->id;
		$notice->orderBy('created DESC');
		$notice->limit(0, 1);

		if ($notice->find(true)) {
			# FIXME: URL, image, video, audio
			common_element_start('p', array('class' => 'notice_current'));
			if ($notice->rendered) {
				common_raw($notice->rendered);
			} else {
				# XXX: may be some uncooked notices in the DB,
				# we cook them right now. This can probably disappear in future
				# versions (>> 0.4.x)
				common_raw(common_render_content($notice->content, $notice));
			}
			common_element_end('p');
		}
	}

	function show_notice($notice) {
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_element_start('li', array('class' => 'notice_single',
										 'id' => 'notice-' . $notice->id));
		$noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
		# FIXME: URL, image, video, audio
		common_element_start('p');
		common_raw(common_render_content($notice->content, $notice));
		common_element_end('p');
		common_element_start('p', array('class' => 'time'));
		common_element('a', array('class' => 'permalink',
								  'href' => $noticeurl,
								  'title' => common_exact_date($notice->created)),
					   common_date_string($notice->created));
		common_element_start('a', 
							 array('href' => common_local_url('newnotice',
															  array('replyto' => $profile->nickname)),
								   'onclick' => 'doreply("'.$profile->nickname.'"); return false',
								   'title' => _t('reply'),
								   'class' => 'replybutton'));
		common_raw('&rarr;');
		common_element_end('a');
		common_element_end('p');
		common_element_end('li');
	}
}