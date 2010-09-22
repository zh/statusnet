<?php

if (php_sapi_name() != 'cli') {
    die('no');
}

define('INSTALLDIR', dirname(dirname(dirname(__FILE__))));

require INSTALLDIR . "/scripts/commandline.inc";

// temp stuff
require 'yam-config.php';
$yam = new SN_YammerClient($consumerKey, $consumerSecret, $token, $tokenSecret);
$imp = new YammerImporter($yam);

$data = $yam->messages();
var_dump($data);

/*
  ["messages"]=>
  ["meta"]=> // followed_user_ids, current_user_id, etc
  ["threaded_extended"]=> // empty!
  ["references"]=> // lists the users, threads, replied messages, tags
*/

// 1) we're getting messages in descending order, but we'll want to process ascending
// 2) we'll need to pull out all those referenced items too?
// 3) do we need to page over or anything?

// 20 qualifying messages per hit...
// use older_than to grab more
// (better if we can go in reverse though!)
// meta: The older-available element indicates whether messages older than those shown are available to be fetched. See the older_than parameter mentioned above.

foreach ($data['references'] as $item) {
    if ($item['type'] == 'user') {
        $user = $imp->prepUser($item);
        var_dump($user);
    } else if ($item['type'] == 'group') {
        $group = $imp->prepGroup($item);
        var_dump($group);
    } else if ($item['type'] == 'tag') {
        // could need these if we work from the parsed message text
        // otherwise, the #blarf in orig text is fine.
    } else if ($item['type'] == 'thread') {
        // Shouldn't need thread info; we'll reconstruct conversations
        // from the reply-to chains.
    } else if ($item['type'] == 'message') {
        // If we're processing everything, then we don't need the refs here.
    } else {
        echo "(skipping unknown ref: " . $item['type'] . ")\n";
    }
}

// Process in reverse chron order...
// @fixme follow paging
$messages = $data['messages'];
array_reverse($messages);
foreach ($messages as $message) {
    $notice = $imp->prepNotice($message);
    var_dump($notice);
}
