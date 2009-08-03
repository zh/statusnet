<?php

require_once('OAuth.php');

class OAuthClientCurlException extends Exception { }

class TwitterOAuthClient
{
    public static $requestTokenURL = 'https://twitter.com/oauth/request_token';
    public static $authorizeURL    = 'https://twitter.com/oauth/authorize';
    public static $accessTokenURL  = 'https://twitter.com/oauth/access_token';

    function __construct($oauth_token = null, $oauth_token_secret = null)
    {
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
        $consumer_key    = common_config('twitter', 'consumer_key');
        $consumer_secret = common_config('twitter', 'consumer_secret');
        $this->consumer = new OAuthConsumer($consumer_key, $consumer_secret);
        $this->token = null;

        if (isset($oauth_token) && isset($oauth_token_secret)) {
            $this->token = new OAuthToken($oauth_token, $oauth_token_secret);
        }
    }

    function getRequestToken()
    {
        $response = $this->oAuthGet(TwitterOAuthClient::$requestTokenURL);
        parse_str($response);
        $token = new OAuthToken($oauth_token, $oauth_token_secret);
        return $token;
    }

    function getAuthorizeLink($request_token)
    {
        // Not sure Twitter actually looks at oauth_callback

        return TwitterOAuthClient::$authorizeURL .
        '?oauth_token=' . $request_token->key . '&oauth_callback=' .
        urlencode(common_local_url('twitterauthorization'));
    }

    function getAccessToken()
    {
        $response = $this->oAuthPost(TwitterOAuthClient::$accessTokenURL);
        parse_str($response);
        $token = new OAuthToken($oauth_token, $oauth_token_secret);
        return $token;
    }

    function verify_credentials()
    {
        $url = 'https://twitter.com/account/verify_credentials.json';
        $response = $this->oAuthGet($url);
        $twitter_user = json_decode($response);
        return $twitter_user;
    }

    function statuses_update($status, $in_reply_to_status_id = null)
    {
        $url = 'https://twitter.com/statuses/update.json';
        $params = array('status' => $status,
            'in_reply_to_status_id' => $in_reply_to_status_id);
        $response = $this->oAuthPost($url, $params);
        $status = json_decode($response);
        return $status;
    }

    function oAuthGet($url)
    {
        $request = OAuthRequest::from_consumer_and_token($this->consumer,
            $this->token, 'GET', $url, null);
        $request->sign_request($this->sha1_method,
            $this->consumer, $this->token);

        return $this->httpRequest($request->to_url());
    }

    function oAuthPost($url, $params = null)
    {
        $request = OAuthRequest::from_consumer_and_token($this->consumer,
            $this->token, 'POST', $url, $params);
        $request->sign_request($this->sha1_method,
            $this->consumer, $this->token);

        return $this->httpRequest($request->get_normalized_http_url(),
            $request->to_postdata());
    }

    function httpRequest($url, $params = null)
    {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Laconica',
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPAUTH       => CURLAUTH_ANY,
            CURLOPT_SSL_VERIFYPEER => false,

            // Twitter is strict about accepting invalid "Expect" headers

            CURLOPT_HTTPHEADER => array('Expect:')
        );

        if (isset($params)) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $params;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if ($response === false) {
            $msg  = curl_error($ch);
            $code = curl_errno($ch);
            throw new OAuthClientCurlException($msg, $code);
        }

        curl_close($ch);

        return $response;
    }

}
