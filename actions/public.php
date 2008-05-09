<?php

class PublicAction extends StreamAction {

	function handle($args) {
		parent::handle($args);

		$page = $this->arg('page') || 1;

		common_show_header(_t('Public timeline'));

		# XXX: Public sidebar here?

		$this->show_notices($page);

		common_show_footer();
	}

	function show_notices($page) {

		$notice = DB_DataObject::factory('notice');

		# XXX: filter out private notifications

		$notice->orderBy('created DESC');
		$notice->limit((($page-1)*NOTICES_PER_PAGE) + 1, NOTICES_PER_PAGE);

		$notice->find();

		common_start_element('div', 'notices');

		while ($notice->fetch()) {
			$this->show_notice($notice);
		}

		common_end_element('div');
	}
}

