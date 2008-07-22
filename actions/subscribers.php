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

require_once(INSTALLDIR.'/lib/gallery.php');

class SubscribersAction extends GalleryAction {

	function gallery_type() {
		return _('Subscribers');
	}

	function get_instructions(&$profile) {
		$user =& common_current_user();
		if ($user && ($user->id == $profile->id)) {
			return _('These are the people who listen to your notices.');
		} else {
			return sprintf(_('These are the people who listen to %s\'s notices.'), $profile->nickname);
		}
	}

	function define_subs(&$subs, &$profile) {
		$subs->subscribed = $profile->id;
		$subs->whereAdd('subscriber != ' . $profile->id);
	}

	function div_class() {
		return 'subscribers';
	}

	function get_other(&$subs) {
		return $subs->subscriber;
	}
}