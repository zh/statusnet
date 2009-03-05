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

function ping_broadcast_notice($notice) {
	if (!$notice->is_local) {
		return;
	}
	
	# Array of servers, URL => type
	$notify = common_config('ping', 'notify');
	$profile = $notice->getProfile();
	$tags = ping_notice_tags($notice);
	
	foreach ($notify as $notify_url => $type) {
		switch ($type) {
		 case 'xmlrpc':
		 case 'extended':
			$req = xmlrpc_encode_request('weblogUpdates.ping',
										 array($profile->nickname, # site name
											   common_local_url('showstream', 
																array('nickname' => $profile->nickname)),
											   common_local_url('shownotice',
																array('notice' => $notice->id)),
											   common_local_url('userrss', 
																array('nickname' => $profile->nickname)),
											   $tags));
			
			# We re-use this tool's fetcher, since it's pretty good
	
			$fetcher = Auth_Yadis_Yadis::getHTTPFetcher();

			if (!$fetcher) {
				common_log(LOG_WARNING, 'Failed to initialize Yadis fetcher.', __FILE__);
				return false;
			}
	
			$result = $fetcher->post($notify_url,
									 $req);
											   
		 case 'get':
		 case 'post':			
		 default:
			common_log(LOG_WARNING, 'Unknown notify type for ' . $notify_url . ': ' . $type);
										   }
	}
}
		
function ping_notice_tags($notice) {
	$tag = new Notice_tag();
	$tag->notice_id = $notice->id;
	$tags = array();
	if ($tag->find()) {
		while ($tag->fetch()) {
			$tags[] = $tag->tag;
		}
		$tag->free();
		unset($tag);
		return implode('|', $tags);
	}
	return NULL;
}