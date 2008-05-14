<?php

class NewnoticeAction extends Action {
	
	function handle($args) {
		parent::handle($args);
		# XXX: Ajax!

		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
		} else if ($this->arg('METHOD') == 'POST') {
			if ($this->save_new_notice()) {
				# XXX: smarter redirects
				$user = common_current_user();
				assert(!is_null($user)); # see if... above
				# XXX: redirect to source
				# XXX: use Ajax instead of a redirect
				common_redirect(common_local_url('all',
												 array('nickname' =>
													   $user->nickname)));
			} else {
				common_server_error(_t('Problem saving notice.'));
			}
		} else {
			$this->show_form();
		}
	}
	
	function save_new_notice() {
		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$notice = DB_DataObject::factory('notice');
		assert($notice);
		$notice->profile_id = $user->id; # user id *is* profile id
		$notice->content = $this->arg('content');
		$notice->created = time();
		return $notice->insert();
	}
	
	function show_form() {
		common_start_element('form', array('id' => 'newnotice', 'method' => 'POST',
										   'action' => common_local_url('newnotice')));
		common_element('span', 'nickname', $profile->nickname);
		common_element('textarea', array('rows' => 4, 'cols' => 80, 'id' => 'content'));
		common_element('input', array('type' => 'submit'), 'Send');
		common_end_element('form');
	}
}