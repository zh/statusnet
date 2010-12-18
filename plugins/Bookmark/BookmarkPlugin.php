<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to enable social-bookmarking functionality
 *
 * PHP version 5
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
 *
 * @category  SocialBookmark
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
	exit(1);
}

/**
 * Bookmark plugin main class
 *
 * @category  Bookmark
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class BookmarkPlugin extends Plugin
{
	/**
	 * Database schema setup
	 *
	 * @see Schema
	 * @see ColumnDef
	 *
	 * @return boolean hook value; true means continue processing, false means stop.
	 */

	function onCheckSchema()
	{
		$schema = Schema::get();

		// For storing user-submitted flags on profiles

		$schema->ensureTable('notice_bookmark',
							 array(new ColumnDef('notice_id',
												 'integer',
												 null,
												 true,
												 'PRI')));

		return true;
	}

	/**
	 * Load related modules when needed
	 *
	 * @param string $cls Name of the class to be loaded
	 *
	 * @return boolean hook value; true means continue processing, false means stop.
	 */

	function onAutoload($cls)
	{
		$dir = dirname(__FILE__);

		switch ($cls)
		{
		case 'NewbookmarkAction':
			include_once $dir.'/newbookmark.php';
			return false;
		case 'Notice_bookmark':
			include_once $dir.'/'.$cls.'.php';
			return false;
		case 'BookmarkForm':
			include_once $dir.'/'.strtolower($cls).'.php';
			return false;
		default:
			return true;
		}
	}

	/**
	 * Map URLs to actions
	 *
	 * @param Net_URL_Mapper $m path-to-action mapper
	 *
	 * @return boolean hook value; true means continue processing, false means stop.
	 */

	function onRouterInitialized($m)
	{
		$m->connect('main/bookmark/new',
					array('action' => 'newbookmark'),
					array('id' => '[0-9]+'));

		return true;
	}

	function onPluginVersion(&$versions)
	{
		$versions[] = array('name' => 'Sample',
							'version' => STATUSNET_VERSION,
							'author' => 'Evan Prodromou',
							'homepage' => 'http://status.net/wiki/Plugin:Bookmark',
							'rawdescription' =>
							_m('Simple extension for supporting bookmarks.'));
		return true;
	}
}

