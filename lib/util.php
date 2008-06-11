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

function common_server_error($msg, $code=500) {
	static $status = array(500 => 'Internal Server Error',
						   501 => 'Not Implemented',
						   502 => 'Bad Gateway',
						   503 => 'Service Unavailable',
						   504 => 'Gateway Timeout',
						   505 => 'HTTP Version Not Supported');

	if (!array_key_exists($code, $status)) {
		$code = 500;
	}

	$status_string = $status[$code];

	header('HTTP/1.1 '.$code.' '.$status_string);
	header('Content-type: text/plain');

	print $msg;
	print "\n";
	exit();
}

# Show a user error
function common_user_error($msg, $code=400) {
	static $status = array(400 => 'Bad Request',
						   401 => 'Unauthorized',
						   402 => 'Payment Required',
						   403 => 'Forbidden',
						   404 => 'Not Found',
						   405 => 'Method Not Allowed',
						   406 => 'Not Acceptable',
						   407 => 'Proxy Authentication Required',
						   408 => 'Request Timeout',
						   409 => 'Conflict',
						   410 => 'Gone',
						   411 => 'Length Required',
						   412 => 'Precondition Failed',
						   413 => 'Request Entity Too Large',
						   414 => 'Request-URI Too Long',
						   415 => 'Unsupported Media Type',
						   416 => 'Requested Range Not Satisfiable',
						   417 => 'Expectation Failed');

	if (!array_key_exists($code, $status)) {
		$code = 400;
	}

	$status_string = $status[$code];

	header('HTTP/1.1 '.$code.' '.$status_string);

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

function common_start_xml($doc=NULL, $public=NULL, $system=NULL) {
	global $xw;
	$xw = new XMLWriter();
	$xw->openURI('php://output');
	$xw->setIndent(true);
	$xw->startDocument('1.0', 'UTF-8');
	if ($doc) {
		$xw->writeDTD($doc, $public, $system);
	}
}

function common_end_xml() {
	global $xw;
	$xw->endDocument();
	$xw->flush();
}

function common_show_header($pagetitle, $callable=NULL, $data=NULL, $notice=NULL) {
	global $config, $xw;

	header('Content-Type: application/xhtml+xml');

	common_start_xml('html',
					 '-//W3C//DTD XHTML 1.0 Strict//EN',
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
								 'href' => theme_path('display.css'),
								 'media' => 'screen, projection, tv'));
	foreach (array(6,7) as $ver) {
		if (file_exists(theme_file('ie'.$ver.'.css'))) {
			# Yes, IE people should be put in jail.
			$xw->writeComment('[if lte IE '.$ver.']><link rel="stylesheet" type="text/css" '.
							  'href="'.theme_path('ie'.$ver.'.css').' /><![endif]');
		}
	}
	if ($callable) {
		if ($data) {
			call_user_func($callable, $data);
		} else {
			call_user_func($callable);
		}
	}
	common_element_end('head');
	common_element_start('body');
	common_element_start('div', array('id' => 'wrap'));
	common_element_start('div', array('id' => 'header'));
	common_nav_menu();
	common_element_start('a', array('href' => common_local_url('public')));
	common_element('img', array('src' => ($config['site']['logo']) ? 
								($config['site']['logo']) : theme_path('logo.png'),
								'alt' => $config['site']['name'],
								'id' => 'logo'));
	common_element_end('a');
	common_element_end('div');
	common_element_start('div', array('id' => 'content'));
}

function common_show_footer() {
	global $xw, $config;
	common_element_end('div'); # content div
	common_foot_menu();
	common_element_start('div', array('id' => 'footer'));
	common_element('img', array('id' => 'cc',
								'src' => $config['license']['image'],
								'alt' => $config['license']['title']));
	common_element_start('p');
	common_text(_t('Unless otherwise specified, contents of this site are copyright by the contributors and available under the '));
	common_element('a', array('class' => 'license',
							  'rel' => 'license',
							  href => $config['license']['url']),
				   $config['license']['title']);
	common_text(_t('. Contributors should be attributed by full name or nickname.'));
	common_element_end('p');
	common_element_end('div');
	common_element_end('div');
	common_element_end('body');
	common_element_end('html');
	common_end_xml();
}

function common_text($txt) {
	global $xw;
	$xw->text($txt);
}

function common_raw($xml) {
	global $xw;
	$xw->writeRaw($xml);
}

function common_nav_menu() {
	$user = common_current_user();
	common_element_start('ul', array('id' => 'nav'));
	if ($user) {
		common_menu_item(common_local_url('all', array('nickname' => $user->nickname)),
						 _t('Home'));
	}
	common_menu_item(common_local_url('public'), _t('Public'));
	common_menu_item(common_local_url('doc', array('title' => 'help')),
					 _t('Help'));
	if ($user) {
		common_menu_item(common_local_url('profilesettings'),
						 _t('Settings'));
		common_menu_item(common_local_url('logout'),
						 _t('Logout'));
	} else {
		common_menu_item(common_local_url('login'), _t('Login'));
		common_menu_item(common_local_url('register'), _t('Register'));
	}
	common_element_end('ul');
}

function common_foot_menu() {
	common_element_start('ul', array('id' => 'nav_sub'));
	common_menu_item(common_local_url('doc', array('title' => 'about')),
					 _t('About'));
	common_menu_item(common_local_url('doc', array('title' => 'privacy')),
					 _t('Privacy'));
	common_menu_item(common_local_url('doc', array('title' => 'source')),
					 _t('Source'));
	common_element_end('ul');
}

function common_menu_item($url, $text, $title=NULL, $is_selected=false) {
	$lattrs = array();
	if ($is_selected) {
		$lattrs['class'] = 'current';
	}
	common_element_start('li', $lattrs);
	$attrs['href'] = $url;
	if ($title) {
		$attrs['title'] = $title;
	}
	common_element('a', $attrs, $text);
	common_element_end('li');
}

function common_input($id, $label, $value=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'text',
				   'id' => $id);
	if ($value) {
		$attrs['value'] = htmlspecialchars($value);
	}
	common_element('input', $attrs);
	common_element_end('p');
}

function common_hidden($id, $value) {
	common_element('input', array('name' => $id,
								  'type' => 'hidden',
								  'id' => $id,
								  'value' => $value));
}

function common_password($id, $label) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'password',
				   'id' => $id);
	common_element('input', $attrs);
	common_element_end('p');
}

function common_submit($id, $label) {
	global $xw;
	common_element_start('p');
	common_element('input', array('type' => 'submit',
								  'id' => $id,
								  'name' => $id,
								  'value' => $label));
	common_element_end('p');
}

function common_textarea($id, $label, $content=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	common_element('textarea', array('rows' => 3,
									 'cols' => 40,
									 'name' => $id,
									 'id' => $id),
				   ($content) ? $content : ' ');
	common_element_end('p');
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
	return strtolower($nickname);
}

# get canonical version of email for comparison
function common_canonical_email($email) {
	# XXX: canonicalize UTF-8
	# XXX: lcase the domain part
	return $email;
}

define('URL_REGEX', '^|[ \t\r\n])((ftp|http|https|gopher|mailto|news|nntp|telnet|wais|file|prospero|aim|webcal):(([A-Za-z0-9$_.+!*(),;/?:@&~=-])|%[A-Fa-f0-9]{2}){2,}(#([a-zA-Z0-9][a-zA-Z0-9$_.+!*(),;/?:@&~=%-]*))?([A-Za-z0-9$_+!*();/?:~-]))');

function common_render_content($text, $notice) {
	$r = htmlspecialchars($text);
	$id = $notice->profile_id;
	$r = preg_replace('@https?://\S+@', '<a href="\0" class="extlink">\0</a>', $r);
	$r = preg_replace('/(^|\b)@([\w-]+)($|\b)/e', "'\\1@'.common_at_link($id, '\\2').'\\3'", $r);
	# XXX: # tags
	# XXX: machine tags
	return $r;
}

function common_at_link($sender_id, $nickname) {
	# Try to find profiles this profile is subscribed to that have this nickname
	$recipient = new Profile();
	# XXX: chokety and bad
	$recipient->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$sender_id.' and subscribed = id)', 'AND');
	$recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
	if ($recipient->find(TRUE)) {
		return '<a href="'.htmlspecialchars($recipient->profileurl).'" class="atlink tolistenee">'.$nickname.'</a>';
	}
	# Try to find profiles that listen to this profile and that have this nickname
	$recipient = new Profile();
	# XXX: chokety and bad
	$recipient->whereAdd('EXISTS (SELECT subscriber from subscription where subscribed = '.$sender_id.' and subscriber = id)', 'AND');
	$recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
	if ($recipient->find(TRUE)) {
		return '<a href="'.htmlspecialchars($recipient->profileurl).'" class="atlink tolistener">'.$nickname.'</a>';
	}
	# If this is a local user, try to find a local user with that nickname.
	$sender = User::staticGet($sender_id);
	if ($sender) {
		$recipient_user = User::staticGet('nickname', $nickname);
		if ($recipient_user) {
			$recipient = $recipient->getProfile();
			return '<a href="'.htmlspecialchars($recipient->profileurl).'" class="atlink usertouser">'.$nickname.'</a>';
		}
	}
	# Otherwise, no links. @messages from local users to remote users,
	# or from remote users to other remote users, are just
	# outside our ability to make intelligent guesses about
	return $nickname;
}

// where should the avatar go for this user?

function common_avatar_filename($id, $extension, $size=NULL, $extra=NULL) {
	global $config;

	if ($size) {
		return $id . '-' . $size . (($extra) ? ('-' . $extra) : '') . $extension;
	} else {
		return $id . '-original' . (($extra) ? ('-' . $extra) : '') . $extension;
	}
}

function common_avatar_path($filename) {
	global $config;
	return INSTALLDIR . '/avatar/' . $filename;
}

function common_avatar_url($filename) {
	return common_path('avatar/'.$filename);
}

function common_default_avatar($size) {
	static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
							  AVATAR_STREAM_SIZE => 'stream',
							  AVATAR_MINI_SIZE => 'mini');
	global $config;

	return common_path($config['avatar']['default'][$sizenames[$size]]);
}

function common_local_url($action, $args=NULL) {
	global $config;
	if ($config['site']['fancy']) {
		return common_fancy_url($action, $args);
	} else {
		return common_simple_url($action, $args);
	}
}

function common_fancy_url($action, $args=NULL) {
	switch (strtolower($action)) {
	 case 'public':
		return common_path('');
	 case 'publicrss':
		return common_path('rss');
	 case 'doc':
		return common_path('doc/'.$args['title']);
	 case 'login':
	 case 'logout':
	 case 'register':
	 case 'subscribe':
	 case 'unsubscribe':
		return common_path('main/'.$action);
	 case 'avatar':
	 case 'password':
		return common_path('settings/'.$action);
	 case 'profilesettings':
		return common_path('settings/profile');
	 case 'newnotice':
		return common_path('notice/new');
	 case 'shownotice':
		return common_path('notice/'.$args['notice']);
	 case 'subscriptions':
	 case 'subscribed':
	 case 'xrds':		
	 case 'all':
	 case 'foaf':
		return common_path($args['nickname'].'/'.$action);
	 case 'allrss':
		return common_path($args['nickname'].'/all/rss');
	 case 'userrss':
		return common_path($args['nickname'].'/rss');
	 case 'showstream':
		return common_path($args['nickname']);
	 default:
		return common_simple_url($action, $args);
	}
}

function common_simple_url($action, $args=NULL) {
	global $config;
	/* XXX: pretty URLs */
	$extra = '';
	if ($args) {
		foreach ($args as $key => $value) {
			$extra .= "&${key}=${value}";
		}
	}
	return common_path("index.php?action=${action}${extra}");
}

function common_path($relative) {
	global $config;
	$pathpart = ($config['site']['path']) ? $config['site']['path']."/" : '';
	return "http://".$config['site']['server'].'/'.$pathpart.$relative;
}

function common_date_string($dt) {
	// XXX: do some sexy date formatting
	// return date(DATE_RFC822, $dt);
	return $dt;
}

function common_date_w3dtf($dt) {
	$t = strtotime($dt);
	return date(DATE_W3C, $t);
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

function common_broadcast_notice($notice, $remote=false) {
	// XXX: optionally use a queue system like http://code.google.com/p/microapps/wiki/NQDQ
	if (!$remote) {
		# Make sure we have the OMB stuff
		require_once(INSTALLDIR.'/lib/omb.php');
		omb_broadcast_remote_subscribers($notice);
	}
	// XXX: broadcast notices to Jabber
	// XXX: broadcast notices to SMS
	// XXX: broadcast notices to other IM
	return true;
}


function common_profile_url($nickname) {
	return common_local_url('showstream', array('nickname' => $nickname));
}

# Don't call if nobody's logged in

function common_notice_form() {
	$user = common_current_user();
	assert(!is_null($user));
	common_element_start('form', array('id' => 'status_form',
									   'method' => 'POST',
									   'action' => common_local_url('newnotice')));
	common_element_start('p');
	common_element('label', array('for' => 'status_update',
								  'id' => 'status_label'),
				   _t('What\'s up, ').$user->nickname.'?');
	common_element('textarea', array('id' => 'status_textarea',
									 'name' => 'status_textarea'));
	common_element('input', array('id' => 'status_submit',
								  'name' => 'status_submit',
								  'type' => 'submit',
								  'value' => _t('Send')));
	common_element_end('p');
	common_element_end('form');
}

function common_mint_tag($extra) {
	global $config;
	return
	  'tag:'.$config['tag']['authority'].','.
	  $config['tag']['date'].':'.$config['tag']['prefix'].$extra;
}

# Should make up a reasonable root URL

function common_root_url() {
	return common_path('');
}

# returns $bytes bytes of random data as a hexadecimal string
# "good" here is a goal and not a guarantee

function common_good_rand($bytes) {
	# XXX: use random.org...?
	if (file_exists('/dev/urandom')) {
		return common_urandom($bytes);
	} else { # FIXME: this is probably not good enough
		return common_mtrand($bytes);
	}
}

function common_urandom($bytes) {
	$h = fopen('/dev/urandom', 'rb');
	# should not block
	$src = fread($h, $bytes);
	fclose($h);
	$enc = '';
	for ($i = 0; $i < $bytes; $i++) {
		$enc .= sprintf("%02x", (ord($src[$i])));
	}
	return $enc;
}

function common_mtrand($bytes) {
	$enc = '';
	for ($i = 0; $i < $bytes; $i++) {
		$enc .= sprintf("%02x", mt_rand(0, 255));
	}
	return $enc;
}

function common_set_returnto($url) {
	common_ensure_session();
	$_SESSION['returnto'] = $url;
}

function common_get_returnto() {
	common_ensure_session();
	return $_SESSION['returnto'];
}

function common_timestamp() {
	return date('YmdHis');
}

// XXX: set up gettext

function _t($str) {
	return $str;
}

function common_ensure_syslog() {
	static $initialized = false;
	if (!$initialized) {
		global $config;
		define_syslog_variables();
		openlog($config['syslog']['appname'], 0, LOG_USER);
		$initialized = true;
	}
}

function common_log($priority, $msg, $filename=NULL) {
	common_ensure_syslog();
	syslog($priority, $msg);
}

function common_debug($msg, $filename=NULL) {
	if ($filename) {
		common_log(LOG_DEBUG, basename($filename).' - '.$msg);
	} else {
		common_log(LOG_DEBUG, $msg);
	}
}

function common_valid_http_url($url) {
	return Validate::uri($url, array('allowed_schemes' => array('http', 'https')));
}

function common_valid_tag($tag) {
	if (preg_match('/^tag:(.*?),(\d{4}(-\d{2}(-\d{2})?)?):(.*)$/', $tag, $matches)) {
		return (Validate::email($matches[1]) ||
				preg_match('/^([\w-\.]+)$/', $matches[1]));
	}
	return false;
}

# Does a little before-after block for next/prev page

function common_pagination($have_before, $have_after, $page, $action, $args=NULL) {		
	
	if ($have_before || $have_after) {
		common_element_start('div', array('id' => 'pagination'));
		common_element_start('ul', array('id' => 'nav_pagination'));
	}
	
	if ($have_before) {
		$pargs = array('page' => $page-1);
		$newargs = ($args) ? array_merge($args,$pargs) : $pargs;
										 
		common_element_start('li', 'before');
		common_element('a', array('href' => common_local_url($action, $newargs)),
					   _t('« Before'));
		common_element_end('li');
	}

	if ($have_after) {
		$pargs = array('page' => $page+1);
		$newargs = ($args) ? array_merge($args,$pargs) : $pargs;
		common_element_start('li', 'after');
		common_element('a', array('href' => common_local_url($action, $newargs)),
						   _t('After »'));
		common_element_end('li');
	}
	
	if ($have_before || $have_after) {
		common_element_end('ul');
		common_element_end('div');
	}
}
