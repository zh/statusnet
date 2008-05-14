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

# Show a server error

function common_server_error($msg) {
	header('Status: 500 Server Error');
	header('Content-type: text/plain');

	print $msg;
	exit();
}

# Show a user error
function common_user_error($msg, $code=200) {
	common_show_header('Error');
	common_element('div', array('class' => 'error'), $msg);
	common_show_footer();
}

# Start an HTML element
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
	if ($content) print htmlspecialchars($content);
	common_element_end($tag);
}

function common_show_header($pagetitle) {
	global $config;
	common_element_start('html');
	common_element_start('head');
	common_element('title', NULL, 
				   $pagetitle . " - " . $config['site']['name']);
	common_element_end('head');
	common_element_start('body');
}

function common_show_footer() {
	common_element_end('body');
	common_element_end('html');
}

# salted, hashed passwords are stored in the DB

function common_munge_password($id, $password) {
	return md5($id . $password);
}

# check if a username exists and has matching password
function common_check_user($nickname, $password) {
	$user = User::staticGet('nickname', $nickname);
	if (is_null($user)) {
		return false;
	} else {
		return (0 == strcmp(common_munge_password($password, $user->id), 
							$user->password));
	}
}

# is the current user logged in?
function common_logged_in() {
	return (!is_null(common_current_user()));
}

function common_have_session() {
	return (0 != strcmp(session_id(), ''));
}

function common_ensure_session() {
	if (!common_have_session()) {
		@session_start();
	}
}

function common_set_user($nickname) {
	if (is_null($nickname) && common_have_session()) {
		unset($_SESSION['userid']);
		return true;
	} else {
		$user = User::staticGet('nickname', $nickname);
		if ($user) {
			common_ensure_session();
			$_SESSION['userid'] = $user->id;
			return true;
		} else {
			return false;
		}
	}
	return false;
}

# who is the current user?
function common_current_user() {
	static $user = NULL; # FIXME: global memcached
	if (is_null($user)) {
		if (common_have_session()) {
			$id = $_SESSION['userid'];
			if ($id) {
				$user = User::staticGet($id);
			}
		}
	}
	return $user;
}

# get canonical version of nickname for comparison
function common_canonical_nickname($nickname) {
	# XXX: UTF-8 canonicalization (like combining chars)
	return strtolower($nickname);
}

function common_render_content($text) {
	# XXX: @ messages
	# XXX: # tags
	# XXX: machine tags
	return htmlspecialchars($text);
}

// XXX: set up gettext

function _t($str) { $str }
