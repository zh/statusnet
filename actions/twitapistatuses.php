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

class TwitapistatusesAction extends TwitterapiAction {

	function public_timeline($args, $apidata) {
		parent::handle($args);

		$sitename = common_config('site', 'name');
		$siteserver = common_config('site', 'server');
		$title = sprintf(_("%s public timeline"), $sitename);
		$id = "tag:$siteserver:Statuses";
		$link = common_root_url();
		$subtitle = sprintf(_("%s updates from everyone!"), $sitename);

		// Number of public statuses to return by default -- Twitter sends 20
		$MAX_PUBSTATUSES = 20;

		// FIXME: To really live up to the spec we need to build a list
		// of notices by users who have custom avatars, so fix this SQL -- Zach

    	$page = $this->arg('page');
    	$since_id = $this->arg('since_id');
    	$before_id = $this->arg('before_id');

		// NOTE: page, since_id, and before_id are extensions to Twitter API -- TB
        if (!$page) {
            $page = 1;
        }
        if (!$since_id) {
            $since_id = 0;
        }
        if (!$before_id) {
            $before_id = 0;
        }

		$notice = Notice::publicStream((($page-1)*$MAX_PUBSTATUSES), $MAX_PUBSTATUSES, $since_id, $before_id);

		if ($notice) {

			switch($apidata['content-type']) {
				case 'xml':
					$this->show_xml_timeline($notice);
					break;
				case 'rss':
					$this->show_rss_timeline($notice, $title, $link, $subtitle);
					break;
				case 'atom':
					$this->show_atom_timeline($notice, $title, $id, $link, $subtitle);
					break;
				case 'json':
					$this->show_json_timeline($notice);
					break;
				default:
					common_user_error(_('API method not found!'), $code = 404);
					break;
			}

		} else {
			common_server_error(_('Couldn\'t find any statuses.'), $code = 503);
		}

	}

	function friends_timeline($args, $apidata) {
		parent::handle($args);

		$since = $this->arg('since');
		$since_id = $this->arg('since_id');
		$count = $this->arg('count');
    	$page = $this->arg('page');
    	$before_id = $this->arg('before_id');

        if (!$page) {
            $page = 1;
        }

		if (!$count) {
			$count = 20;
		}

        if (!$since_id) {
            $since_id = 0;
        }

		// NOTE: before_id is an extension to Twitter API -- TB
        if (!$before_id) {
            $before_id = 0;
        }

		$user = $this->get_user($id, $apidata);
		$this->auth_user = $user;

		$profile = $user->getProfile();

		$sitename = common_config('site', 'name');
		$siteserver = common_config('site', 'server');

		$title = sprintf(_("%s and friends"), $user->nickname);
		$id = "tag:$siteserver:friends:" . $user->id;
		$link = common_local_url('all', array('nickname' => $user->nickname));
		$subtitle = sprintf(_('Updates from %1$s and friends on %2$s!'), $user->nickname, $sitename);

		$notice = $user->noticesWithFriends(($page-1)*20, $count, $since_id, $before_id);

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_timeline($notice);
			break;
		 case 'rss':
			$this->show_rss_timeline($notice, $title, $link, $subtitle);
			break;
		 case 'atom':
			$this->show_atom_timeline($notice, $title, $id, $link, $subtitle);
			break;
		 case 'json':
			$this->show_json_timeline($notice);
			break;
		 default:
			common_user_error(_('API method not found!'), $code = 404);
		}

	}

	function user_timeline($args, $apidata) {
		parent::handle($args);

		$this->auth_user = $apidata['user'];
		$user = $this->get_user($apidata['api_arg'], $apidata);

		if (!$user) {
			$this->client_error('Not Found', 404, $apidata['content-type']);
			return;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile.'));
			return;
		}

		$count = $this->arg('count');
		$since = $this->arg('since');
    	$since_id = $this->arg('since_id');
		$page = $this->arg('page');
    	$before_id = $this->arg('before_id');

		if (!$page) {
			$page = 1;
		}

		if (!$count) {
			$count = 20;
		}

        if (!$since_id) {
            $since_id = 0;
        }

		// NOTE: before_id is an extensions to Twitter API -- TB
        if (!$before_id) {
            $before_id = 0;
        }

		$sitename = common_config('site', 'name');
		$siteserver = common_config('site', 'server');

		$title = sprintf(_("%s timeline"), $user->nickname);
		$id = "tag:$siteserver:user:".$user->id;
		$link = common_local_url('showstream', array('nickname' => $user->nickname));
		$subtitle = sprintf(_('Updates from %1$s on %2$s!'), $user->nickname, $sitename);

		# FriendFeed's SUP protocol
		# Also added RSS and Atom feeds

		$suplink = common_local_url('sup', NULL, $user->id);
		header('X-SUP-ID: '.$suplink);

		# XXX: since

		$notice = $user->getNotices((($page-1)*20), $count, $since_id, $before_id);

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_timeline($notice);
			break;
		 case 'rss':
			$this->show_rss_timeline($notice, $title, $link, $subtitle, $suplink);
			break;
		 case 'atom':
			$this->show_atom_timeline($notice, $title, $id, $link, $subtitle, $suplink);
			break;
		 case 'json':
			$this->show_json_timeline($notice);
			break;
		 default:
			common_user_error(_('API method not found!'), $code = 404);
		}

	}

	function update($args, $apidata) {

		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			return;
		}

		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			$this->client_error(_('This method requires a POST.'), 400, $apidata['content-type']);
			return;
		}

		$this->auth_user = $apidata['user'];
		$user = $this->auth_user;
		$status = $this->trimmed('status');
		$source = $this->trimmed('source');
		$in_reply_to_status_id = intval($this->trimmed('in_reply_to_status_id'));

		if (!$source) {
			$source = 'api';
		}

		if (!$status) {

			// XXX: Note: In this case, Twitter simply returns '200 OK'
			// No error is given, but the status is not posted to the
			// user's timeline.  Seems bad.  Shouldn't we throw an
			// errror? -- Zach
			return;

		} else if (mb_strlen($status) > 140) {
			
			$status = common_shorten_links($status);

			if (mb_strlen($status) > 140) {

				// XXX: Twitter truncates anything over 140, flags the status
			    // as "truncated."  Sending this error may screw up some clients
			    // that assume Twitter will truncate for them.  Should we just
			    // truncate too? -- Zach
				$this->client_error(_('That\'s too long. Max notice size is 140 chars.'), $code = 406, $apidata['content-type']);
				return;
				
			}
		}

		// Check for commands
		$inter = new CommandInterpreter();
		$cmd = $inter->handle_command($user, $status);

		if ($cmd) {

			if ($this->supported($cmd)) {
				$cmd->execute(new Channel());
			}

			// cmd not supported?  Twitter just returns your latest status.
			// And, it returns your last status whether the cmd was successful
			// or not!
			$n = $user->getCurrentNotice();
			$apidata['api_arg'] = $n->id;
		} else {

			$reply_to = NULL;

			if ($in_reply_to_status_id) {

				// check whether notice actually exists
				$reply = Notice::staticGet($in_reply_to_status_id);

				if ($reply) {
					$reply_to = $in_reply_to_status_id;
				} else {
					$this->client_error(_('Not found'), $code = 404, $apidata['content-type']);
					return;
				}
			}

			$notice = Notice::saveNew($user->id, html_entity_decode($status, ENT_NOQUOTES, 'UTF-8'),
				$source, 1, $reply_to);

			if (is_string($notice)) {
				$this->server_error($notice);
				return;
			}

			common_broadcast_notice($notice);
			$apidata['api_arg'] = $notice->id;
		}

		$this->show($args, $apidata);
	}

	function replies($args, $apidata) {

		parent::handle($args);

		$since = $this->arg('since');
		$count = $this->arg('count');
		$page = $this->arg('page');
    	$since_id = $this->arg('since_id');
    	$before_id = $this->arg('before_id');

		$this->auth_user = $apidata['user'];
		$user = $this->auth_user;
		$profile = $user->getProfile();

		$sitename = common_config('site', 'name');
		$siteserver = common_config('site', 'server');

		$title = sprintf(_('%1$s / Updates replying to %2$s'), $sitename, $user->nickname);
		$id = "tag:$siteserver:replies:".$user->id;
		$link = common_local_url('replies', array('nickname' => $user->nickname));
		$subtitle = sprintf(_('%1$s updates that reply to updates from %2$s / %3$s.'), $sitename, $user->nickname, $profile->getBestName());

		if (!$page) {
			$page = 1;
		}

		if (!$count) {
			$count = 20;
		}

        if (!$since_id) {
            $since_id = 0;
        }

		// NOTE: before_id is an extension to Twitter API -- TB
        if (!$before_id) {
            $before_id = 0;
        }
		$notice = $user->getReplies((($page-1)*20), $count, $since_id, $before_id);
		$notices = array();

		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_timeline($notices);
			break;
		 case 'rss':
			$this->show_rss_timeline($notices, $title, $link, $subtitle);
			break;
		 case 'atom':
			$this->show_atom_timeline($notices, $title, $id, $link, $subtitle);
			break;
		 case 'json':
			$this->show_json_timeline($notices);
			break;
		 default:
			common_user_error(_('API method not found!'), $code = 404);
		}

	}

	function show($args, $apidata) {
		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			return;
		}

		$this->auth_user = $apidata['user'];
		$notice_id = $apidata['api_arg'];
		$notice = Notice::staticGet($notice_id);

		if ($notice) {
			if ($apidata['content-type'] == 'xml') {
				$this->show_single_xml_status($notice);
			} elseif ($apidata['content-type'] == 'json') {
				$this->show_single_json_status($notice);
			}
		} else {
			// XXX: Twitter just sets a 404 header and doens't bother to return an err msg
			$this->client_error(_('No status with that ID found.'), 404, $apidata['content-type']);
		}

	}

	function destroy($args, $apidata) {

		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			return;
		}

		// Check for RESTfulness
		if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
			// XXX: Twitter just prints the err msg, no XML / JSON.
			$this->client_error(_('This method requires a POST or DELETE.'), 400, $apidata['content-type']);
			return;
		}

		$this->auth_user = $apidata['user'];
		$user = $this->auth_user;
		$notice_id = $apidata['api_arg'];
		$notice = Notice::staticGet($notice_id);

		if (!$notice) {
			$this->client_error(_('No status found with that ID.'), 404, $apidata['content-type']);
			return;
		}

		if ($user->id == $notice->profile_id) {
			$replies = new Reply;
			$replies->get('notice_id', $notice_id);
			common_dequeue_notice($notice);
			$replies->delete();
			$notice->delete();

			if ($apidata['content-type'] == 'xml') {
				$this->show_single_xml_status($notice);
			} elseif ($apidata['content-type'] == 'json') {
				$this->show_single_json_status($notice);
			}
		} else {
			$this->client_error(_('You may not delete another user\'s status.'), 403, $apidata['content-type']);
		}

	}

	function friends($args, $apidata) {
		parent::handle($args);
		return $this->subscriptions($apidata, 'subscribed', 'subscriber');
	}

	function followers($args, $apidata) {
		parent::handle($args);

		return $this->subscriptions($apidata, 'subscriber', 'subscribed');
	}

	function subscriptions($apidata, $other_attr, $user_attr) {

		# XXX: lite

		$this->auth_user = $apidate['user'];
		$user = $this->get_user($apidata['api_arg'], $apidata);

		if (!$user) {
			$this->client_error('Not Found', 404, $apidata['content-type']);
			return;
		}

		$page = $this->trimmed('page');

		if (!$page || !is_numeric($page)) {
			$page = 1;
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile.'));
			return;
		}

		$sub = new Subscription();
		$sub->$user_attr = $profile->id;
		$sub->orderBy('created DESC');
		$sub->limit(($page-1)*100, 100);

		$others = array();

		if ($sub->find()) {
			while ($sub->fetch()) {
				$others[] = Profile::staticGet($sub->$other_attr);
			}
		} else {
			// user has no followers
		}

		$type = $apidata['content-type'];

		$this->init_document($type);
		$this->show_profiles($others, $type);
		$this->end_document($type);
	}

	function show_profiles($profiles, $type) {
		switch ($type) {
		 case 'xml':
			common_element_start('users', array('type' => 'array'));
			foreach ($profiles as $profile) {
				$this->show_profile($profile);
			}
			common_element_end('users');
			break;
		 case 'json':
			$arrays = array();
			foreach ($profiles as $profile) {
				$arrays[] = $this->twitter_user_array($profile, true);
			}
			print json_encode($arrays);
			break;
		 default:
			$this->client_error(_('unsupported file type'));
		}
	}

	function featured($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
	}

	function supported($cmd) {

		$cmdlist = array('MessageCommand', 'SubCommand', 'UnsubCommand', 'FavCommand', 'OnCommand', 'OffCommand');

		if (in_array(get_class($cmd), $cmdlist)) {
			return true;
		}

		return false;
	}

}
