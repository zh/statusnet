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
		if ($tag) {
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
			$this->public_views_menu();
		}
		else {
			$this->show_feeds_list(array(0=>array('href'=>common_local_url('tagrss'),
												  'type' => 'rss',
												  'version' => 'RSS 1.0',
												  'item' => 'tagrss')));
		}
	}

	function show_tags()
	{
		# This should probably be cached rather than recalculated
		$tags = DB_DataObject::factory('Notice_tag');

		#Need to clear the selection and then only re-add the field
		#we are grouping by, otherwise it's not a valid 'group by'
		#even though MySQL seems to let it slide...
		$tags->selectAdd();
		$tags->selectAdd('tag');

		#Add the aggregated columns...
		$tags->selectAdd('max(notice_id) as last_notice_id');
		if(common_config('db','type')=='pgsql') {
			$calc='sum(exp(-extract(epoch from (now()-created))/%s)) as weight';
		} else {
			$calc='sum(exp(-(now() - created)/%s)) as weight';
		}
		$tags->selectAdd(sprintf($calc, common_config('tag', 'dropoff')));
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

			ksort($tw);
			
			foreach ($tw as $tag => $weight) {
				$this->show_tag($tag, $weight, $weight/$sum);
			}

			common_element_end('p');
		}
	}

	function show_tag($tag, $weight, $relative) {

		# XXX: these should probably tune to the size of the site
		if ($relative > 0.1) {
			$cls =  'largest';
		} else if ($relative > 0.05) {
			$cls = 'verylarge';
		} else if ($relative > 0.02) {
			$cls = 'large';
		} else if ($relative > 0.01) {
			$cls = 'medium';
		} else if ($relative > 0.005) {
			$cls = 'small';
		} else if ($relative > 0.002) {
			$cls = 'verysmall';
		} else {
			$cls = 'smallest';
		}

		common_element('a', array('class' => "$cls weight-$weight relative-$relative",
								  'href' => common_local_url('tag', array('tag' => $tag))),
					   $tag);
		common_text(' ');
	}

	function show_notices($tag) {

		$cnt = 0;
		
		$page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

		$notice = Notice_tag::getStream($tag, (($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);

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
						  $page, 'tag', array('tag' => $tag));
	}
}
