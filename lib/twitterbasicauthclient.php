<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for doing OAuth calls against Twitter
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Integration
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Exception wrapper for cURL errors
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class BasicAuthCurlException extends Exception
{
}

/**
 * Class for talking to the Twitter API with HTTP Basic Auth.
 *
 * @category Integration
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */
class TwitterBasicAuthClient
{
    var $screen_name = null;
    var $password    = null;

    /**
     * constructor
     *
     * @param Foreign_link $flink a Foreign_link storing the
     *                            Twitter user's password, etc.
     */
    function __construct($flink)
    {
        $fuser             = $flink->getForeignUser();
        $this->screen_name = $fuser->nickname;
        $this->password    = $flink->credentials;
    }

    /**
     * Calls Twitter's /stutuses/update API method
     *
     * @param string $status                text of the status
     * @param int    $in_reply_to_status_id optional id of the status it's
     *                                      a reply to
     *
     * @return mixed the status
     */
    function statusesUpdate($status, $in_reply_to_status_id = null)
    {
        $url      = 'https://twitter.com/statuses/update.json';
        $params   = array('status' => $status,
                          'source' => common_config('integration', 'source'),
                          'in_reply_to_status_id' => $in_reply_to_status_id);
        $response = $this->httpRequest($url, $params);
        $status   = json_decode($response);
        return $status;
    }

    /**
     * Calls Twitter's /stutuses/friends_timeline API method
     *
     * @param int $since_id show statuses after this id
     * @param int $max_id   show statuses before this id
     * @param int $cnt      number of statuses to show
     * @param int $page     page number
     *
     * @return mixed an array of statuses
     */
    function statusesFriendsTimeline($since_id = null, $max_id = null,
                                     $cnt = null, $page = null)
    {
        $url    = 'https://twitter.com/statuses/friends_timeline.json';
        $params = array('since_id' => $since_id,
                        'max_id' => $max_id,
                        'count' => $cnt,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
        $statuses = json_decode($response);
        return $statuses;
    }

    /**
     * Calls Twitter's /stutuses/friends API method
     *
     * @param int $id          id of the user whom you wish to see friends of
     * @param int $user_id     numerical user id
     * @param int $screen_name screen name
     * @param int $page        page number
     *
     * @return mixed an array of twitter users and their latest status
     */
    function statusesFriends($id = null, $user_id = null, $screen_name = null,
                             $page = null)
    {
        $url = "https://twitter.com/statuses/friends.json";

        $params = array('id' => $id,
                        'user_id' => $user_id,
                        'screen_name' => $screen_name,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
        $friends  = json_decode($response);
        return $friends;
    }

    /**
     * Calls Twitter's /stutuses/friends/ids API method
     *
     * @param int $id          id of the user whom you wish to see friends of
     * @param int $user_id     numerical user id
     * @param int $screen_name screen name
     * @param int $page        page number
     *
     * @return mixed a list of ids, 100 per page
     */
    function friendsIds($id = null, $user_id = null, $screen_name = null,
                         $page = null)
    {
        $url = "https://twitter.com/friends/ids.json";

        $params = array('id' => $id,
                        'user_id' => $user_id,
                        'screen_name' => $screen_name,
                        'page' => $page);
        $qry    = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->httpRequest($url);
        $ids      = json_decode($response);
        return $ids;
    }

    /**
     * Make a HTTP request using cURL.
     *
     * @param string $url    Where to make the request
     * @param array  $params post parameters
     *
     * @return mixed the request
     */
    function httpRequest($url, $params = null, $auth = true)
    {
        $options = array(
                         CURLOPT_RETURNTRANSFER => true,
                         CURLOPT_FAILONERROR    => true,
                         CURLOPT_HEADER         => false,
                         CURLOPT_FOLLOWLOCATION => true,
                         CURLOPT_USERAGENT      => 'StatusNet',
                         CURLOPT_CONNECTTIMEOUT => 120,
                         CURLOPT_TIMEOUT        => 120,
                         CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
                         CURLOPT_SSL_VERIFYPEER => false,

                         // Twitter is strict about accepting invalid "Expect" headers

                         CURLOPT_HTTPHEADER => array('Expect:')
                         );

        if (isset($params)) {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = $params;
        }

        if ($auth) {
            $options[CURLOPT_USERPWD] = $this->screen_name .
              ':' . $this->password;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if ($response === false) {
            $msg  = curl_error($ch);
            $code = curl_errno($ch);
            throw new BasicAuthCurlException($msg, $code);
        }

        curl_close($ch);

        return $response;
    }

}
