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

require_once(INSTALLDIR.'/actions/showstream.php');
define('TAGS_PER_PAGE', 100);
define('AGE_FACTOR', 864000.0);

class TagAction extends StreamAction {

	function handle($args) {

		parent::handle($args);

		# Looks like we're good; show the header

		if (isset($args['tag']) && $args['tag']) {
			$tag = $args['tag'];
			common_show_header(sprintf(_("Notices tagged with %s"), $tag),
							   array($this, 'show_header'), $tag,
							   array($this, 'show_top'));

			$this->show_notices($tag);
		} else {
			common_show_header(_("Tags"),
							   array($this, 'show_header'), '',
							   array($this, 'show_top'));
			$this->show_tags();
		}

		common_show_footer();
	}

	function show_header($tag = false) {
		if (false && $tag) {
			common_element('link', array('rel' => 'alternate',
										 'href' => common_local_url('tagrss', array('tag' => $tag)),
										 'type' => 'application/rss+xml',
										 'title' => sprintf(_('Feed for tag %s'), $tag)));
		}
	}

	function get_instructions() {
		return _('Showing most popular tags from the last week');
	}

	function show_top($tag = false) {
		if (!$tag) {
			$instr = $this->get_instructions();
			$output = common_markup_to_html($instr);
			common_element_start('div', 'instructions');
			common_raw($output);
			common_element_end('div');
		}

		common_element_start('ul', array('id' => 'nav_views'));

		common_menu_item(common_local_url('tags'),
						 _('Recent Tags'),
						 _('Recent Tags'),
						 !$tag);
		if ($tag) {
			common_menu_item(common_local_url('tag', array('tag' => $tag)),
							 '#' . $tag,
							 sprintf(_("Notices tagged with %s"), $tag),
							 true);
		}
		common_element_end('ul');
	}

	function show_tags()
	{
		# This should probably be cached rather than recalculated
		$tags = DB_DataObject::factory('Notice_tag');
		$tags->selectAdd('max(notice_id) as last_notice_id');
		$tags->selectAdd(sprintf('sum(exp(-(now() - created)/%f)) as weight', AGE_FACTOR));
		$tags->groupBy('tag');
		$tags->orderBy('weight DESC');

		# $tags->whereAdd('created > "' . strftime('%Y-%m-%d %H:%M:%S', strtotime('-1 MONTH')) . '"');

		$tags->limit(TAGS_PER_PAGE);

		$cnt = $tags->find();

		if ($cnt > 0) {
			common_element_start('p', 'tagcloud');
			
			$tw = array();
			$sum = 0;
			while ($tags->fetch()) {
				$tw[$tags->tag] = $tags->weight;
				$sum += $tags->weight;
			}
			
			foreach ($tw as $tag => $weight) {
				$this->show_tag($tag, $weight/$sum);
			}
			
			common_element_end('p');
		}
	}

	function show_tag($tag, $relative) {
		
		# XXX: these should probably tune to the size of the site
		$cls = ($relative > 0.1) ? 'largest' :
		($relative > 0.05) ? 'verylarge' :
		($relative > 0.02) ? 'large' :
		($relative > 0.01) ? 'medium' :
		($relative > 0.005) ? 'small' :
		($relative > 0.002) ? 'verysmall' :
		'smallest';
		
		common_element('a', array('class' => $cls,
								  'href' => common_local_url('tag', array('tag' => $tag))),
					   $tag);
		common_text(' ');
	}
	
	function show_notices($tag) {

		$tags = DB_DataObject::factory('Notice_tag');

		$tags->tag = $tag;

		$tags->orderBy('created DESC');

		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		$tags->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

		$cnt = $tags->find();

		if ($cnt > 0) {
			common_element_start('ul', array('id' => 'notices'));
			for ($i = 0; $i < min($cnt, NOTICES_PER_PAGE); $i++) {
				if ($tags->fetch()) {
					$notice = new Notice();
					$notice->id = $tags->notice_id;
					$result = $notice->find(true);
					if (!$result) {
						continue;
					}
					$this->show_notice($notice);
				} else {
					// shouldn't happen!
					break;
				}
			}
			common_element_end('ul');
		}

		common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
						  $page, 'tag', array('tag' => $tag));
	}
}
