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

function common_user_error($msg) {
	common_show_header('Error');
	common_element('div', array('class' => 'error'), $msg);
	common_show_footer();
}

function common_element_start($tag, $attrs=NULL) {
	print "<$tag";
	if (is_array($attrs)) {
		foreach ($attrs as $name => $value) {
			print " $name='$value'";
		}
	} else if (is_string($attrs)) {
		print " class='$attrs'";
	}
	print '>';
}

function common_element_end($tag) {
	print "</$tag>";
}

function common_element($tag, $attrs=NULL, $content=NULL) {
    common_element_start($tag, $attrs);
	if ($content) print $content;
	common_element_end($tag);
}

function common_show_header($pagetitle) {
	global $config;
	common_element_start('html');
	common_element_start('head');
	common_element('title', NULL, $pagetitle . " - " . $config['site']['name']);
	common_element_end('head');
	common_element_start('body');
}

function common_show_footer() {
	common_element_end('body');
	common_element_end('html');
}

// TODO: set up gettext

function _t($str) { $str }
