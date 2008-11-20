<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	 See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.	 If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/stream.php');

class FeaturedAction extends StreamAction {

	function handle($args) {
		parent::handle($args);

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		common_show_header(_('Featured timeline'),
						   array($this, 'show_header'), NULL,
						   array($this, 'show_top'));

		$this->show_notices($page);

		common_show_footer();
	}

	function show_top() {
		$instr = $this->get_instructions();
		$output = common_markup_to_html($instr);
		common_element_start('div', 'instructions');
		common_raw($output);
		common_element_end('div');
		$this->public_views_menu();
	}

	function get_instructions() {
		return _('Featured users');
	}

	function show_header() {

		// XXX need to make the RSS feed for this

		//common_element('link', array('rel' => 'alternate',
		//							 'href' => common_local_url('featuredrss'),
		//							 'type' => 'application/rss+xml',
		//							 'title' => _('Featured Stream Feed')));

	}

	function show_notices($page) {

		$featured = common_config('nickname', 'featured');

		if (count($featured) > 0) {

			$id_list = array();

			foreach($featured as $featuree) {
				$profile = Profile::staticGet('nickname', trim($featuree));
				array_push($id_list, $profile->id);
			}

			// XXX: Show a list of users (people list) instead of shit crap

			$qry =
				'SELECT * ' .
				'FROM notice ' .
				'WHERE profile_id IN (%s) ';

			$cnt = 0;

			$notice = Notice::getStream(sprintf($qry, implode($id_list, ',')),
				'featured_stream', ($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

			if ($notice) {
				common_element_start('ul', array('id' => 'notices'));
				while ($notice->fetch()) {
					$cnt++;
					if ($cnt > NOTICES_PER_PAGE) {
						break;
					}
					$this->show_notice($notice);
				}
				common_element_end('ul');
			}

			common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
							  $page, 'featured');

		}
	}

}