<?php
/* 
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

if (!defined('LACONICA')) { exit(1) }

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
	common_head_menu();
}

function common_show_footer() {
	common_foot_menu();
	common_element_end('body');
	common_element_end('html');
}

function common_head_menu() {
	$user = common_current_user();
	common_element_start('ul', 'headmenu');
	common_menu_item(common_local_url('doc', array('title' => 'help')),
					 _t('Help'));
	if ($user) {
		common_menu_item(common_local_url('all', array('nickname' => 
													   $user->nickname)),
						 _t('Home'));
		common_menu_item(common_local_url('showstream', array('nickname' =>
															  $user->nickname)),
						 _t('Profile'),  $user->fullname || $user->nickname);
		common_menu_item(common_local_url('settings'),
						 _t('Settings'));
		common_menu_item(common_local_url('logout'),
						 _t('Logout'));
	} else {
		common_menu_item(common_local_url('login'),
						 _t('Login'));
		common_menu_item(common_local_url('register'),
						 _t('Register'));
	}
	common_element_end('ul');
}

function common_foot_menu() {
	common_element_start('ul', 'footmenu');
	common_menu_item(common_local_url('doc', array('title' => 'about')),
					 _t('About'));
	common_menu_item(common_local_url('doc', array('title' => 'help')),
					 _t('Help'));
	common_menu_item(common_local_url('doc', array('title' => 'privacy')),
					 _t('Privacy'));
}

function common_menu_item($url, $text, $title=NULL) {
	$attrs['href'] = $url;
	if ($title) {
		$attrs['title'] = $title;
	}
	common_element_start('li', 'menuitem');
	common_element('a', $attrs, $text);
	common_element_end('li');
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
