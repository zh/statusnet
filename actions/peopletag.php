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

require_once(INSTALLDIR.'/lib/profilelist.php');

class PeopletagAction extends Action {
	
	function handle($args) {

		parent::handle($args);

		$tag = $this->trimmed('tag');
		
		if (!common_valid_profile_tag($tag)) {
			$this->client_error(sprintf(_('Not a valid people tag: %s'), $tag));
			return;
		}

		$page = $this->trimmed('page');
		
		if (!$page) {
			$page = 1;
		}
		
		# Looks like we're good; show the header

		common_show_header(sprintf(_('Users self-tagged with %s - page %d'), $tag, $page),
						   NULL, $tag, array($this, 'show_top'));

		$this->show_people($tag);

		common_show_footer();
	}

	function show_people($tag) {
		
		$profile = new Profile();

		$offset = ($page-1)*PROFILES_PER_PAGE;
		$limit = PROFILES_PER_PAGE + 1;
		
		if (common_config('db','type') == 'pgsql') {
			$lim = ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		} else {
			$lim = ' LIMIT ' . $offset . ', ' . $limit;
		}

		# XXX: memcached this
		
		$profile->query(sprintf('SELECT profile.* ' .
								'FROM profile JOIN profile_tag ' .
								'ON profile.id = profile_tag.tagger ' .
								'WHERE profile_tag.tagger = profile_tag.tagged ' .
								'AND tag = "%s" ' .
								'ORDER BY profile_tag.created DESC ' . 
								$lim, $tag));

		$pl = new ProfileList($profile);
		$cnt = $pl->show_list();
		
		common_pagination($page > 1,
						  $cnt > PROFILES_PER_PAGE,
						  $page,
						  $this->trimmed('action'),
						  array('tag' => $tag));
	}
	
	function show_top($tag) {
		$instr = sprintf(_('These are users who have tagged themselves "%s" ' .
						   'to show a common interest, characteristic, hobby or job.'), $tag);
		common_element_start('div', 'instructions');
		common_text($instr);
		common_element_end('div');
	}

	function get_title() {
		return NULL;
	}

	function show_header($arr) {
		return;
	}
}
