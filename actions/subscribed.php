<?php

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
			common_element('img', array('src' => $subs->avatar,
										'class' => 'avatar'));
			common_end_element('a');
			
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