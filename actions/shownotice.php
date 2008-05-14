<?php

class ShownoticeAction extends Action {

	function handle($args) {
		parent::handle($args);
		$id = $this->arg('notice');
		$notice = Notice::staticGet($id);

		if (!$notice) {
			$this->no_such_notice();
		}

		if (!$notice->getProfile()) {
			$this->no_such_notice();
		}
		
		# Looks like we're good; show the header
	
		common_show_header($profile->nickname);
	
		$this->show_notice($notice);
	
		common_show_footer();
	}
	
	function no_such_notice() {
		common_user_error('No such notice.');
	}
	
	function show_notice($notice) {
		$profile = $notice->getProfile();
		# XXX: RDFa
		common_start_element('div', array('class' => 'notice'));
		# FIXME: add the avatar
		common_start_element('a', array('href' => $profile->profileurl,
										'class' => 'nickname'),
							 $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element('span', array('class' => 'content'),
					   $notice->content);
		common_element('span', array('class' => 'date'),
					   common_date_string($notice->created));
		common_end_element('div');
	}
}
