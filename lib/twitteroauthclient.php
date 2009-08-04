<?php

class TwitterOAuthClient extends OAuthClient
{
    public static $requestTokenURL = 'https://twitter.com/oauth/request_token';
    public static $authorizeURL    = 'https://twitter.com/oauth/authorize';
    public static $accessTokenURL  = 'https://twitter.com/oauth/access_token';

    function __construct($oauth_token = null, $oauth_token_secret = null)
    {
        $consumer_key    = common_config('twitter', 'consumer_key');
        $consumer_secret = common_config('twitter', 'consumer_secret');

        parent::__construct($consumer_key, $consumer_secret,
                            $oauth_token, $oauth_token_secret);
    }

    function getAuthorizeLink($request_token) {
        return parent::getAuthorizeLink($request_token,
                                        common_local_url('twitterauthorization'));

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

    function statuses_friends_timeline($since_id = null, $max_id = null,
                                       $cnt = null, $page = null) {

        $url = 'http://twitter.com/statuses/friends_timeline.json';
        $params = array('since_id' => $since_id,
                        'max_id' => $max_id,
                        'count' => $cnt,
                        'page' => $page);
        $qry = http_build_query($params);

        if (!empty($qry)) {
            $url .= "?$qry";
        }

        $response = $this->oAuthGet($url);
        $statuses = json_decode($response);
        return $statuses;
    }

}
