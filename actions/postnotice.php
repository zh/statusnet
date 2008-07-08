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

require_once(INSTALLDIR.'/lib/omb.php');

class PostnoticeAction extends Action {
	function handle($args) {
		parent::handle($args);
		try {
			$req = OAuthRequest::from_request();
			# Note: server-to-server function!
			$server = omb_oauth_server();
			list($consumer, $token) = $server->verify_request($req);
			if ($this->save_notice($req, $consumer, $token)) {
				print "omb_version=".OMB_VERSION_01;
			}
		} catch (OAuthException $e) {
			common_server_error($e->getMessage());
			return;
		}
	}

	function save_notice(&$req, &$consumer, &$token) {
		$version = $req->get_parameter('omb_version');
		if ($version != OMB_VERSION_01) {
			common_user_error(_('Unsupported OMB version'), 400);
			return false;
		}
		# First, check to see
		$listenee =  $req->get_parameter('omb_listenee');
		$remote_profile = Remote_profile::staticGet('uri', $listenee);
		if (!$remote_profile) {
			common_user_error(_('Profile unknown'), 403);
			return false;
		}
		$sub = Subscription::staticGet('token', $token->key);
		if (!$sub) {
			common_user_error(_('No such subscription'), 403);
			return false;
		}
		$content = $req->get_parameter('omb_notice_content');
		if (!$content || strlen($content) > 140) {
			common_user_error(_('Invalid notice content'), 400);
			return false;
		}
		$notice_uri = $req->get_parameter('omb_notice');
		if (!Validate::uri($notice_uri) &&
			!common_valid_tag($notice_uri)) {
			common_user_error(_('Invalid notice uri'), 400);
			return false;
		}
		$notice_url = $req->get_parameter('omb_notice_url');
		if ($notice_url && !common_valid_http_url($notice_url)) {
			common_user_error(_('Invalid notice url'), 400);
			return false;
		}
		$notice = Notice::staticGet('uri', $notice_uri);
		if (!$notice) {
			$notice = new Notice();
			$notice->profile_id = $remote_profile->id;
			$notice->uri = $notice_uri;
			$notice->content = $content;
			$notice->rendered = common_render_content($notice->content, $notice);
			if ($notice_url) {
				$notice->url = $notice_url;
			}
			$notice->created = DB_DataObject_Cast::dateTime(); # current time
			$id = $notice->insert();
			if (!$id) {
				common_server_error(_('Error inserting notice'), 500);
				return false;
			}
			common_save_replies($notice);	
			common_broadcast_notice($notice, true);
		}
		return true;
	}
}
