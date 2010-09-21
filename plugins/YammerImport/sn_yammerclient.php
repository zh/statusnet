<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * Basic client class for Yammer's OAuth/JSON API.
 * 
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class SN_YammerClient
{
    protected $apiBase = "https://www.yammer.com";
    protected $consumerKey, $consumerSecret;
    protected $token, $tokenSecret;

    public function __construct($consumerKey, $consumerSecret, $token=null, $tokenSecret=null)
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->token = $token;
        $this->tokenSecret = $tokenSecret;
    }

    /**
     * Make an HTTP hit with OAuth headers and return the response body on success.
     *
     * @param string $path URL chunk for the API method
     * @param array $params
     * @return array
     *
     * @throws Exception for HTTP error
     */
    protected function fetch($path, $params=array())
    {
        $url = $this->apiBase . '/' . $path;
        if ($params) {
            $url .= '?' . http_build_query($params, null, '&');
        }
        $headers = array('Authorization: ' . $this->authHeader());

        $client = HTTPClient::start();
        $response = $client->get($url, $headers);

        if ($response->isOk()) {
            return $response->getBody();
        } else {
            throw new Exception("Yammer API returned HTTP code " . $response->getStatus() . ': ' . $response->getBody());
        }
    }

    /**
     * Hit the main Yammer API point and decode returned JSON data.
     *
     * @param string $method
     * @param array $params
     * @return array from JSON data
     *
     * @throws Exception for HTTP error or bad JSON return
     */
    protected function api($method, $params=array())
    {
        $body = $this->fetch("api/v1/$method.json", $params);
        $data = json_decode($body, true);
        if (!$data) {
            throw new Exception("Invalid JSON response from Yammer API");
        }
        return $data;
    }

    /**
     * Build an Authorization header value from the keys we have available.
     */
    protected function authHeader()
    {
        // token
        // token_secret
        $params = array('realm' => '',
                        'oauth_consumer_key' => $this->consumerKey,
                        'oauth_signature_method' => 'PLAINTEXT',
                        'oauth_timestamp' => time(),
                        'oauth_nonce' => time(),
                        'oauth_version' => '1.0');
        if ($this->token) {
            $params['oauth_token'] = $this->token;
        }
        if ($this->tokenSecret) {
            $params['oauth_signature'] = $this->consumerSecret . '&' . $this->tokenSecret;
        } else {
            $params['oauth_signature'] = $this->consumerSecret . '&';
        }
        if ($this->verifier) {
            $params['oauth_verifier'] = $this->verifier;
        }
        $parts = array_map(array($this, 'authHeaderChunk'), array_keys($params), array_values($params));
        return 'OAuth ' . implode(', ', $parts);
    }

    /**
     * @param string $key
     * @param string $val
     */
    protected function authHeaderChunk($key, $val)
    {
        return urlencode($key) . '="' . urlencode($val) . '"';
    }

    /**
     * @return array of oauth return data; should contain nice things
     */
    public function requestToken()
    {
        if ($this->token || $this->tokenSecret) {
            throw new Exception("Requesting a token, but already set up with a token");
        }
        $data = $this->fetch('oauth/request_token');
        $arr = array();
        parse_str($data, $arr);
        return $arr;
    }

    /**
     * @return array of oauth return data; should contain nice things
     */
    public function accessToken($verifier)
    {
        $this->verifier = $verifier;
        $data = $this->fetch('oauth/access_token');
        $this->verifier = null;
        $arr = array();
        parse_str($data, $arr);
        return $arr;
    }

    /**
     * Give the URL to send users to to authorize a new app setup
     *
     * @param string $token as returned from accessToken()
     * @return string URL
     */
    public function authorizeUrl($token)
    {
        return $this->apiBase . '/oauth/authorize?oauth_token=' . urlencode($token);
    }

    public function messages($params)
    {
        return $this->api('messages', $params);
    }
}
