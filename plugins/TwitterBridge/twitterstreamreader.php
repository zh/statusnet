<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

// A single stream connection
abstract class TwitterStreamReader extends JsonStreamReader
{
    protected $callbacks = array();

    function __construct(TwitterOAuthClient $auth, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->oauth = $auth;
    }

    public function connect($method)
    {
        $url = $this->oAuthUrl($this->baseUrl . '/' . $method);
        return parent::connect($url);
    }

    /**
     * Sign our target URL with OAuth auth stuff.
     *
     * @param string $url
     * @param array $params
     * @return string 
     */
    function oAuthUrl($url, $params=array())
    {
        // In an ideal world this would be better encapsulated. :)
        $request = OAuthRequest::from_consumer_and_token($this->oauth->consumer,
            $this->oauth->token, 'GET', $url, $params);
        $request->sign_request($this->oauth->sha1_method,
            $this->oauth->consumer, $this->oauth->token);

        return $request->to_url();
    }
    /**
     * Add an event callback. Available event names include
     * 'raw' (all data), 'friends', 'delete', 'scrubgeo', etc
     *
     * @param string $event
     * @param callable $callback
     */
    public function hookEvent($event, $callback)
    {
        $this->callbacks[$event][] = $callback;
    }

    /**
     * Call event handler callbacks for the given event.
     * 
     * @param string $event
     * @param mixed $arg1 ... one or more params to pass on
     */
    public function fireEvent($event, $arg1)
    {
        if (array_key_exists($event, $this->callbacks)) {
            $args = array_slice(func_get_args(), 1);
            foreach ($this->callbacks[$event] as $callback) {
                call_user_func_array($callback, $args);
            }
        }
    }

    function handleJson(array $data)
    {
        $this->routeMessage($data);
    }

    abstract function routeMessage($data);

    /**
     * Send the decoded JSON object out to any event listeners.
     *
     * @param array $data
     * @param int $forUserId
     */
    function handleMessage(array $data, $forUserId=null)
    {
        $this->fireEvent('raw', $data, $forUserId);

        if (isset($data['text'])) {
            $this->fireEvent('status', $data);
            return;
        }
        if (isset($data['event'])) {
            $this->fireEvent($data['event'], $data);
            return;
        }

        $knownMeta = array('friends', 'delete', 'scrubgeo', 'limit', 'direct_message');
        foreach ($knownMeta as $key) {
            if (isset($data[$key])) {
                $this->fireEvent($key, $data[$key], $forUserId);
                return;
            }
        }
    }
}

class TwitterSiteStream extends TwitterStreamReader
{
    protected $userIds;

    public function __construct(TwitterOAuthClient $auth, $baseUrl='https://stream.twitter.com')
    {
        parent::__construct($auth, $baseUrl);
    }

    public function connect($method='2b/site.json')
    {
        return parent::connect($method);
    }

    function followUsers($userIds)
    {
        $this->userIds = $userIds;
    }

    /**
     * Each message in the site stream tells us which user ID it should be
     * routed to; we'll need that to let the caller know what to do.
     *
     * @param array $data
     */
    function routeMessage($data)
    {
        parent::handleMessage($data['message'], $data['for_user']);
    }
}

class TwitterUserStream extends TwitterStreamReader
{
    public function __construct(TwitterOAuthClient $auth, $baseUrl='https://userstream.twitter.com')
    {
        parent::__construct($auth, $baseUrl);
    }

    public function connect($method='2/user.json')
    {
        return parent::connect($method);
    }

    /**
     * Each message in the user stream is just ready to go.
     *
     * @param array $data
     */
    function routeMessage($data)
    {
        parent::handleMessage($data, $this->userId);
    }
}
