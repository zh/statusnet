<?php

if (php_sapi_name() != 'cli') {
    die('no');
}

define('INSTALLDIR', dirname(dirname(dirname(dirname(__FILE__)))));

require INSTALLDIR . "/scripts/commandline.inc";

// temp stuff
require 'yam-config.php';
$yam = new SN_YammerClient($consumerKey, $consumerSecret, $token, $tokenSecret);
$imp = new YammerImporter($yam);

// First, import all the users!
// @fixme follow paging -- we only get 50 at a time
$data = $yam->users();
foreach ($data as $item) {
    $user = $imp->importUser($item);
    echo "Imported Yammer user " . $item['id'] . " as $user->nickname ($user->id)\n";
}

// Groups!
// @fixme follow paging -- we only get 20 at a time
$data = $yam->groups();
foreach ($data as $item) {
    $group = $imp->importGroup($item);
    echo "Imported Yammer group " . $item['id'] . " as $group->nickname ($group->id)\n";
}

// Messages!
// Process in reverse chron order...
// @fixme follow paging -- we only get 20 at a time, and start at the most recent!
$data = $yam->messages();
$messages = $data['messages'];
$messages = array_reverse($messages);
foreach ($messages as $item) {
    $notice = $imp->importNotice($item);
    echo "Imported Yammer notice " . $item['id'] . " as $notice->id\n";
}
