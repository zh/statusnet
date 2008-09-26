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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class Twitapidirect_messagesAction extends TwitterapiAction {


	function is_readonly() {

		static $write_methods = array(	'direct_messages',
										'sent');

		$cmdtext = explode('.', $this->arg('method'));

		if (in_array($cmdtext[0], $write_methods)) {
			return false;
		}

		return true;
	}

	function direct_messages($args, $apidata) {
		parent::handle($args);

		$user = $apidata['user'];

		$count = $this->arg('count');
		$since = $this->arg('since');
		$since_id = $this->arg('since_id');
		$page = $this->arg('page');

		if (!$page) {
			$page = 1;
		}

		if (!$count) {
			$count = 20;
		}

		$message = new Message();
		$message->to_profile = $user->id;
		$message->orderBy('created DESC, id DESC');
		$message->limit((($page-1)*20), $count);

		$message->find();

		$title = 'Direct messages to ' . $user->nickname;
		$subtitle = 'All the direct messages sent to ' . $user->nickname;

		$server = common_root_url();
		$link = $server . $user->nickname . '/inbox';

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_dmsgs($message);
			break;
		 case 'rss':
			$this->show_rss_dmsgs($message, $title, $link, $subtitle);
			break;
		 case 'atom':
			$this->show_atom_dmsgs($message, $title, $link, $subtitle);
			break;
		 case 'json':
			$this->show_json_dmsgs($message);
			break;
		 default:
			common_user_error(_('API method not found!'), $code = 404);
		}

		exit();
	}

	function sent($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}

	# had to change this from "new" to "create" to avoid PHP reserved word
	function create($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}

	function destroy($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
		exit();
	}

	function show_xml_dmsgs($message) {

		$this->init_document('xml');
		common_element_start('direct-messages', array('type' => 'array'));

		if (is_array($messages)) {
			foreach ($message as $m) {
				$twitter_dm = $this->twitter_dm_array($m);
				$this->show_twitter_xml_dm($twitter_dm);
			}
		} else {
			while ($message->fetch()) {
				$twitter_dm = $this->twitter_dm_array($message);
				$this->show_twitter_xml_dm($twitter_dm);
			}
		}

		common_element_end('direct-messages');
		$this->end_document('xml');
	}

	function show_json_dmsgs($message) {

		$this->init_document('json');

		$dmsgs = array();

		if (is_array($message)) {
			foreach ($message as $m) {
				$twitter_dm = $this->twitter_dm_array($m);
				array_push($dmsgs, $twitter_dm);
			}
		} else {
			while ($message->fetch()) {
				$twitter_dm = $this->twitter_dm_array($message);
				array_push($dmsgs, $twitter_dm);
			}
		}

		$this->show_twitter_json_dmsgs($dmsgs);

		$this->end_document('json');
	}

	function show_rss_dmsgs($message, $title, $link, $subtitle) {

		$this->init_document('rss');

		common_element_start('channel');
		common_element('title', NULL, $title);

		common_element('link', NULL, $link);
		common_element('description', NULL, $subtitle);
		common_element('language', NULL, 'en-us');
		common_element('ttl', NULL, '40');

		if (is_array($message)) {
			foreach ($message as $m) {
				$entry = $this->twitter_rss_dmsg_array($m);
				$this->show_twitter_rss_item($entry);
			}
		} else {
			while ($message->fetch()) {
				$entry = $this->twitter_rss_dmsg_array($message);
				$this->show_twitter_rss_item($entry);
			}
		}

		common_element_end('channel');
		$this->end_twitter_rss();
	}

	function show_atom_dmsgs($message, $title, $link, $subtitle) {

		$this->init_document('atom');

		common_element('title', NULL, $title);
		$siteserver = common_config('site', 'server');
		common_element('id', NULL, "tag:$siteserver,2008:DirectMessage");
		common_element('link', array('href' => $link, 'rel' => 'alternate', 'type' => 'text/html'), NULL);
		common_element('updated', NULL, common_date_iso8601(strftime('%c')));
		common_element('subtitle', NULL, $subtitle);

		if (is_array($message)) {
			foreach ($message as $m) {
				$entry = $this->twitter_rss_dmsg_array($m);
				$this->show_twitter_atom_entry($entry);
			}
		} else {
			while ($message->fetch()) {
				$entry = $this->twitter_rss_dmsg_array($message);
				$this->show_twitter_atom_entry($entry);
			}
		}

		$this->end_document('atom');

	}

}