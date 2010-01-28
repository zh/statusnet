#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

require_once INSTALLDIR . '/extlib/OAuth.php';

$ini = parse_ini_file("oauth.ini");

$test_consumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);

$at_endpoint = $ini['apiroot'] . $ini['access_token_url'];

$shortoptions = 't:s:';
$longoptions = array('oauth_token=', 'token_secret=');

$helptext = <<<END_OF_ETOKENS_HELP
  exchangetokens.php [options]
  Exchange an authorized OAuth request token for an access token

    -t --oauth_token       authorized request token
    -s --token_secret      authorized request token secret

END_OF_ETOKENS_HELP;

require_once INSTALLDIR . '/scripts/commandline.inc';

$token        = null;
$token_secret = null;

if (have_option('t', 'oauth_token')) {
    $token = get_option_value('oauth_token');
}

if (have_option('s', 'token_secret')) {
    $token_secret = get_option_value('s', 'token_secret');
}

if (empty($token)) {
    print "Please specify a request token.\n";
    exit(1);
}

if (empty($token_secret)) {
    print "Please specify a request token secret.\n";
    exit(1);
}

$rt = new OAuthToken($token, $token_secret);
common_debug("Exchange request token = " . var_export($rt, true));

$parsed = parse_url($at_endpoint);
$params = array();
parse_str($parsed['query'], $params);

$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

$req_req = OAuthRequest::from_consumer_and_token($test_consumer, $rt, "GET", $at_endpoint, $params);
$req_req->sign_request($hmac_method, $test_consumer, $rt);

$r = httpRequest($req_req->to_url());

common_debug("Exchange request token = " . var_export($rt, true));
common_debug("Exchange tokens URL: " . $req_req->to_url());

$body = $r->getBody();

$token_stuff = array();
parse_str($body, $token_stuff);

print 'Access token        : ' . $token_stuff['oauth_token'] . "\n";
print 'Access token secret : ' . $token_stuff['oauth_token_secret'] . "\n";

function httpRequest($url)
{
    $request = HTTPClient::start();

    $request->setConfig(array(
			      'follow_redirects' => true,
			      'connect_timeout' => 120,
			      'timeout' => 120,
			      'ssl_verify_peer' => false,
			      'ssl_verify_host' => false
			      ));

    return $request->get($url);
}

