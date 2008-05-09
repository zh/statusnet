<?php

class AllAction extends ShowstreamAction {

	// XXX: push this up to a common function.
	
	function show_notices($profile) {

		$notice = DB_DataObject::factory('notice');
		
		# XXX: chokety and bad
 		
		$notice->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = {$profile->id})', 'OR');
		$notice->whereAdd('profile_id = {$profile->id}', 'OR');
		
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
}
