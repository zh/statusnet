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

require_once(INSTALLDIR.'/lib/rssaction.php');

// Formatting of RSS handled by Rss10Action

class FavoritedrssAction extends Rss10Action {

	function init() {
		return true;
	}

	function get_notices($limit=0) {

		$qry =
			'SELECT notice_id, sum(exp(-(now() - modified)/864000)) as weight ' .
			'FROM fave GROUP BY notice_id ' .
			'ORDER BY weight DESC';

		$offset = 0;
		$total = ($limit == 0) ? 48 : $limit;

		if (common_config('db','type') == 'pgsql') {
			$qry .= ' LIMIT ' . $total . ' OFFSET ' . $offset;
		} else {
			$qry .= ' LIMIT ' . $offset . ', ' . $limit;
		}

		$fave = new Fave;
		$fave->query($qry);

		$notice_list = array();

		while ($fave->fetch()) {
		  array_push($notice_list, $fave->notice_id);
		}

		$notice = new Notice();

		$notice->query(sprintf('SELECT * FROM notice WHERE id in (%s)',
			implode($notice_list, ',')));

		$notices = array();

		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}

		return $notices;
	}

	function get_channel() {
		global $config;
		$c = array('url' => common_local_url('favoritedrss'),
				   'title' => sprintf(_('%s Most Favorited Stream'), $config['site']['name']),
				   'link' => common_local_url('favorited'),
				   'description' => sprintf(_('Most favorited updates for %s'), $config['site']['name']));
		return $c;
	}

	function get_image() {
		return NULL;
	}
}