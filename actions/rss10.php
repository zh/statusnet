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

class Rss10Action extends Action {

	function handle($args) {
		parent::handle($args);
		
		$nickname = $this->trimmed('nickname');
		
		if (!$nickname) {
			common_user_error(_t('No nickname provided.'));
		}
		
		$user = User::staticGet('nickname', $nickname);
		
		if (!$user) {
			common_user_error(_t('No such nickname.'));
		}
		
		$limit = (int) $this->trimmed('limit');
		
		$this->show_rss($user, $limit);
	}
	
	function show_rss($user, $limit=0) {
		
		global $config;
		
		header('Content-Type: application/rdf+xml');

		common_start_xml();
		common_element_start('rdf:RDF', array('xmlns:rdf' =>
											  'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
											  'xmlns' => 'http://purl.org/rss/1.0/'));

		$notices = $this->get_notices($user, $limit);
		$this->show_channel($user, $notices);
		
		foreach ($notices as $n) {
			$this->show_item($n);
		}
		
		common_element_end('rdf:RDF');
	}
	
	function get_notices($user, $limit=0) {
		$notices = array();
		
		$notice = DB_DataObject::factory('notice');
		$notice->profile_id = $user->id; # user id === profile id
		$notice->orderBy('created DESC');
		if ($limit != 0) {
			$notice->limit(0, $limit);
		}
		$notice->find();
		
		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}
		
		return $notices;
	}
	
	function show_channel($user, $notices) {
		
		# XXX: this is kind of indirect, eh?
		$profile = $user->getProfile();
		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);
		
		common_element_start('channel', array('rdf:about' =>
											  common_local_url('rss10',
															   array('nickname' => 
																	 $user->nickname))));
		common_element('title', NULL, $user->nickname);
		common_element('link', NULL, $profile->profileurl);
		common_element('description', NULL, _t('Microblog by ') . $user->nickname);

		if ($avatar) {
			common_element('image', array('rdf:resource' => $avatar->url));
		}

		common_element_start('items');
		common_element_start('rdf:Seq');
		foreach ($notices as $n) {
			common_element('rdf:li', array('rdf:resource' =>
										   common_local_url('shownotice',
															array('notice' => $n->id))));
		}
		
		common_element_end('rdf:Seq');
		common_element_end('items');
		common_element_end('channel');
		
		if ($avatar) {
			common_element_start('image', array('rdf:about' => $avatar->url));
			common_element('title', NULL, $user->nickname);
			common_element('link', NULL, $profile->profileurl);
			common_element('url', NULL, $avatar->url);
			common_element_end('image');
		}
	}
	
	function show_item($notice) {
		$nurl = common_local_url('shownotice', array('notice' => $n->id));
		common_element_start('item', array('rdf:about' => $nurl));
		common_element('title', NULL, $notice->created);
		common_element('link', NULL, $nurl);
		common_element('description', NULL, common_render_content($notice->content));
		common_element_end('item');
	}
}