<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2009, Control Yourself, Inc.
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
		return true;
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

            $context = stream_context_create(array('http' => array('method' => "POST",
                                                                   'header' =>
                                                                   "Content-Type: text/xml\r\n".
                                                                   "User-Agent: Laconica/".LACONICA_VERSION."\r\n",
                                                                   'content' => $req)));
            $file = file_get_contents($notify_url, false, $context);

            if ($file === false || mb_strlen($file) == 0) {
                common_log(LOG_WARNING,
                           "XML-RPC empty results for ping ($notify_url, $notice->id) ");
                continue;
            }

            $response = xmlrpc_decode($file);

            if (is_array($response) && xmlrpc_is_fault($response)) {
                common_log(LOG_WARNING,
                           "XML-RPC error for ping ($notify_url, $notice->id) ".
                           "$response[faultString] ($response[faultCode])");
            } else {
                common_log(LOG_INFO,
                           "Ping success for $notify_url $notice->id");
            }
            break;

		 case 'get':
		 case 'post':
            $args = array('name' => $profile->nickname,
                          'url' => common_local_url('showstream',
                                                    array('nickname' => $profile->nickname)),
                          'changesURL' => common_local_url('userrss',
                                                           array('nickname' => $profile->nickname)));

            $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();

            if ($type === 'get') {
                $result = $fetcher->get($notify_url . '?' . http_build_query($args),
                                        array('User-Agent: Laconica/'.LACONICA_VERSION));
            } else {
                $result = $fetcher->post($notify_url,
                                         http_build_query($args),
                                         array('User-Agent: Laconica/'.LACONICA_VERSION));
            }
            if ($result->status != '200') {
                common_log(LOG_WARNING,
                           "Ping error for '$notify_url' ($notice->id): ".
                           "$result->body");
            } else {
                common_log(LOG_INFO,
                           "Ping success for '$notify_url' ($notice->id): ".
                           "'$result->body'");
            }
            break;

		 default:
			common_log(LOG_WARNING, 'Unknown notify type for ' . $notify_url . ': ' . $type);
        }
	}

    return true;
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