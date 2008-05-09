<?php

define('NOTICES_PER_PAGE', 20);

class StreamAction extends Action {

	function handle($args) {
		parent::handle($args);
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
		common_element('span', array('class' => 'content'), $notice->content);
		common_element('span', array('class' => 'date'),
					   common_date_string($notice->created));
		common_end_element('div');
	}
}
