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

# XXX: make distinct from similar definitions in showstream.php

define('SUBSCRIPTIONS_PER_ROW', 10);
define('SUBSCRIPTIONS_PER_PAGE', 80);

class SubscriptionsAction extends Action {

	function handle($args) {
		parent::handle($args);
		$nickname = $this->arg('nickname');
		$profile = Profile::staticGet('nickname', $nickname);
		if (!$profile) {
			$this->no_such_user();
		}
		$user = User::staticGet($profile->id);
		if (!$user) {
			$this->no_such_user();
		}
		$page = $this->arg('page') || 1;
		common_show_header($profile->nickname . ": " . _t('Subscriptions'));
		$this->show_subscriptions($profile, $page);
		common_show_footer();
	}

	function show_subscriptions($profile, $page) {

		$subs = DB_DataObject::factory('subscription');
		$subs->subscriber = $profile->id;

		$subs->orderBy('created DESC');

		# We ask for an extra one to know if we need to do another page

		$subs->limit((($page-1)*SUBSCRIPTIONS_PER_PAGE), SUBSCRIPTIONS_PER_PAGE + 1);

		$subs_count = $subs->find();

		common_element_start('div', 'subscriptions');

		$idx = 0;

		while ($subs->fetch()) {
			
			$idx++;
			if ($idx % SUBSCRIPTIONS_PER_ROW == 1) {
				common_element_start('div', 'row');
			}

			$other = Profile::staticGet($subs->subscribed);
			
			common_element_start('a', array('title' => ($other->fullname) ?
											$other->fullname :
											$other->nickname,
											'href' => $other->profileurl,
											'class' => 'subscription'));
			$avatar = $other->getAvatar(AVATAR_STREAM_SIZE);
			common_element('img', 
						   array('src' => 
								 (($avatar) ? $avatar->url : 
								  common_default_avatar(AVATAR_STREAM_SIZE)),
								 'width' => AVATAR_STREAM_SIZE,
								 'height' => AVATAR_STREAM_SIZE,
								 'class' => 'avatar stream',
								 'alt' => ($other->fullname) ?
								 $other->fullname :
								 $other->nickname));
			common_element_end('a');

			# XXX: subscribe form here

			if ($idx % SUBSCRIPTIONS_PER_ROW == 0) {
				common_element_end('div');
			}

			if ($idx == SUBSCRIPTIONS_PER_PAGE) {
				break;
			}
		}

		if ($page > 1) {
			common_element('a', array('href' =>
									  common_local_url('subscriptions',
													   array('nickname' => $profile->nickname,
															 'page' => $page - 1)),
									  'class' => 'prev'),
					   _t('Previous'));
		}

		if ($subs_count > SUBSCRIPTIONS_PER_PAGE) {
			common_element('a', array('href' =>
									  common_local_url('subscriptions',
													   array('nickname' => $profile->nickname,
															 'page' => $page + 1)),
									  'class' => 'next'),
					   _t('Next'));
		}
		common_element_end('div');
	}
}