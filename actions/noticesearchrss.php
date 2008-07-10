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

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class NoticesearchrssAction extends Rss10Action {

	function init() {
		return true;
	}
	
	function get_notices($limit=0) {

		$q = $this->trimmed('q');
		$notices = array();
		
		$notice = new Notice();

		# lcase it for comparison
		$q = strtolower($q);
		
		$notice->whereAdd('MATCH(content) against (\''.addslashes($q).'\')');
		$notice->orderBy('created DESC');
		
		# Ask for an extra to see if there's more.
		
		if ($limit != 0) {
			$notice->limit(0, $limit);
		}

		$notice->find();
		
		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}
		
		return $notices;
	}
	
	function get_channel() {
		global $config;
		$q = $this->trimmed('q');
		$c = array('url' => common_local_url('noticesearchrss', array('q' => $q)),
				   'title' => $config['site']['name'] . _t(' Search Stream for "' . $q . '"'),
				   'link' => common_local_url('noticesearch', array('q' => $q)),
				   'description' => _t('All updates matching search term "') . $q . '"');
		return $c;
	}
	
	function get_image() {
		return NULL;
	}
}