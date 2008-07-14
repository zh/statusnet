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

/* XXX: Please don't freak out about all the ugly comments in this file.
 * They are mostly in here for reference while I develop the
 * API. I'll fix things up to make them look better later. -- Zach 
 */
class ApistatusesAction extends Action {
	
	/*
		Returns the 20 most recent statuses from non-protected users who have set a custom user icon. 
		Does not require authentication.
		
		URL: http://identi.ca/api/statuses/public_timeline.format

		Formats: xml, json, rss, atom
	*/
	function public_timeline($args, $apidata) {
		parent::handle($args);

		print "Public Timeline! requested content-type: " . $apidata['content-type'] . "\n";
		
		exit();
	}
	
	/*
	Returns the 20 most recent statuses posted by the authenticating user and that user's friends. 
	This is the equivalent of /home on the Web. 
	
	URL: http://identi.ca/api/statuses/friends_timeline.format
	
	Parameters:

	    * since.  Optional.  Narrows the returned results to just those statuses created after the specified 
			HTTP-formatted date.  The same behavior is available by setting an If-Modified-Since header in 
			your HTTP request.  
			Ex: http://identi.ca/api/statuses/friends_timeline.rss?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
	    * since_id.  Optional.  Returns only statuses with an ID greater than (that is, more recent than) 
			the specified ID.  Ex: http://identi.ca/api/statuses/friends_timeline.xml?since_id=12345
	    * count.  Optional.  Specifies the number of statuses to retrieve. May not be greater than 200.
	  		Ex: http://identi.ca/api/statuses/friends_timeline.xml?count=5 
	    * page. Optional. Ex: http://identi.ca/api/statuses/friends_timeline.rss?page=3
	
	Formats: xml, json, rss, atom
	*/
	function friends_timeline($args, $apidata) {
		parent::handle($args);

		$since = $this->arg('since');
		$since_id = $this->arg('since_id');
		$count = $this->arg('count');
		$page = $this->arg('page');

		print "Friends Timeline! requested content-type: " . $apidata['content-type'] . "\n";
		print "since: $since, since_id: $since_id, count: $count, page: $page\n";
		
		exit();
		
	}
	
	/*
		Returns the 20 most recent statuses posted from the authenticating user. It's also possible to
        request another user's timeline via the id parameter below. This is the equivalent of the Web
        /archive page for your own user, or the profile page for a third party.

		URL: http://identi.ca/api/statuses/user_timeline.format

		Formats: xml, json, rss, atom

		Parameters:

		    * id. Optional. Specifies the ID or screen name of the user for whom to return the
            friends_timeline. Ex: http://identi.ca/api/statuses/user_timeline/12345.xml or
            http://identi.ca/api/statuses/user_timeline/bob.json. 
			* count. Optional. Specifies the number of
            statuses to retrieve. May not be greater than 200. Ex:
            http://identi.ca/api/statuses/user_timeline.xml?count=5 
			* since. Optional. Narrows the returned
            results to just those statuses created after the specified HTTP-formatted date. The same
            behavior is available by setting an If-Modified-Since header in your HTTP request. Ex:
            http://identi.ca/api/statuses/user_timeline.rss?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT 
			* since_id. Optional. Returns only statuses with an ID greater than (that is, more recent than)
            the specified ID. Ex: http://identi.ca/api/statuses/user_timeline.xml?since_id=12345 * page.
            Optional. Ex: http://identi.ca/api/statuses/friends_timeline.rss?page=3
	*/
	function user_timeline($args, $apidata) {
		parent::handle($args);
		
		$id = $this->arg('id');
		$count = $this->arg('count');
		$since = $this->arg('since');
		$since_id = $this->arg('since_id');
		
		print "User Timeline! requested content-type: " . $apidata['content-type'] . "\n";
		print "id: $id since: $since, since_id: $since_id, count: $count\n";
		
		exit();	
	}
	
	/*
		Returns a single status, specified by the id parameter below. The status's author will be returned inline.
		
		 URL: http://identi.ca/api/statuses/show/id.format
		
		 Formats: xml, json
		
		 Parameters:
		
		 * id. Required. The numerical ID of the status you're trying to retrieve. 
		 Ex: http://identi.ca/api/statuses/show/123.xml
	*/
	function show($args, $apidata) {
		parent::handle($args);

		$id = $this->arg('id');
		
		print "show requested content-type: " . $apidata['content-type'] . "\n";
		print "id: $id\n";
		
		exit();
		
	}
	
	/*
		Updates the authenticating user's status.  Requires the status parameter specified below.  Request must be a POST.

		URL: http://identi.ca/api/statuses/update.format

		Formats: xml, json.  Returns the posted status in requested format when successful.

		Parameters:

		    * status. Required. The text of your status update. Be sure to URL encode as necessary. Must not be more than 160
            characters and should not be more than 140 characters to ensure optimal display.

	*/
	function update($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
	/*
		Returns the 20 most recent @replies (status updates prefixed with @username) for the authenticating user.
		URL: http://identi.ca/api/statuses/replies.format
 		
		Formats: xml, json, rss, atom

 		Parameters:

 		* page. Optional. Retrieves the 20 next most recent replies. Ex: http://identi.ca/api/statuses/replies.xml?page=3 
		* since. Optional. Narrows the returned results to just those replies created after the specified HTTP-formatted date. The
        same behavior is available by setting an If-Modified-Since header in your HTTP request. Ex:
        http://identi.ca/api/statuses/replies.xml?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
		* since_id. Optional. Returns only statuses with an ID greater than (that is, more recent than) the specified
		ID. Ex: http://identi.ca/api/statuses/replies.xml?since_id=12345
	*/
	function replies($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
	
	/*
		Destroys the status specified by the required ID parameter. The authenticating user must be
        the author of the specified status.
		
		 URL: http://identi.ca/api/statuses/destroy/id.format
		
		 Formats: xml, json
		
		 Parameters:
		
		 * id. Required. The ID of the status to destroy. Ex:
        	http://identi.ca/api/statuses/destroy/12345.json or
        	http://identi.ca/api/statuses/destroy/23456.xml
	
	*/
	function destroy($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
	# User Methods
	
	/*
		Returns up to 100 of the authenticating user's friends who have most recently updated, each with current status inline.
        It's also possible to request another user's recent friends list via the id parameter below.
		
		 URL: http://identi.ca/api/statuses/friends.format
		
		 Formats: xml, json
		
		 Parameters:
		
		 * id. Optional. The ID or screen name of the user for whom to request a list of friends. Ex:
        	http://identi.ca/api/statuses/friends/12345.json 
			or 
			http://identi.ca/api/statuses/friends/bob.xml
		 * page. Optional. Retrieves the next 100 friends. Ex: http://identi.ca/api/statuses/friends.xml?page=2
		 * lite. Optional. Prevents the inline inclusion of current status. Must be set to a value of true. Ex:
        	http://identi.ca/api/statuses/friends.xml?lite=true
		 * since. Optional. Narrows the returned results to just those friendships created after the specified
  			HTTP-formatted date. The same behavior is available by setting an If-Modified-Since header in your HTTP
  			request. Ex: http://identi.ca/api/statuses/friends.xml?since=Tue%2C+27+Mar+2007+22%3A55%3A48+GMT
	*/
	function friends($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
	/*
		Returns the authenticating user's followers, each with current status inline. They are ordered by the
		order in which they joined Twitter (this is going to be changed).
		
		URL: http://identi.ca/api/statuses/followers.format
		Formats: xml, json

		Parameters: 

		    * id. Optional. The ID or screen name of the user for whom to request a list of followers. Ex:
            	http://identi.ca/api/statuses/followers/12345.json 
				or 
				http://identi.ca/api/statuses/followers/bob.xml
		    * page. Optional. Retrieves the next 100 followers. Ex: http://identi.ca/api/statuses/followers.xml?page=2   
		    * lite. Optional. Prevents the inline inclusion of current status. Must be set to a value of true.
		 		Ex: http://identi.ca/api/statuses/followers.xml?lite=true
	*/
	function followers($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
	/*
	Returns a list of the users currently featured on the site with their current statuses inline. 
	URL: http://identi.ca/api/statuses/featured.format 

	Formats: xml, json
	*/
	function featured($args, $apidata) {
		parent::handle($args);
		common_server_error("API method under construction.", $code=501);
	}
	
}


