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

if (!defined('LACONICA')) { exit(1) }

class SubscribedAction extends Action {

	# Who is subscribed to a given user?
	
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
		$this->show_subscribed($profile, $page);
	}

	function show_subscribed($profile, $page) {

		$sub = DB_DataObject::factory('subscriptions');
		$sub->subscribed = $profile->id;
		
		# We ask for an extra one to know if we need to do another page
		
		$sub->limit((($page-1)*SUBSCRIPTIONS_PER_PAGE)+1, SUBSCRIPTIONS_PER_PAGE + 1);

		$subs_count = $subs->find();
		
		common_start_element('div', 'subscriptions');
		
		$idx = 0;
		
		while ($subs->fetch()) {
			$idx++;
			if ($idx % SUBSCRIPTIONS_PER_ROW == 1) {
				common_start_element('div', 'row');
			}

			common_start_element('a', array('title' => $subs->fullname ||
											           $subs->nickname,
											'href' => $subs->profileurl,
											'class' => 'subscription'));
			$avatar = $subs->getAvatar(AVATAR_STREAM_SIZE);
			common_element('img', array('src' => (($avatar) ? $avatar->url : DEFAULT_STREAM_AVATAR),
										'width' => AVATAR_STREAM_SIZE,
										'height' => AVATAR_STREAM_SIZE,
										'class' => 'avatar stream'));
			common_end_element('a');

			# XXX: subscribe form here
			
			if ($idx % SUBSCRIPTIONS_PER_ROW == 0) {
				common_end_element('div');
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
		common_end_element('div');
	}
}