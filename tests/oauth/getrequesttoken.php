#!/usr/bin/env php
<?php
/*
 * StatusNet - a distributed open-source microblogging tool
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

require_once INSTALLDIR . '/scripts/commandline.inc';
require_once INSTALLDIR . '/extlib/OAuth.php';

$ini = parse_ini_file("oauth.ini");

// Check to make sure we have everything we need from the ini file
foreach(array('consumer_key', 'consumer_secret', 'apiroot', 'request_token_url') as $inikey) {
    if (empty($ini[$inikey])) {
        print "You forgot to specify a $inikey in your oauth.ini file.\n";
        exit(1);
    }
}

$testConsumer = new OAuthConsumer($ini['consumer_key'], $ini['consumer_secret']);
$requestTokenUrl = $ini['apiroot'] . $ini['request_token_url'];
$parsed = parse_url($requestTokenUrl);
$params = array();
parse_str($parsed['query'], $params);
$params['oauth_callback'] = 'oob';

$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

try {
    $req = OAuthRequest::from_consumer_and_token(
        $testConsumer,
        null,
        "GET",
        $requestTokenUrl,
        $params
    );
    $req->sign_request($hmac_method, $testConsumer, NULL);
    $r = httpRequest($req->to_url());
} catch (Exception $e) {
    // oh noez
    print $e->getMessage();
    var_dump($req);
    exit(1);
}

$body = $r->getBody();
$tokenStuff = array();
parse_str($body, $tokenStuff);

if (empty($tokenStuff['oauth_token'])) {
    print "Error: $body\n";
    exit(1);
}

$authurl = $ini['apiroot'] . $ini['authorize_url'] . '?oauth_token=' . $tokenStuff['oauth_token'];
print "\nSuccess!\n\n";
print 'Request token        : ' . $tokenStuff['oauth_token'] . "\n";
print 'Request token secret : ' . $tokenStuff['oauth_token_secret'] . "\n";
print "Authorize URL        : $authurl\n";

print "\nNow paste the Authorize URL into your browser and authorize the request token.\n";

function httpRequest($url)
{
    $request = HTTPClient::start();

    $request->setConfig(
        array(
            'follow_redirects' => true,
	    'connect_timeout' => 120,
	    'timeout' => 120,
	    'ssl_verify_peer' => false,
	    'ssl_verify_host' => false
        )
    );

    return $request->get($url);
}

