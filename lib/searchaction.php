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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

class SearchAction extends Action {

	function handle($args) {
		parent::handle($args);
		$this->show_form();
	}

	function show_top($arr=NULL) {
		if ($arr) {
			$error = $arr[1];
		}
		if ($error) {
			common_element('p', 'error', $error);
		} else {
			$instr = $this->get_instructions();
			$output = common_markup_to_html($instr);
			common_element_start('div', 'instructions');
			common_raw($output);
			common_element_end('div');
		}
		$this->search_menu();
	}

	function get_title() {
		return NULL;
	}

	function show_header($arr) {
		return;
	}
	
	function show_form($error=NULL) {
		$q = $this->trimmed('q');
		$page = $this->trimmed('page', 1);
		
		common_show_header($this->get_title(), array($this, 'show_header'), array($q, $error),
						   array($this, 'show_top'));
		common_element_start('form', array('method' => 'post',
										   'id' => 'login',
										   'action' => common_local_url($this->trimmed('action'))));
		common_element_start('p');
		common_element('input', array('name' => 'q',
									  'id' => 'q',
									  'type' => 'text',
									  'class' => 'input_text',
									  'value' => ($q) ? $q : ''));
		common_text(' ');
		common_element('input', array('type' => 'submit',
									  'id' => 'search',
									  'name' => 'search',
									  'class' => 'submit',
									  'value' => _t('Search')));
					   
		common_element_end('p');
		common_element_end('form');
		if ($q) {
			$this->show_results($q, $page);
		}
		common_show_footer();
	}
	
	function search_menu() {
        # action => array('prompt', 'title')
        static $menu =
        array('peoplesearch' =>
              array('People',
              		'Find people on this site'),
			  'noticesearch' =>
			  array('Text',
					'Find content of notices'));
		$this->nav_menu($menu);
	}
}
