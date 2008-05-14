<?php

class UnsubscribeAction extends Action {
	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
			return;
		}
		$other_nickname = $this->arg('unsubscribeto');
		$other = User::staticGet('nickname', $other_nickname);
		if (!$other) {
			common_user_error(_t('No such user.'));
			return;
		}
		
		$user = common_current_user();

		if (!$user->isSubscribed($other)) {
			common_server_error(_t('Not subscribed!.'));
		}
		
		$sub = new Subscription();
		$sub->subscriber = $user->id;
		$sub->subscribed = $other->id;
		
		if (!$sub->delete()) {
			common_server_error(_t('Couldn\'t delete subscription.'));
			return;
		}
		
		common_redirect(common_local_url('all', array('nickname' =>
													  $user->nickname)));
	}
}
