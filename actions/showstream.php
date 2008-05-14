<?php

define('SUBSCRIPTIONS_PER_ROW', 5);
define('SUBSCRIPTIONS', 80);

class ShowstreamAction extends StreamAction {

	function handle($args) {
		
		parent::handle($args);

		$nickname = common_canonicalize_nickname($this->arg('profile'));
		$user = User::staticGet('nickname', $nickname);

		if (!$user) {
			$this->no_such_user();
		} 

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_t('User record exists without profile.'));
		}
		
		# Looks like we're good; show the header
		
		common_show_header($profile->nickname);

		$cur = common_current_user();
		
		if ($cur && $profile->id == $cur->id) {
			$this->notice_form();
		}
	
		$this->show_profile($profile);

		$this->show_last_notice($profile);

		if ($cur) {
			if ($cur->isSubscribed($profile)) {
				$this->show_unsubscribe_form($profile);
			} else {
				$this->show_subscribe_form($profile);
			}
		}
		
		$this->show_statistics($profile);

		$this->show_subscriptions($profile);
		
		$this->show_notices($profile);
		
		common_show_footer();
	}
	
	function no_such_user() {
		common_user_error('No such user');
	}
	
	function notice_form() {
		common_start_element('form', array('id' => 'newnotice', 'method' => 'POST',
										   'action' => common_local_url('newnotice')));
		common_element('textarea', array('rows' => 4, 'cols' => 80, 'id' => 'content'));
		common_element('input', array('type' => 'submit'), 'Send');
		common_end_element('form');
	}
	
	function show_profile($profile) {
		common_start_element('div', 'profile');
		common_element('span', 'nickname', $profile->nickname);
		if ($profile->fullname) {
			if ($profile->homepage) {
				common_element('a', array('href' => $profile->homepage, 
										  'class' => 'fullname'),
							   $profile->fullname);
			} else {
				common_element('span', 'fullname', $profile->fullname);
			}
		}
		if ($profile->location) {
			common_element('span', 'location', $profile->location);
		}
		if ($profile->bio) {
			common_element('div', 'bio', $profile->bio);
		}
	}

	function show_subscribe_form($profile) {
		common_start_element('form', array('id' => 'subscribe', 'method' => 'POST',
										   'action' => common_local_url('subscribe')));
		common_element('input', array('id' => 'subscribeto',
									  'name' => 'subscribeto',
									  'type' => 'hidden',
									  'value' => $profile->nickname));
		common_element('input', array('type' => 'submit'), _t('subscribe'));
		common_end_element('form');
	}

	function show_unsubscribe_form($profile) {
		common_start_element('form', array('id' => 'unsubscribe', 'method' => 'POST',
										   'action' => common_local_url('unsubscribe')));
		common_element('input', array('id' => 'unsubscribeto',
									  'name' => 'unsubscribeto',
									  'type' => 'hidden',
									  'value' => $profile->nickname));
		common_element('input', array('type' => 'submit'), _t('unsubscribe'));
		common_end_element('form');
	}
	
	function show_subscriptions($profile) {
		
		# XXX: add a limit
		$subs = $profile->getLink('id', 'subscription', 'subscriber');
		common_start_element('div', 'subscriptions');
		
		$cnt = 0;
		
		while ($subs->fetch()) {
			$cnt++;
			if ($cnt % SUBSCRIPTIONS_PER_ROW == 1) {
				common_start_element('div', 'row');
			}

			common_start_element('a', array('title' => $subs->fullname ||
											           $subs->nickname,
											'href' => $subs->profileurl,
											'class' => 'subscription'));
			common_element('img', array('src' => $subs->avatar,
										'class' => 'avatar'));
			common_end_element('a');
			
			if ($cnt % SUBSCRIPTIONS_PER_ROW == 0) {
				common_end_element('div');
			}
			
			if ($cnt == SUBSCRIPTIONS) {
				break;
			}
		}

		common_element('a', array('href' => common_local_url('subscriptions', 
															 array('profile' => $profile->nickname))
								  'class' => 'moresubscriptions'),
					   _t('All subscriptions'));

		common_end_element('div');
	}

	function show_statistics($profile) {

		// XXX: WORM cache this
		$subs = DB_DataObject::factory('subscription');
		$subs->subscriber = $profile->id;
		$subs_count = $subs->count();
		
		$subbed = DB_DataObject::factory('subscription');
		$subbed->subscribed = $profile->id;
		$subbed_count = $subbed->count();
		
		$notices = DB_DataObject::factory('notice');
		$notice->profile_id = $profile->id;
		$notice_count = $notice->count();
		
		# Other stats...?
		common_start_element('dl', 'statistics');
		common_element('dt', _t('Subscriptions'));
		common_element('dd', $subs_count);
		common_element('dt', _t('Subscribers'));
		common_element('dd', $subbed_count);
		common_element('dt', _t('Notices'));
		common_element('dd', $notice_count);
		common_end_element('dl');
	}
	
	function show_notices($profile) {

		$notice = DB_DataObject::factory('notice');
		$notice->profile_id = $profile->id;
		
		$notice->orderBy('created DESC');
		
		$page = $this->arg('page') || 1;
		
		$notice->limit((($page-1)*NOTICES_PER_PAGE) + 1, NOTICES_PER_PAGE);
		
		$notice->find();

		common_start_element('div', 'notices');
		
		while ($notice->fetch()) {
			$this->show_notice($notice);
		}
		
		common_end_element('div');
	}
	
	function show_last_notice($profile) {
		$notice = DB_DataObject::factory('notice');
		$notice->profile_id = $profile->id;
		$notice->orderBy('created DESC');
		$notice->limit(1, 1);
		$notice->find();
		
		while ($notice->fetch()) {
			# FIXME: URL, image, video, audio
			common_element('span', array('class' => 'content'), 
						   $notice->content);
			common_element('span', array('class' => 'date'),
						   common_date_string($notice->created));
		}
	}
}
