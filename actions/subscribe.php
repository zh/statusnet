<?php

class SubscribeAction extends Action {
	function handle($args) {
		parent::handle($args);
		
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
			return;
		}
		
		$other_nickname = $this->arg('subscribeto');

		$other = User::staticGet('nickname', $other_nickname);
		
		if (!$other) {
			common_user_error(_t('No such user.'));
			return;
		}
		
		$user = common_current_user();

		if ($user->isSubscribed($other)) {
			common_user_error(_t('Already subscribed!.'));
			return;
		}
		
		$sub = new Subscription();
		$sub->subscriber = $user->id;
		$sub->subscribed = $other->id;
		
		$sub->created = time();
		
		if (!$sub->insert()) {
			common_server_error(_t('Couldn\'t create subscription.'));
			return;
		}
		
		common_redirect(common_local_url('all', array('nickname' =>
													  $user->nickname)));
	}
}