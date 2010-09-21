<?php

if (php_sapi_name() != 'cli') {
    die('no');
}

define('INSTALLDIR', dirname(dirname(dirname(__FILE__))));

require INSTALLDIR . "/scripts/commandline.inc";

// temp stuff
require 'yam-config.php';
$yam = new SN_YammerClient($consumerKey, $consumerSecret, $token, $tokenSecret);
$imp = new YammerImporter();

$data = $yam->messages();
/*
  ["messages"]=>
  ["meta"]=> // followed_user_ids, current_user_id, etc
  ["threaded_extended"]=> // empty!
  ["references"]=> // lists the users, threads, replied messages, tags
*/

// 1) we're getting messages in descending order, but we'll want to process ascending
// 2) we'll need to pull out all those referenced items too?
// 3) do we need to page over or anything?

foreach ($data['messages'] as $message) {
    $notice = $imp->messageToNotice($message);
    var_dump($notice);
}
