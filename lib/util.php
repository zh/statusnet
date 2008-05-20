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

/* XXX: break up into separate modules (HTTP, HTML, user, files) */

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

$xw = null;

# Start an HTML element
function common_element_start($tag, $attrs=NULL) {
	global $xw;
	$xw->startElement($tag);
	if (is_array($attrs)) {
		foreach ($attrs as $name => $value) {
			$xw->writeAttribute($name, $value);
		}
	} else if (is_string($attrs)) {
		$xw->writeAttribute('class', $attrs);
	}
}

function common_element_end($tag) {
	global $xw;
	$xw->endElement();
}

function common_element($tag, $attrs=NULL, $content=NULL) {
    common_element_start($tag, $attrs);
	if ($content) {
		global $xw;
		$xw->text($content);
	}
	common_element_end($tag);
}

function common_show_header($pagetitle) {
	global $config, $xw;

	header('Content-Type: application/xhtml+xml');
	
	$xw = new XMLWriter();
	$xw->openURI('php://output');
	$xw->startDocument('1.0', 'UTF-8');
	$xw->writeDTD('html', '-//W3C//DTD XHTML 1.0 Strict//EN',
				  'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');

	# FIXME: correct language for interface
	
	common_element_start('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
									   'xml:lang' => 'en',
									   'lang' => 'en'));
	
	common_element_start('head');
	common_element('title', NULL, 
				   $pagetitle . " - " . $config['site']['name']);
	common_element('link', array('rel' => 'stylesheet',
								 'type' => 'text/css',
								 'href' => $config['site']['path'] . 'theme/default/style/html.css',
								 'media' => 'screen, projection, tv'));
	common_element('link', array('rel' => 'stylesheet',
								 'type' => 'text/css',
								 'href' => $config['site']['path'] . 'theme/default/style/layout.css',
								 'media' => 'screen, projection, tv'));
	common_element('link', array('rel' => 'stylesheet',
								 'type' => 'text/css',
								 'href' => $config['site']['path'] . 'theme/default/style/print.css',
								 'media' => 'print'));
	common_element_end('head');
	common_element_start('body');
	common_element_start('div', array('id' => 'wrapper'));
	common_element_start('div', array('id' => 'content'));
	common_element_start('div', array('id' => 'header'));	
	common_element('h1', 'title', $pagetitle);
	common_element('h2', 'subtitle', $config['site']['name']);
	common_element_end('div');
	common_head_menu();
	common_element_start('div', array('id' => 'page'));
}

function common_show_footer() {
	global $xw, $config;
	common_element_start('p', 'footer');
	common_foot_menu();
	common_license_block();
	common_element_end('p');
	common_element_end('div');
	common_element_end('div');
	common_element_end('div');
	common_element_end('body');
	common_element_end('html');
	$xw->endDocument();
	$xw->flush();
}

function common_text($txt) {
	global $xw;
	$xw->text($txt);
}

function common_license_block() {
	global $config, $xw;
	common_element_start('div', 'license');
	common_element_start('a', array('class' => 'license',
									'rel' => 'license',
									href => $config['license']['url']));
	common_element('img', array('class' => 'license',
								'src' => $config['license']['image'],
								'alt' => $config['license']['title']));
	common_element_end('a');
	common_text(_t('Unless otherwise specified, contents of this site are copyright by the contributors and available under the '));
	common_element('a', array('class' => 'license',
							  'rel' => 'license',
							  href => $config['license']['url']),
				   $config['license']['title']);
	common_text(_t('. Contributors should be attributed by full name or nickname.'));
	common_element_end('div');
}

function common_head_menu() {
	$user = common_current_user();
	common_element_start('ul', array('id' => 'menu', 'class' => ($user) ? 'five' : 'three'));
	common_menu_item(common_local_url('doc', array('title' => 'help')),
					 _t('Help'));
	if ($user) {
		common_menu_item(common_local_url('all', array('nickname' => 
													   $user->nickname)),
						 _t('Home'));
		common_menu_item(common_local_url('showstream', array('nickname' =>
															  $user->nickname)),
						 _t('Profile'),  $user->fullname || $user->nickname);
		common_menu_item(common_local_url('profilesettings'),
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

function common_input($id, $label, $value=NULL) {
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'text',
				   'id' => $id);
	if ($value) {
		$attrs['value'] = htmlspecialchars($value);
	}
	common_element('input', $attrs);
}

function common_password($id, $label) {
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'password',
				   'id' => $id);
	common_element('input', $attrs);
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
		common_ensure_session();
		$id = $_SESSION['userid'];
		if ($id) {
			$user = User::staticGet($id);
		}
	}
	return $user;
}

# get canonical version of nickname for comparison
function common_canonical_nickname($nickname) {
	# XXX: UTF-8 canonicalization (like combining chars)
	return $nickname;
}

# get canonical version of email for comparison
function common_canonical_email($email) {
	# XXX: canonicalize UTF-8
	# XXX: lcase the domain part
	return $email;
}

function common_render_content($text) {
	# XXX: @ messages
	# XXX: # tags
	# XXX: machine tags
	return htmlspecialchars($text);
}

// where should the avatar go for this user?

function common_avatar_filename($user, $extension, $size=NULL) {
	global $config;

	if ($size) {
		return $user->id . '-' . $size . $extension;
	} else {
		return $user->id . '-original' . $extension;
	}
}

function common_avatar_path($filename) {
	global $config;
	return $config['avatar']['directory'] . '/' . $filename;
}

function common_avatar_url($filename) {
	global $config;
	return $config['avatar']['path'] . '/' . $filename;
}

function common_local_url($action, $args=NULL) {
	global $config;
	/* XXX: pretty URLs */
	$extra = '';
	if ($args) {
		foreach ($args as $key => $value) {
			$extra .= "&${key}=${value}";
		}
	}
	$pathpart = ($config['site']['path']) ? $config['site']['path']."/" : '';
	return "http://".$config['site']['server'].'/'.$pathpart."index.php?action=${action}${extra}";
}

function common_date_string($dt) {
	// XXX: do some sexy date formatting
	// return date(DATE_RFC822, $dt);
	return $dt;
}

function common_redirect($url, $code=307) {
	static $status = array(301 => "Moved Permanently",
						   302 => "Found",
						   303 => "See Other",
						   307 => "Temporary Redirect");
	header("Status: ${code} $status[$code]");
	header("Location: $url");
	common_element('a', array('href' => $url), $url);
}

function common_broadcast_notices($id) {
	// XXX: broadcast notices to remote subscribers
	// XXX: broadcast notices to SMS
	// XXX: broadcast notices to Jabber
	// XXX: broadcast notices to other IM
	// XXX: use a queue system like http://code.google.com/p/microapps/wiki/NQDQ
	return true;
}

function common_profile_url($nickname) {
	return common_local_url('showstream', array('nickname' => $nickname));
}

// XXX: set up gettext

function _t($str) { 
	return $str;
}
