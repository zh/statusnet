<?php

# global configuration object

// default configuration, overwritten in config.php

$config =
  array('site' => 
		array('name' => 'Just another µB'),
		'dsn' =>
		array('phptype' => 'mysql',
			  'username' => 'stoica',
			  'password' => 'apasswd',
			  'hostspec' => 'localhost',
			  'database' => 'thedb')
		'dboptions' =>
		array('debug' => 2,
			  'portability' => DB_PORTABILITY_ALL));

require_once(INSTALLDIR . '/config.php');
require_once('DB.php');

function common_database() {
	global $config;
	$db =& DB::connect($config['dsn'], $config['dboptions']);
	if (PEAR::isError($db)) {
		common_server_error($db->getMessage());
	} else {
		return $db;
	}
}

function common_read_database() {
	// XXX: read from slave server
	return common_database();
}

function common_server_error($msg) {
	header('Status: 500 Server Error');
	header('Content-type: text/plain');

	print $msg;
	exit();
}
