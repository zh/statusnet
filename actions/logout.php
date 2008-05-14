<?php

class LogoutAction extends Action {
	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
		} else {
			common_set_user(NULL);
			common_redirect(common_local_url('main'));
		}
	}
}
