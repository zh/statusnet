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

define('DEFAULT_RSS_LIMIT', 48);

class Rss10Action extends Action {

	# This will contain the details of each feed item's author and be used to generate SIOC data.
	var $creators = array();

	function is_readonly() {
		return true;
	}
	
	function handle($args) {
		parent::handle($args);
		$limit = (int) $this->trimmed('limit');
		if ($limit == 0) {
			$limit = DEFAULT_RSS_LIMIT;
		}
		$this->show_rss($limit);
	}

	function init() {
		return true;
	}

	function get_notices() {
		return array();
	}

	function get_channel() {
		return array('url' => '',
					 'title' => '',
					 'link' => '',
					 'description' => '');
	}

	function get_image() {
		return NULL;
	}

	function show_rss($limit=0) {

		if (!$this->init()) {
			return;
		}

		$notices = $this->get_notices($limit);

		$this->init_rss();
		$this->show_channel($notices);
		$this->show_image();

		foreach ($notices as $n) {
			$this->show_item($n);
		}

		$this->show_creators();
		$this->end_rss();
	}

	function show_channel($notices) {

		$channel = $this->get_channel();
		$image = $this->get_image();

		common_element_start('channel', array('rdf:about' => $channel['url']));
		common_element('title', NULL, $channel['title']);
		common_element('link', NULL, $channel['link']);
		common_element('description', NULL, $channel['description']);
		common_element('cc:licence', array('rdf:resource' => common_config('license','url')));

		if ($image) {
			common_element('image', array('rdf:resource' => $image));
		}

		common_element_start('items');
		common_element_start('rdf:Seq');

		foreach ($notices as $notice) {
			common_element('sioct:MicroblogPost', array('rdf:resource' => $notice->uri));
		}

		common_element_end('rdf:Seq');
		common_element_end('items');

		common_element_end('channel');
	}

	function show_image() {
		$image = $this->get_image();
		if ($image) {
			$channel = $this->get_channel();
			common_element_start('image', array('rdf:about' => $image));
			common_element('title', NULL, $channel['title']);
			common_element('link', NULL, $channel['link']);
			common_element('url', NULL, $image);
			common_element_end('image');
		}
	}

	function show_item($notice) {
		$profile = Profile::staticGet($notice->profile_id);
		$nurl = common_local_url('shownotice', array('notice' => $notice->id));
		$creator_uri = common_profile_uri($profile);
		common_element_start('item', array('rdf:about' => $notice->uri));
		$title = $profile->nickname . ': ' . common_xml_safe_str($notice->content);
		common_element('title', NULL, $title);
		common_element('link', NULL, $nurl);
		common_element('description', NULL, $profile->nickname."'s status on ".common_exact_date($notice->created));
		common_element('dc:date', NULL, common_date_w3dtf($notice->created));
		common_element('dc:creator', NULL, ($profile->fullname) ? $profile->fullname : $profile->nickname);
		common_element('sioc:has_creator', array('rdf:resource' => $creator_uri));
		common_element('laconica:postIcon', array('rdf:resource' => common_profile_avatar_url($profile)));
		common_element('cc:licence', array('rdf:resource' => common_config('license', 'url')));
        common_element_start('content:items');
        common_element_start('rdf:Bag');
        common_element_start('rdf:li');
        common_element_start('content:item');
        common_element('content:format', array('rdf:resource' =>
                                               'http://www.w3.org/1999/xhtml'));
        common_text($notice->rendered);
        common_element_end('content:item');
        common_element_end('rdf:li');
        common_element_start('rdf:li');
        common_element_start('content:item');
        common_element('content:format', array('rdf:resource' =>
                                               'http://www.isi.edu/in-notes/iana/assignments/media-types/text/plain'));
        common_text(common_xml_safe_str($notice->content));
        common_element_end('content:item');
        common_element_end('rdf:li');
        common_element_end('rdf:Bag');
        common_element_end('content:items');
		common_element_end('item');
		$this->creators[$creator_uri] = $profile;
	}

	function show_creators() {
		foreach ($this->creators as $uri => $profile) {
			$id = $profile->id;
			$nickname = $profile->nickname;
			
			common_element_start('sioc:User', array('rdf:about' => $uri));
			common_element('foaf:nick', NULL, $nickname);                                                
			if ($profile->fullname) {
				common_element('foaf:name', NULL, $profile->fullname);
			}
			common_element('sioc:id', NULL, $id);
			$avatar = common_profile_avatar_url($profile);
			common_element('sioc:avatar', array('rdf:resource' => $avatar));
			common_element_end('sioc:User');
		}
	}
	
	function init_rss() {
		$channel = $this->get_channel();
		
		header('Content-Type: application/rdf+xml');

		common_start_xml();
		common_element_start('rdf:RDF', array('xmlns:rdf' =>
											  'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
											  'xmlns:dc' =>
											  'http://purl.org/dc/elements/1.1/',
											  'xmlns:cc' =>
											  'http://web.resource.org/cc/',
                                              'xmlns:content' =>
                                              'http://purl.org/rss/1.0/modules/content/',
											  'xmlns:foaf' =>
											  'http://xmlns.com/foaf/0.1/',
											  'xmlns:sioc' =>
											  'http://rdfs.org/sioc/ns#',
		                                      'xmlns:sioct' =>
		                                      'http://rdfs.org/sioc/types#',
		                                      'xmlns:laconica' =>
		                                      'http://laconi.ca/ont/',
											  'xmlns' => 'http://purl.org/rss/1.0/'));
		
		common_element_start('sioc:Site', array('rdf:about' => common_root_url()));
		common_element('sioc:name', NULL, common_config('site', 'name'));
		common_element_start('sioc:container_of');
		common_element('sioc:Container', array('rdf:about' =>
		                                       $channel['url']));
		common_element_end('sioc:container_of');
		common_element_end('sioc:Site');
	}

	function end_rss() {
		common_element_end('rdf:RDF');
	}
}
