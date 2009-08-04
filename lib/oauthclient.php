<?php

require_once('OAuth.php');

class OAuthClientCurlException extends Exception { }

class OAuthClient
{
    var $consumer;
    var $token;

    function __construct($consumer_key, $consumer_secret,
                         $oauth_token = null, $oauth_token_secret = null)
    {
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
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

    function getAuthorizeLink($request_token, $oauth_callback = null)
    {
        $url = TwitterOAuthClient::$authorizeURL . '?oauth_token=' .
            $request_token->key;

        if (isset($oauth_callback)) {
            $url .= '&oauth_callback=' . urlencode($oauth_callback);
        }

        return $url;
    }

    function getAccessToken()
    {
        $response = $this->oAuthPost(TwitterOAuthClient::$accessTokenURL);
        parse_str($response);
        $token = new OAuthToken($oauth_token, $oauth_token_secret);
        return $token;
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
