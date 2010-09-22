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

$data = $yam->users();
var_dump($data);
// @fixme follow paging
foreach ($data as $item) {
    $user = $imp->prepUser($item);
    var_dump($user);
}

/*
$data = $yam->messages();
var_dump($data);
// @fixme follow paging
$messages = $data['messages'];
$messages = array_reverse($messages);
foreach ($messages as $message) {
    $notice = $imp->prepNotice($message);
    var_dump($notice);
}
*/
