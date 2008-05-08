<?php

function handle_showstream() {
	
	$user_name = $_REQUEST['profile'];
	$profile = Profile::staticGet('nickname', $user_name);
	
	if (!$profile) {
		showstream_no_such_user();
	} 
	
	$user = User::staticGet($profile->id);

	if (!$user) {
		// remote profile
		showstream_no_such_user();
	}

	if ($profile->id == current_user()->id) {
		showstream_notice_form();
	}
	
	showstream_show_profile($profile);

	$notice = DB_DataObject::factory('notice');
	$notice->profile_id = $profile->id;
	$notice->limit(1, 10);
	
	$notice->find();
	
	while ($notice->fetch()) {
		showstream_show_notice($notice);
	}
}

function showstream_no_such_user() {
	common_user_error('No such user');
}

function showstream_notice_form() {
	// print notice form
}

function showstream_show_profile($profile) {
}