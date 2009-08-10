<?php
/**
 * Laconica, the distributed open-source microblogging tool
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
 * @package   Laconica
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Class for talking to the Twitter API with OAuth.
 *
 * @category Integration
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 */
class TwitterOAuthClient extends OAuthClient
{
    public static $requestTokenURL = 'https://twitter.com/oauth/request_token';
    public static $authorizeURL    = 'https://twitter.com/oauth/authorize';
    public static $accessTokenURL  = 'https://twitter.com/oauth/access_token';

    /**
     * Constructor
     *
     * @param string $oauth_token        the user's token
     * @param string $oauth_token_secret the user's token secret
     *
     * @return nothing
     */
    function __construct($oauth_token = null, $oauth_token_secret = null)
    {
        $consumer_key    = common_config('twitter', 'consumer_key');
        $consumer_secret = common_config('twitter', 'consumer_secret');

        parent::__construct($consumer_key, $consumer_secret,
                            $oauth_token, $oauth_token_secret);
    }

    // XXX: the following two functions are to support the horrible hack
    // of using the credentils field in Foreign_link to store both
    // the access token and token secret.  This hack should go away with
    // 0.9, in which we can make DB changes and add a new column for the
    // token itself.

    static function packToken($token)
    {
        return implode(chr(0), array($token->key, $token->secret));
    }

    static function unpackToken($str)
    {
        $vals = explode(chr(0), $str);
        return new OAuthToken($vals[0], $vals[1]);
    }

    /**
     * Builds a link to Twitter's endpoint for authorizing a request token
     *
     * @param OAuthToken $request_token token to authorize
     *
     * @return the link
     */
    function getAuthorizeLink($request_token)
    {
        return parent::getAuthorizeLink(self::$authorizeURL,
                                        $request_token,
                                        common_local_url('twitterauthorization'));
    }

    /**
     * Calls Twitter's /account/verify_credentials API method
     *
     * @return mixed the Twitter user
     */
    function verifyCredentials()
    {
        $url          = 'https://twitter.com/account/verify_credentials.json';
        $response     = $this->oAuthGet($url);
        $twitter_user = json_decode($response);
        return $twitter_user;
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
            'in_reply_to_status_id' => $in_reply_to_status_id);
        $response = $this->oAuthPost($url, $params);
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

        $response = $this->oAuthGet($url);
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

        $response = $this->oAuthGet($url);
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

        $response = $this->oAuthGet($url);
        $ids      = json_decode($response);
        return $ids;
    }

}
