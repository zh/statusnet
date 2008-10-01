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

/* XXX: Please don't freak out about all the ugly comments in this file.
 * They are mostly in here for reference while I work on the
 * API. I'll fix things up later to make them look better later. -- Zach
 */
class TwitapistatusesAction extends TwitterapiAction {

	function is_readonly() {

		static $write_methods = array(	'update',
										'destroy');

		$cmdtext = explode('.', $this->arg('method'));

		if (in_array($cmdtext[0], $write_methods)) {
			return false;
		}

		return true;
	}

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

		exit();
	}

	/*
	Returns the 20 most recent statuses posted by the authenticating user and that user's friends.
	This is the equivalent of /home on the Web.

	URL: http://server/api/statuses/friends_timeline.format

	Parameters:

	    * since.  Optional.  Narrows the returned results to just those statuses created after the specified
			HTTP-formatted date.  The same behavior is available by setting an If-Modified-Since header in
			your HTTP request.
			Ex: http://server/api/statuses/friends_timeline.rss?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
	    * since_id.  Optional.  Returns only statuses with an ID greater than (that is, more recent than)
			the specified ID.  Ex: http://server/api/statuses/friends_timeline.xml?since_id=12345
	    * count.  Optional.  Specifies the number of statuses to retrieve. May not be greater than 200.
	  		Ex: http://server/api/statuses/friends_timeline.xml?count=5
	    * page. Optional. Ex: http://server/api/statuses/friends_timeline.rss?page=3

	Formats: xml, json, rss, atom
	*/
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

		// NOTE: before_id is an extensions to Twitter API -- TB
        if (!$before_id) {
            $before_id = 0;
        }

		$user = $this->get_user($id, $apidata);
		$profile = $user->getProfile();

		$sitename = common_config('site', 'name');
		$siteserver = common_config('site', 'server');

		$title = sprintf(_("%s and friends"), $user->nickname);
		$id = "tag:$siteserver:friends:".$user->id;
		$link = common_local_url('all', array('nickname' => $user->nickname));
		$subtitle = sprintf(_('Updates from %1$s and friends on %2$s!'), $user->nickname, $sitename);

		$notice = $user->noticesWithFriends(($page-1)*20, $count, $since_id, $before_id);

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_timeline($notice);
			break;
		 case 'rss':
			$this->show_rss_timeline($notice, $title, $id, $link, $subtitle);
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

		exit();
	}

	/*
		Returns the 20 most recent statuses posted from the authenticating user. It's also possible to
        request another user's timeline via the id parameter below. This is the equivalent of the Web
        /archive page for your own user, or the profile page for a third party.

		URL: http://server/api/statuses/user_timeline.format

		Formats: xml, json, rss, atom

		Parameters:

		    * id. Optional. Specifies the ID or screen name of the user for whom to return the
            friends_timeline. Ex: http://server/api/statuses/user_timeline/12345.xml or
            http://server/api/statuses/user_timeline/bob.json.
			* count. Optional. Specifies the number of
            statuses to retrieve. May not be greater than 200. Ex:
            http://server/api/statuses/user_timeline.xml?count=5
			* since. Optional. Narrows the returned
            results to just those statuses created after the specified HTTP-formatted date. The same
            behavior is available by setting an If-Modified-Since header in your HTTP request. Ex:
            http://server/api/statuses/user_timeline.rss?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
			* since_id. Optional. Returns only statuses with an ID greater than (that is, more recent than)
            the specified ID. Ex: http://server/api/statuses/user_timeline.xml?since_id=12345 * page.
            Optional. Ex: http://server/api/statuses/friends_timeline.rss?page=3
	*/
	function user_timeline($args, $apidata) {
		parent::handle($args);

		$user = null;

		// function was called with an argument /statuses/user_timeline/api_arg.format
		if (isset($apidata['api_arg'])) {

			if (is_numeric($apidata['api_arg'])) {
				$user = User::staticGet($apidata['api_arg']);
			} else {
				$nickname = common_canonical_nickname($apidata['api_arg']);
				$user = User::staticGet('nickname', $nickname);
			}
		} else {

			// if no user was specified, then we'll use the authenticated user
			$user = $apidata['user'];
		}

		if (!$user) {
			// Set the user to be the auth user if asked-for can't be found
			// honestly! This is what Twitter does, I swear --Zach
			$user = $apidata['user'];
		}

		$profile = $user->getProfile();

		if (!$profile) {
			common_server_error(_('User has no profile.'));
			exit();
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

		# XXX: since

		$notice = $user->getNotices((($page-1)*20), $count, $since_id, $before_id);

		switch($apidata['content-type']) {
		 case 'xml':
			$this->show_xml_timeline($notice);
			break;
		 case 'rss':
			$this->show_rss_timeline($notice, $title, $id, $link, $subtitle);
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

		exit();
	}

	function update($args, $apidata) {

		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			exit;
		}

		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			$this->client_error(_('This method requires a POST.'), 400, $apidata['content-type']);
			exit();
		}

		$user = $apidata['user'];
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
			exit();

		} else if (mb_strlen($status) > 140) {

			// XXX: Twitter truncates anything over 140, flags the status
		    // as "truncated."  Sending this error may screw up some clients
		    // that assume Twitter will truncate for them.  Should we just
		    // truncate too? -- Zach
			$this->client_error(_('That\'s too long. Max notice size is 140 chars.'), $code = 406, $apidata['content-type']);
			exit();
		}

		$reply_to = NULL;

		if ($in_reply_to_status_id) {

			// check whether notice actually exists
			$reply = Notice::staticGet($in_reply_to_status_id);

			if ($reply) {
				$reply_to = $in_reply_to_status_id;
			} else {
				$this->client_error(_('Not found'), $code = 404, $apidata['content-type']);
				exit();
			}
		}

		$notice = Notice::saveNew($user->id, $status, $source, 1, $reply_to);

		if (is_string($notice)) {
			$this->server_error($notice);
			exit();
		}

		common_broadcast_notice($notice);

		// FIXME: Bad Hack
		// I should be able to just sent this notice off for display,
		// but $notice->created does not contain a string at this
		// point and I don't know how to convert it to one here. So
		// I'm forced to have DBObject pull the notice back out of the
		// DB before printing. --Zach
		$apidata['api_arg'] = $notice->id;
		$this->show($args, $apidata);

		exit();
	}

	/*
		Returns the 20 most recent @replies (status updates prefixed with @username) for the authenticating user.
		URL: http://server/api/statuses/replies.format

		Formats: xml, json, rss, atom

 		Parameters:

 		* page. Optional. Retrieves the 20 next most recent replies. Ex: http://server/api/statuses/replies.xml?page=3
		* since. Optional. Narrows the returned results to just those replies created after the specified HTTP-formatted date. The
        same behavior is available by setting an If-Modified-Since header in your HTTP request. Ex:
        http://server/api/statuses/replies.xml?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
		* since_id. Optional. Returns only statuses with an ID greater than (that is, more recent than) the specified
		ID. Ex: http://server/api/statuses/replies.xml?since_id=12345
	*/
	function replies($args, $apidata) {

		parent::handle($args);

		$since = $this->arg('since');

		$count = $this->arg('count');
		$page = $this->arg('page');
    	$since_id = $this->arg('since_id');
    	$before_id = $this->arg('before_id');

		$user = $apidata['user'];
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

		// NOTE: before_id is an extensions to Twitter API -- TB
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
			$this->show_rss_timeline($notices, $title, $id, $link, $subtitle);
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

		exit();
	}

	function show($args, $apidata) {
		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			exit;
		}

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

		exit();
	}


	/*
		Destroys the status specified by the required ID parameter. The authenticating user must be
        the author of the specified status.

		 URL: http://server/api/statuses/destroy/id.format

		 Formats: xml, json

		 Parameters:

		 * id. Required. The ID of the status to destroy. Ex:
        	http://server/api/statuses/destroy/12345.json or
        	http://server/api/statuses/destroy/23456.xml

	*/
	function destroy($args, $apidata) {

		parent::handle($args);

		if (!in_array($apidata['content-type'], array('xml', 'json'))) {
			common_user_error(_('API method not found!'), $code = 404);
			exit;
		}

		// Check for RESTfulness
		if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
			// XXX: Twitter just prints the err msg, no XML / JSON.
			$this->client_error(_('This method requires a POST or DELETE.'), 400, $apidata['content-type']);
			exit();
		}

		$user = $apidata['user'];
		$notice_id = $apidata['api_arg'];
		$notice = Notice::staticGet($notice_id);

		if (!$notice) {
			$this->client_error(_('No status found with that ID.'), 404, $apidata['content-type']);
			exit();
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

		exit();
	}

	# User Methods

	/*
		Returns up to 100 of the authenticating user's friends who have most recently updated, each with current status inline.
        It's also possible to request another user's recent friends list via the id parameter below.

		 URL: http://server/api/statuses/friends.format

		 Formats: xml, json

		 Parameters:

		 * id. Optional. The ID or screen name of the user for whom to request a list of friends. Ex:
        	http://server/api/statuses/friends/12345.json
			or
			http://server/api/statuses/friends/bob.xml
		 * page. Optional. Retrieves the next 100 friends. Ex: http://server/api/statuses/friends.xml?page=2
		 * lite. Optional. Prevents the inline inclusion of current status. Must be set to a value of true. Ex:
        	http://server/api/statuses/friends.xml?lite=true
		 * since. Optional. Narrows the returned results to just those friendships created after the specified
  			HTTP-formatted date. The same behavior is available by setting an If-Modified-Since header in your HTTP
  			request. Ex: http://server/api/statuses/friends.xml?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
	*/
	function friends($args, $apidata) {
		parent::handle($args);
		return $this->subscriptions($apidata, 'subscribed', 'subscriber');
	}

	/*
		Returns the authenticating user's followers, each with current status inline. They are ordered by the
		order in which they joined Twitter (this is going to be changed).

		URL: http://server/api/statuses/followers.format
		Formats: xml, json

		Parameters:

		    * id. Optional. The ID or screen name of the user for whom to request a list of followers. Ex:
            	http://server/api/statuses/followers/12345.json
				or
				http://server/api/statuses/followers/bob.xml
		    * page. Optional. Retrieves the next 100 followers. Ex: http://server/api/statuses/followers.xml?page=2
		    * lite. Optional. Prevents the inline inclusion of current status. Must be set to a value of true.
		 		Ex: http://server/api/statuses/followers.xml?lite=true
	*/
	function followers($args, $apidata) {
		parent::handle($args);

		return $this->subscriptions($apidata, 'subscriber', 'subscribed');
	}

	function subscriptions($apidata, $other_attr, $user_attr) {

		$user = $this->get_subs_user($apidata);

		# XXX: id
		# XXX: lite

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
		exit();
	}

	function get_subs_user($apidata) {

		// function was called with an argument /statuses/user_timeline/api_arg.format
		if (isset($apidata['api_arg'])) {

			if (is_numeric($apidata['api_arg'])) {
				$user = User::staticGet($apidata['api_arg']);
			} else {
				$nickname = common_canonical_nickname($apidata['api_arg']);
				$user = User::staticGet('nickname', $nickname);
			}
		} else {

			// if no user was specified, then we'll use the authenticated user
			$user = $apidata['user'];
		}

		if (!$user) {
			// Set the user to be the auth user if asked-for can't be found
			// honestly! This is what Twitter does, I swear --Zach
			$user = $apidata['user'];
		}

		return $user;
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
			exit();
		}
	}

	/*
	Returns a list of the users currently featured on the site with their current statuses inline.
	URL: http://server/api/statuses/featured.format

	Formats: xml, json
	*/
	function featured($args, $apidata) {
		parent::handle($args);
		common_server_error(_('API method under construction.'), $code=501);
	}

	function get_user($id, $apidata) {
		if (!$id) {
			return $apidata['user'];
		} else if (is_numeric($id)) {
			return User::staticGet($id);
		} else {
			return User::staticGet('nickname', $id);
		}
	}
}

