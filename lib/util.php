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
	static $empty_tag = array('base', 'meta', 'link', 'hr',
							  'br', 'param', 'img', 'area',
							  'input', 'col');
	global $xw;
	# XXX: check namespace
	if (in_array($tag, $empty_tag)) {
		$xw->endElement();
	} else {
		$xw->fullEndElement();
	}
}

function common_element($tag, $attrs=NULL, $content=NULL) {
	common_element_start($tag, $attrs);
	global $xw;
	if (!is_null($content)) {
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

function common_init_language() {
	mb_internal_encoding('UTF-8');
	$language = common_language();
	# So we don't have to make people install the gettext locales
	putenv('LANGUAGE='.$language);
	putenv('LANG='.$language);
	$locale_set = setlocale(LC_ALL, $language . ".utf8",
							$language . ".UTF8",
							$language . ".utf-8",
							$language . ".UTF-8",
							$language);
	bindtextdomain("laconica", common_config('site','locale_path'));
	bind_textdomain_codeset("laconica", "UTF-8");
	textdomain("laconica");
	setlocale(LC_CTYPE, 'C');
}

define('PAGE_TYPE_PREFS', 'text/html,application/xhtml+xml,application/xml;q=0.3,text/xml;q=0.2');

function common_show_header($pagetitle, $callable=NULL, $data=NULL, $headercall=NULL) {
	global $config, $xw;

	$httpaccept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : NULL;

	# XXX: allow content negotiation for RDF, RSS, or XRDS

	$type = common_negotiate_type(common_accept_to_prefs($httpaccept),
								  common_accept_to_prefs(PAGE_TYPE_PREFS));

	if (!$type) {
		common_user_error(_('This page is not available in a media type you accept'), 406);
		exit(0);
	}

	header('Content-Type: '.$type);

	common_start_xml('html',
					 '-//W3C//DTD XHTML 1.0 Strict//EN',
					 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');

	# FIXME: correct language for interface

	$language = common_language();

	common_element_start('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
									   'xml:lang' => $language,
									   'lang' => $language));

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
							  'href="'.theme_path('ie'.$ver.'.css').'" /><![endif]');
		}
	}

	common_element('script', array('type' => 'text/javascript',
								   'src' => common_path('js/jquery.min.js')),
				   ' ');
	common_element('script', array('type' => 'text/javascript',
								   'src' => common_path('js/jquery.form.js')),
				   ' ');
	common_element('script', array('type' => 'text/javascript',
								   'src' => common_path('js/util.js')),
				   ' ');
	common_element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                        'href' =>  common_local_url('opensearch', array('type' => 'people')),
                                        'title' => common_config('site', 'name').' People Search'));

	common_element('link', array('rel' => 'search', 'type' => 'application/opensearchdescription+xml',
                                        'href' =>  common_local_url('opensearch', array('type' => 'notice')),
                                        'title' => common_config('site', 'name').' Notice Search'));

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
	if ((isset($config['site']['logo']) && is_string($config['site']['logo']) && (strlen($config['site']['logo']) > 0))
		|| file_exists(theme_file('logo.png')))
	{
		common_element_start('a', array('href' => common_local_url('public')));
		common_element('img', array('src' => isset($config['site']['logo']) ?
									($config['site']['logo']) : theme_path('logo.png'),
									'alt' => $config['site']['name'],
									'id' => 'logo'));
		common_element_end('a');
	} else {
		common_element_start('p', array('id' => 'branding'));
		common_element('a', array('href' => common_local_url('public')),
					   $config['site']['name']);
		common_element_end('p');
	}

	common_element('h1', 'pagetitle', $pagetitle);

	if ($headercall) {
		if ($data) {
			call_user_func($headercall, $data);
		} else {
			call_user_func($headercall);
		}
	}
	common_element_end('div');
	common_element_start('div', array('id' => 'content'));
}

function common_show_footer() {
	global $xw, $config;
	common_element_end('div'); # content div
	common_foot_menu();
	common_element_start('div', array('id' => 'footer'));
	common_element_start('div', 'laconica');
	if (common_config('site', 'broughtby')) {
		$instr = _('**%%site.name%%** is a microblogging service brought to you by [%%site.broughtby%%](%%site.broughtbyurl%%). ');
	} else {
		$instr = _('**%%site.name%%** is a microblogging service. ');
	}
	$instr .= sprintf(_('It runs the [Laconica](http://laconi.ca/) microblogging software, version %s, available under the [GNU Affero General Public License](http://www.fsf.org/licensing/licenses/agpl-3.0.html).'), LACONICA_VERSION);
    $output = common_markup_to_html($instr);
    common_raw($output);
	common_element_end('div');
	common_element('img', array('id' => 'cc',
								'src' => $config['license']['image'],
								'alt' => $config['license']['title']));
	common_element_start('p');
	common_text(_('Unless otherwise specified, contents of this site are copyright by the contributors and available under the '));
	common_element('a', array('class' => 'license',
							  'rel' => 'license',
							  'href' => $config['license']['url']),
				   $config['license']['title']);
	common_text(_('. Contributors should be attributed by full name or nickname.'));
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
						 _('Home'));
	}
	common_menu_item(common_local_url('peoplesearch'), _('Search'));
	common_menu_item(common_local_url('tags'), _('Tags'));
	if ($user) {
		common_menu_item(common_local_url('profilesettings'),
						 _('Settings'));
		common_menu_item(common_local_url('invite'),
						 _('Invite'));
		common_menu_item(common_local_url('logout'),
						 _('Logout'));
	} else {
		common_menu_item(common_local_url('login'), _('Login'));
		if (!common_config('site', 'closed')) {
			common_menu_item(common_local_url('register'), _('Register'));
		}
		common_menu_item(common_local_url('openidlogin'), _('OpenID'));
	}
	common_element_end('ul');
}

function common_foot_menu() {
	common_element_start('ul', array('id' => 'nav_sub'));
	common_menu_item(common_local_url('doc', array('title' => 'help')),
					 _('Help'));
	common_menu_item(common_local_url('doc', array('title' => 'about')),
					 _('About'));
	common_menu_item(common_local_url('doc', array('title' => 'faq')),
					 _('FAQ'));
	common_menu_item(common_local_url('doc', array('title' => 'privacy')),
					 _('Privacy'));
	common_menu_item(common_local_url('doc', array('title' => 'source')),
					 _('Source'));
	common_menu_item(common_local_url('doc', array('title' => 'contact')),
					 _('Contact'));
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

function common_input($id, $label, $value=NULL,$instructions=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'text',
				   'class' => 'input_text',
				   'id' => $id);
	if ($value) {
		$attrs['value'] = htmlspecialchars($value);
	}
	common_element('input', $attrs);
	if ($instructions) {
		common_element('span', 'input_instructions', $instructions);
	}
	common_element_end('p');
}

function common_checkbox($id, $label, $checked=false, $instructions=NULL, $value='true')
{
	common_element_start('p');
	$attrs = array('name' => $id,
				   'type' => 'checkbox',
				   'class' => 'checkbox',
				   'id' => $id);
	if ($value) {
		$attrs['value'] = htmlspecialchars($value);
	}
	if ($checked) {
		$attrs['checked'] = 'checked';
	}
	common_element('input', $attrs);
	# XXX: use a <label>
	common_text(' ');
	common_element('span', 'checkbox_label', $label);
	common_text(' ');
	if ($instructions) {
		common_element('span', 'input_instructions', $instructions);
	}
	common_element_end('p');
}

function common_dropdown($id, $label, $content, $instructions=NULL, $blank_select=FALSE, $selected=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	common_element_start('select', array('id' => $id, 'name' => $id));
	if ($blank_select) {
		common_element('option', array('value' => ''));
	}
	foreach ($content as $value => $option) {
		if ($value == $selected) {
			common_element('option', array('value' => $value, 'selected' => $value), $option);
		} else {
			common_element('option', array('value' => $value), $option);
		}
	}
	common_element_end('select');
	if ($instructions) {
		common_element('span', 'input_instructions', $instructions);
	}
	common_element_end('p');
}
function common_hidden($id, $value) {
	common_element('input', array('name' => $id,
								  'type' => 'hidden',
								  'id' => $id,
								  'value' => $value));
}

function common_password($id, $label, $instructions=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	$attrs = array('name' => $id,
				   'type' => 'password',
				   'class' => 'password',
				   'id' => $id);
	common_element('input', $attrs);
	if ($instructions) {
		common_element('span', 'input_instructions', $instructions);
	}
	common_element_end('p');
}

function common_submit($id, $label, $cls='submit') {
	global $xw;
	common_element_start('p');
	common_element('input', array('type' => 'submit',
								  'id' => $id,
								  'name' => $id,
								  'class' => $cls,
								  'value' => $label));
	common_element_end('p');
}

function common_textarea($id, $label, $content=NULL, $instructions=NULL) {
	common_element_start('p');
	common_element('label', array('for' => $id), $label);
	common_element('textarea', array('rows' => 3,
									 'cols' => 40,
									 'name' => $id,
									 'id' => $id),
				   ($content) ? $content : '');
	if ($instructions) {
		common_element('span', 'input_instructions', $instructions);
	}
	common_element_end('p');
}

function common_timezone() {
	if (common_logged_in()) {
		$user = common_current_user();
		if ($user->timezone) {
			return $user->timezone;
		}
	}

	global $config;
	return $config['site']['timezone'];
}

function common_language() {
	$httplang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : NULL;
        $language = array();
        $user_language = FALSE;

        if (common_logged_in()) {
                $user = common_current_user();
                $user_language = $user->language;
        }

        if ($user_language) {
                return $user_language;
        } else if (!empty($httplang)) {
                $language = client_prefered_language($httplang);
                if ($language) {
                    return $language;
                }
        } else {
                return common_config('site', 'language');
        }
}
# salted, hashed passwords are stored in the DB

function common_munge_password($password, $id) {
	return md5($password . $id);
}

# check if a username exists and has matching password
function common_check_user($nickname, $password) {
	# NEVER allow blank passwords, even if they match the DB
	if (mb_strlen($password) == 0) {
		return false;
	}
	$user = User::staticGet('nickname', $nickname);
	if (is_null($user)) {
		return false;
	} else {
		if (0 == strcmp(common_munge_password($password, $user->id),
						$user->password)) {
			return $user;
		} else {
			return false;
		}
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

# Three kinds of arguments:
# 1) a user object
# 2) a nickname
# 3) NULL to clear

function common_set_user($user) {
	if (is_null($user) && common_have_session()) {
		unset($_SESSION['userid']);
		return true;
	} else if (is_string($user)) {
		$nickname = $user;
		$user = User::staticGet('nickname', $nickname);
	} else if (!($user instanceof User)) {
		return false;
	}

	if ($user) {
		common_ensure_session();
		$_SESSION['userid'] = $user->id;
		return $user;
	}
	return false;
}

function common_set_cookie($key, $value, $expiration=0) {
	$path = common_config('site', 'path');
	$server = common_config('site', 'server');

	if ($path && ($path != '/')) {
		$cookiepath = '/' . $path . '/';
	} else {
		$cookiepath = '/';
	}
	return setcookie($key,
	                 $value,
	          		 $expiration,
			  		 $cookiepath,
			  	     $server);
}

define('REMEMBERME', 'rememberme');
define('REMEMBERME_EXPIRY', 30 * 24 * 60 * 60);

function common_rememberme($user=NULL) {
	if (!$user) {
		$user = common_current_user();
		if (!$user) {
			common_debug('No current user to remember', __FILE__);
			return false;
		}
	}
	$rm = new Remember_me();
	$rm->code = common_good_rand(16);
	$rm->user_id = $user->id;
	$result = $rm->insert();
	if (!$result) {
		common_log_db_error($rm, 'INSERT', __FILE__);
		common_debug('Error adding rememberme record for ' . $user->nickname, __FILE__);
		return false;
	}
	common_log(LOG_INFO, 'adding rememberme cookie for ' . $user->nickname);
	common_set_cookie(REMEMBERME,
					  implode(':', array($rm->user_id, $rm->code)),
					  time() + REMEMBERME_EXPIRY);
	return true;
}

function common_remembered_user() {
	$user = NULL;
	# Try to remember
	$packed = isset($_COOKIE[REMEMBERME]) ? $_COOKIE[REMEMBERME] : '';
	if ($packed) {
		list($id, $code) = explode(':', $packed);
		if ($id && $code) {
			$rm = Remember_me::staticGet($code);
			if ($rm && ($rm->user_id == $id)) {
				$user = User::staticGet($rm->user_id);
				if ($user) {
					# successful!
					$result = $rm->delete();
					if (!$result) {
						common_log_db_error($rm, 'DELETE', __FILE__);
						$user = NULL;
					} else {
						common_log(LOG_INFO, 'logging in ' . $user->nickname . ' using rememberme code ' . $rm->code);
						common_set_user($user->nickname);
						common_real_login(false);
						# We issue a new cookie, so they can log in
						# automatically again after this session
						common_rememberme($user);
					}
				}
			}
		}
	}
	return $user;
}

# must be called with a valid user!

function common_forgetme() {
	common_set_cookie(REMEMBERME, '', 0);
}

# who is the current user?
function common_current_user() {
	if (isset($_REQUEST[session_name()]) || (isset($_SESSION['userid']) && $_SESSION['userid'])) {
		common_ensure_session();
		$id = isset($_SESSION['userid']) ? $_SESSION['userid'] : false;
		if ($id) {
			# note: this should cache
			$user = User::staticGet($id);
			return $user;
		}
	}
	# that didn't work; try to remember
	$user = common_remembered_user();
	if ($user) {
		common_debug("Got User " . $user->nickname);
	    common_debug("Faking session on remembered user");
	    $_SESSION['userid'] = $user->id;
	}
	return $user;
}

# Logins that are 'remembered' aren't 'real' -- they're subject to
# cookie-stealing. So, we don't let them do certain things. New reg,
# OpenID, and password logins _are_ real.

function common_real_login($real=true) {
	common_ensure_session();
	$_SESSION['real_login'] = $real;
}

function common_is_real_login() {
	return common_logged_in() && $_SESSION['real_login'];
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

	$r = preg_replace('/[\x{0}-\x{8}\x{b}-\x{c}\x{e}-\x{19}]/', '', $r);
	$id = $notice->profile_id;
	$r = preg_replace('@https?://[^)\]>\s]+@', '<a href="\0" class="extlink">\0</a>', $r);
	$r = preg_replace('/(^|\s+)@([A-Za-z0-9]{1,64})/e', "'\\1@'.common_at_link($id, '\\2')", $r);
	$r = preg_replace('/^T ([A-Z0-9]{1,64}) /e', "'T '.common_at_link($id, '\\1').' '", $r);
	$r = preg_replace('/(^|\s+)#([A-Za-z0-9_\-\.]{1,64})/e', "'\\1#'.common_tag_link('\\2')", $r);
	# XXX: machine tags
	return $r;
}

function common_tag_link($tag) {
	if(common_config('site', 'fancy')) {
		return '<a href="' . htmlspecialchars(common_path('tag/' . strtolower(str_replace(array('-', '_', '.'), '', $tag)))) . '" rel="tag" class="hashlink">' . htmlspecialchars($tag) . '</a>';
	} else {
		return '<a href="' . htmlspecialchars(common_path('index.php?action=tag&tag=' . strtolower(str_replace(array('-', '_', '.'), '', $tag)))) . '" rel="tag" class="hashlink">' . htmlspecialchars($tag) . '</a>';
	}
}

function common_at_link($sender_id, $nickname) {
	$sender = Profile::staticGet($sender_id);
	$recipient = common_relative_profile($sender, common_canonical_nickname($nickname));
	if ($recipient) {
		return '<a href="'.htmlspecialchars($recipient->profileurl).'" class="atlink">'.$nickname.'</a>';
	} else {
		return $nickname;
	}
}

function common_relative_profile($sender, $nickname, $dt=NULL) {
	# Try to find profiles this profile is subscribed to that have this nickname
	$recipient = new Profile();
	# XXX: use a join instead of a subquery
	$recipient->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$sender->id.' and subscribed = id)', 'AND');
	$recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
	if ($recipient->find(TRUE)) {
		# XXX: should probably differentiate between profiles with
		# the same name by date of most recent update
		return $recipient;
	}
	# Try to find profiles that listen to this profile and that have this nickname
	$recipient = new Profile();
	# XXX: use a join instead of a subquery
	$recipient->whereAdd('EXISTS (SELECT subscriber from subscription where subscribed = '.$sender->id.' and subscriber = id)', 'AND');
	$recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
	if ($recipient->find(TRUE)) {
		# XXX: should probably differentiate between profiles with
		# the same name by date of most recent update
		return $recipient;
	}
	# If this is a local user, try to find a local user with that nickname.
	$sender = User::staticGet($sender->id);
	if ($sender) {
		$recipient_user = User::staticGet('nickname', $nickname);
		if ($recipient_user) {
			return $recipient_user->getProfile();
		}
	}
	# Otherwise, no links. @messages from local users to remote users,
	# or from remote users to other remote users, are just
	# outside our ability to make intelligent guesses about
	return NULL;
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

function common_avatar_display_url($avatar) {
	$server = common_config('avatar', 'server');
	if ($server) {
		return 'http://'.$server.'/'.$avatar->filename;
	} else {
		return $avatar->url;
	}
}

function common_default_avatar($size) {
	static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
							  AVATAR_STREAM_SIZE => 'stream',
							  AVATAR_MINI_SIZE => 'mini');
	return theme_path('default-avatar-'.$sizenames[$size].'.png');
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
		if ($args && isset($args['page'])) {
			return common_path('?page=' . $args['page']);
		} else {
			return common_path('');
		}
	 case 'publicrss':
		return common_path('rss');
	 case 'publicxrds':
		return common_path('xrds');
	 case 'opensearch':
                if ($args && $args['type']) {
                        return common_path('opensearch/'.$args['type']);
                } else {
                        return common_path('opensearch/people');
                }
	 case 'doc':
		return common_path('doc/'.$args['title']);
	 case 'login':
	 case 'logout':
	 case 'subscribe':
	 case 'unsubscribe':
	 case 'invite':
		return common_path('main/'.$action);
	 case 'register':
		if ($args && $args['code']) {
			return common_path('main/register/'.$args['code']);
		} else {
			return common_path('main/register');
		}
	 case 'remotesubscribe':
		if ($args && $args['nickname']) {
			return common_path('main/remote?nickname=' . $args['nickname']);
		} else {
			return common_path('main/remote');
		}
	 case 'openidlogin':
		return common_path('main/openid');
	 case 'profilesettings':
		return common_path('settings/profile');
	 case 'emailsettings':
		return common_path('settings/email');
	 case 'openidsettings':
		return common_path('settings/openid');
	 case 'smssettings':
		return common_path('settings/sms');
	 case 'twittersettings':
		return common_path('settings/twitter');
	 case 'newnotice':
		if ($args && $args['replyto']) {
			return common_path('notice/new?replyto='.$args['replyto']);
		} else {
			return common_path('notice/new');
		}
	 case 'shownotice':
		return common_path('notice/'.$args['notice']);
	 case 'deletenotice':
                if ($args && $args['notice']) {
                        return common_path('notice/delete/'.$args['notice']);
                } else {
                        return common_path('notice/delete');
                }
	 case 'xrds':
	 case 'foaf':
		return common_path($args['nickname'].'/'.$action);
	 case 'subscriptions':
	 case 'subscribers':
	 case 'all':
	 case 'replies':
	 case 'inbox':
	 case 'outbox':
		if ($args && isset($args['page'])) {
			return common_path($args['nickname'].'/'.$action.'?page=' . $args['page']);
		} else {
			return common_path($args['nickname'].'/'.$action);
		}
	 case 'allrss':
		return common_path($args['nickname'].'/all/rss');
	 case 'repliesrss':
		return common_path($args['nickname'].'/replies/rss');
	 case 'userrss':
		return common_path($args['nickname'].'/rss');
	 case 'showstream':
		if ($args && isset($args['page'])) {
			return common_path($args['nickname'].'?page=' . $args['page']);
		} else {
			return common_path($args['nickname']);
		}
	 case 'confirmaddress':
		return common_path('main/confirmaddress/'.$args['code']);
	 case 'userbyid':
	 	return common_path('user/'.$args['id']);
	 case 'recoverpassword':
	    $path = 'main/recoverpassword';
	    if ($args['code']) {
	    	$path .= '/' . $args['code'];
		}
	    return common_path($path);
	 case 'imsettings':
	 	return common_path('settings/im');
	 case 'peoplesearch':
		return common_path('search/people' . (($args) ? ('?' . http_build_query($args)) : ''));
	 case 'noticesearch':
		return common_path('search/notice' . (($args) ? ('?' . http_build_query($args)) : ''));
	 case 'noticesearchrss':
		return common_path('search/notice/rss' . (($args) ? ('?' . http_build_query($args)) : ''));
	 case 'avatarbynickname':
		return common_path($args['nickname'].'/avatar/'.$args['size']);
	 case 'tag':
	    if (isset($args['tag']) && $args['tag']) {
	    		$path = 'tag/' . $args['tag'];
			unset($args['tag']);
		} else {
			$path = 'tags';
		}
		return common_path($path . (($args) ? ('?' . http_build_query($args)) : ''));
	 case 'tags':
		return common_path('tags' . (($args) ? ('?' . http_build_query($args)) : ''));
	 case 'favor':
		return common_path('main/favor');
	 case 'disfavor':
		return common_path('main/disfavor');
	 case 'showfavorites':
		if ($args && isset($args['page'])) {
			return common_path($args['nickname'].'/favorites?page=' . $args['page']);
		} else {
			return common_path($args['nickname'].'/favorites');
		}
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
	$t = strtotime($dt);
	$now = time();
	$diff = $now - $t;

	if ($now < $t) { # that shouldn't happen!
		return common_exact_date($dt);
	} else if ($diff < 60) {
		return _('a few seconds ago');
	} else if ($diff < 92) {
		return _('about a minute ago');
	} else if ($diff < 3300) {
		return sprintf(_('about %d minutes ago'), round($diff/60));
	} else if ($diff < 5400) {
		return _('about an hour ago');
	} else if ($diff < 22 * 3600) {
		return sprintf(_('about %d hours ago'), round($diff/3600));
	} else if ($diff < 37 * 3600) {
		return _('about a day ago');
	} else if ($diff < 24 * 24 * 3600) {
		return sprintf(_('about %d days ago'), round($diff/(24*3600)));
	} else if ($diff < 46 * 24 * 3600) {
		return _('about a month ago');
	} else if ($diff < 330 * 24 * 3600) {
		return sprintf(_('about %d months ago'), round($diff/(30*24*3600)));
	} else if ($diff < 480 * 24 * 3600) {
		return _('about a year ago');
	} else {
		return common_exact_date($dt);
	}
}

function common_exact_date($dt) {
    static $_utc;
    static $_siteTz;

    if (!$_utc) {
        $_utc = new DateTimeZone('UTC');
        $_siteTz = new DateTimeZone(common_timezone());
    }

	$dateStr = date('d F Y H:i:s', strtotime($dt));
	$d = new DateTime($dateStr, $_utc);
	$d->setTimezone($_siteTz);
	return $d->format(DATE_RFC850);
}

function common_date_w3dtf($dt) {
	$dateStr = date('d F Y H:i:s', strtotime($dt));
	$d = new DateTime($dateStr, new DateTimeZone('UTC'));
	$d->setTimezone(new DateTimeZone(common_timezone()));
	return $d->format(DATE_W3C);
}

function common_date_rfc2822($dt) {
	$dateStr = date('d F Y H:i:s', strtotime($dt));
	$d = new DateTime($dateStr, new DateTimeZone('UTC'));
	$d->setTimezone(new DateTimeZone(common_timezone()));
	return $d->format('r');
}

function common_date_iso8601($dt) {
	$dateStr = date('d F Y H:i:s', strtotime($dt));
	$d = new DateTime($dateStr, new DateTimeZone('UTC'));
	$d->setTimezone(new DateTimeZone(common_timezone()));
	return $d->format('c');
}

function common_sql_now() {
	return strftime('%Y-%m-%d %H:%M:%S', time());
}

function common_redirect($url, $code=307) {
	static $status = array(301 => "Moved Permanently",
						   302 => "Found",
						   303 => "See Other",
						   307 => "Temporary Redirect");
	header("Status: ${code} $status[$code]");
	header("Location: $url");

	common_start_xml('a',
					 '-//W3C//DTD XHTML 1.0 Strict//EN',
					 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');
	common_element('a', array('href' => $url), $url);
	common_end_xml();
    exit;
}

function common_save_replies($notice) {
	# Alternative reply format
	$tname = false;
	if (preg_match('/^T ([A-Z0-9]{1,64}) /', $notice->content, $match)) {
		$tname = $match[1];
	}
	# extract all @messages
	$cnt = preg_match_all('/(?:^|\s)@([a-z0-9]{1,64})/', $notice->content, $match);
	if (!$cnt && !$tname) {
		return true;
	}
	# XXX: is there another way to make an array copy?
	$names = ($tname) ? array_unique(array_merge(array(strtolower($tname)), $match[1])) : array_unique($match[1]);
	$sender = Profile::staticGet($notice->profile_id);
	# store replied only for first @ (what user/notice what the reply directed,
	# we assume first @ is it)
	for ($i=0; $i<count($names); $i++) {
		$nickname = $names[$i];
		$recipient = common_relative_profile($sender, $nickname, $notice->created);
		if (!$recipient) {
			continue;
		}
		if ($i == 0 && ($recipient->id != $sender->id)) { # Don't save reply to self
			$reply_for = $recipient;
			$recipient_notice = $reply_for->getCurrentNotice();
			if ($recipient_notice) {
				$orig = clone($notice);
				$notice->reply_to = $recipient_notice->id;
				$notice->update($orig);
			}
		}
		$reply = new Reply();
		$reply->notice_id = $notice->id;
		$reply->profile_id = $recipient->id;
		$id = $reply->insert();
		if (!$id) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			common_log(LOG_ERR, 'DB error inserting reply: ' . $last_error->message);
			common_server_error(sprintf(_('DB error inserting reply: %s'), $last_error->message));
			return;
		}
	}
}

function common_broadcast_notice($notice, $remote=false) {
	
	// Check to see if notice should go to Twitter
	$flink = Foreign_link::getForeignLink($notice->profile_id, 1); // 1 == Twitter
	
	if ($flink) {
		if (!common_twitter_broadcast($notice, $flink)) {
			common_debug('Unable to send notice: ' . $notice->id . ' to Twitter.', __FILE__);
		}
	}
	
	if (common_config('queue', 'enabled')) {
		# Do it later!
		return common_enqueue_notice($notice);
	} else {
		return common_real_broadcast($notice, $remote);
	}
}

function common_twitter_broadcast($notice, $flink) {	
	$success = true;
	$fuser = $flink->getForeignUser();
	$twitter_user = $fuser->nickname;
	$twitter_password = $flink->credentials;
	$uri = 'http://www.twitter.com/statuses/update.json';
	$statustxt = $notice->content;
	
	$options = array(
		CURLOPT_USERPWD 		=> "$twitter_user:$twitter_password",
		CURLOPT_POST			=> true,
		CURLOPT_POSTFIELDS		=> array(
									'status'	=> $statustxt,
									'source'	=> 'Laconica'
									),
		CURLOPT_RETURNTRANSFER	=> true,
		CURLOPT_FAILONERROR		=> true,
		CURLOPT_HEADER			=> false,
		CURLOPT_FOLLOWLOCATION	=> true,
		// CURLOPT_USERAGENT		=> "identi.ca",
		CURLOPT_CONNECTTIMEOUT	=> 120,  // XXX: Scary!!!! How long should this be?
		CURLOPT_TIMEOUT			=> 120
	);
	
	$ch = curl_init($uri);
    curl_setopt_array($ch, $options);
    $data = curl_exec($ch);
    $errmsg = curl_error($ch);

	if ($errmsg) {
		common_debug("cURL error: $errmsg - trying to send notice for $twitter_user.", 
			__FILE__);
		$success = false;
	}

	curl_close($ch);
	
	if (!$data) {
		common_debug("No data returned by Twitter's API trying to send update for $twitter_user",
			__FILE__);
		$success = false;
	}

	// Twitter should return a status	
	$status = json_decode($data);

	if (!$status->id) {
		common_debug("Unexpected data returned by Twitter API trying to send update for $twitter_user",
			__FILE__);
		$success = false;
	}
	
	return $status;
}

# Stick the notice on the queue

function common_enqueue_notice($notice) {
	foreach (array('jabber', 'omb', 'sms', 'public') as $transport) {
		$qi = new Queue_item();
		$qi->notice_id = $notice->id;
		$qi->transport = $transport;
		$qi->created = $notice->created;
        $result = $qi->insert();
		if (!$result) {
			$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
			common_log(LOG_ERR, 'DB error inserting queue item: ' . $last_error->message);
			return false;
		}
		common_log(LOG_DEBUG, 'complete queueing notice ID = ' . $notice->id . ' for ' . $transport);
	}
	return $result;
}

function common_dequeue_notice($notice) {
        $qi = Queue_item::staticGet($notice->id);
        if ($qi) {
                $result = $qi->delete();
	        if (!$result) {
	            $last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
                    common_log(LOG_ERR, 'DB error deleting queue item: ' . $last_error->message);
                    return false;
                }
                common_log(LOG_DEBUG, 'complete dequeueing notice ID = ' . $notice->id);
                return $result;
        } else {
            return false;
        }
}

function common_real_broadcast($notice, $remote=false) {
	$success = true;
	if (!$remote) {
		# Make sure we have the OMB stuff
		require_once(INSTALLDIR.'/lib/omb.php');
		$success = omb_broadcast_remote_subscribers($notice);
		if (!$success) {
			common_log(LOG_ERR, 'Error in OMB broadcast for notice ' . $notice->id);
		}
	}
	if ($success) {
		require_once(INSTALLDIR.'/lib/jabber.php');
		$success = jabber_broadcast_notice($notice);
		if (!$success) {
			common_log(LOG_ERR, 'Error in jabber broadcast for notice ' . $notice->id);
		}
	}
	if ($success) {
		require_once(INSTALLDIR.'/lib/mail.php');
		$success = mail_broadcast_notice_sms($notice);
		if (!$success) {
			common_log(LOG_ERR, 'Error in sms broadcast for notice ' . $notice->id);
		}
	}
	if ($success) {
		$success = jabber_public_notice($notice);
		if (!$success) {
			common_log(LOG_ERR, 'Error in public broadcast for notice ' . $notice->id);
		}
	}
	// XXX: broadcast notices to other IM
	return $success;
}

function common_broadcast_profile($profile) {
	// XXX: optionally use a queue system like http://code.google.com/p/microapps/wiki/NQDQ
	require_once(INSTALLDIR.'/lib/omb.php');
	omb_broadcast_profile($profile);
	// XXX: Other broadcasts...?
	return true;
}

function common_profile_url($nickname) {
	return common_local_url('showstream', array('nickname' => $nickname));
}

# Don't call if nobody's logged in

function common_notice_form($action=NULL, $content=NULL) {
	$user = common_current_user();
	assert(!is_null($user));
	common_element_start('form', array('id' => 'status_form',
									   'method' => 'post',
									   'action' => common_local_url('newnotice')));
	common_element_start('p');
	common_element('label', array('for' => 'status_textarea',
								  'id' => 'status_label'),
				   sprintf(_('What\'s up, %s?'), $user->nickname));
        common_element('span', array('id' => 'counter', 'class' => 'counter'), '140');
	common_element('textarea', array('id' => 'status_textarea',
									 'cols' => 60,
									 'rows' => 3,
									 'name' => 'status_textarea'),
				   ($content) ? $content : '');
	if ($action) {
		common_hidden('returnto', $action);
	}
	common_element('input', array('id' => 'status_submit',
								  'name' => 'status_submit',
								  'type' => 'submit',
								  'value' => _('Send')));
	common_element_end('p');
	common_element_end('form');
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

function common_ensure_syslog() {
	static $initialized = false;
	if (!$initialized) {
		global $config;
		openlog($config['syslog']['appname'], 0, LOG_USER);
		$initialized = true;
	}
}

function common_log($priority, $msg, $filename=NULL) {
	$logfile = common_config('site', 'logfile');
	if ($logfile) {
		$log = fopen($logfile, "a");
		if ($log) {
			static $syslog_priorities = array('LOG_EMERG', 'LOG_ALERT', 'LOG_CRIT', 'LOG_ERR',
											  'LOG_WARNING', 'LOG_NOTICE', 'LOG_INFO', 'LOG_DEBUG');
			$output = date('Y-m-d H:i:s') . ' ' . $syslog_priorities[$priority] . ': ' . $msg . "\n";
			fwrite($log, $output);
			fclose($log);
		}
	} else {
		common_ensure_syslog();
		syslog($priority, $msg);
	}
}

function common_debug($msg, $filename=NULL) {
	if ($filename) {
		common_log(LOG_DEBUG, basename($filename).' - '.$msg);
	} else {
		common_log(LOG_DEBUG, $msg);
	}
}

function common_log_db_error(&$object, $verb, $filename=NULL) {
	$objstr = common_log_objstring($object);
	$last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
	common_log(LOG_ERR, $last_error->message . '(' . $verb . ' on ' . $objstr . ')', $filename);
}

function common_log_objstring(&$object) {
	if (is_null($object)) {
		return "NULL";
	}
	$arr = $object->toArray();
	$fields = array();
	foreach ($arr as $k => $v) {
		$fields[] = "$k='$v'";
	}
	$objstring = $object->tableName() . '[' . implode(',', $fields) . ']';
	return $objstring;
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
					   _('« After'));
		common_element_end('li');
	}

	if ($have_after) {
		$pargs = array('page' => $page+1);
		$newargs = ($args) ? array_merge($args,$pargs) : $pargs;
		common_element_start('li', 'after');
		common_element('a', array('href' => common_local_url($action, $newargs)),
						   _('Before »'));
		common_element_end('li');
	}

	if ($have_before || $have_after) {
		common_element_end('ul');
		common_element_end('div');
	}
}

/* Following functions are copied from MediaWiki GlobalFunctions.php
 * and written by Evan Prodromou. */

function common_accept_to_prefs($accept, $def = '*/*') {
	# No arg means accept anything (per HTTP spec)
	if(!$accept) {
		return array($def => 1);
	}

	$prefs = array();

	$parts = explode(',', $accept);

	foreach($parts as $part) {
		# FIXME: doesn't deal with params like 'text/html; level=1'
		@list($value, $qpart) = explode(';', $part);
		$match = array();
		if(!isset($qpart)) {
			$prefs[$value] = 1;
		} elseif(preg_match('/q\s*=\s*(\d*\.\d+)/', $qpart, $match)) {
			$prefs[$value] = $match[1];
		}
	}

	return $prefs;
}

function common_mime_type_match($type, $avail) {
	if(array_key_exists($type, $avail)) {
		return $type;
	} else {
		$parts = explode('/', $type);
		if(array_key_exists($parts[0] . '/*', $avail)) {
			return $parts[0] . '/*';
		} elseif(array_key_exists('*/*', $avail)) {
			return '*/*';
		} else {
			return NULL;
		}
	}
}

function common_negotiate_type($cprefs, $sprefs) {
	$combine = array();

	foreach(array_keys($sprefs) as $type) {
		$parts = explode('/', $type);
		if($parts[1] != '*') {
			$ckey = common_mime_type_match($type, $cprefs);
			if($ckey) {
				$combine[$type] = $sprefs[$type] * $cprefs[$ckey];
			}
		}
	}

	foreach(array_keys($cprefs) as $type) {
		$parts = explode('/', $type);
		if($parts[1] != '*' && !array_key_exists($type, $sprefs)) {
			$skey = common_mime_type_match($type, $sprefs);
			if($skey) {
				$combine[$type] = $sprefs[$skey] * $cprefs[$type];
			}
		}
	}

	$bestq = 0;
	$besttype = "text/html";

	foreach(array_keys($combine) as $type) {
		if($combine[$type] > $bestq) {
			$besttype = $type;
			$bestq = $combine[$type];
		}
	}

	return $besttype;
}

function common_config($main, $sub) {
	global $config;
	return isset($config[$main][$sub]) ? $config[$main][$sub] : false;
}

function common_copy_args($from) {
	$to = array();
	$strip = get_magic_quotes_gpc();
	foreach ($from as $k => $v) {
		$to[$k] = ($strip) ? stripslashes($v) : $v;
	}
	return $to;
}

// Neutralise the evil effects of magic_quotes_gpc in the current request.
// This is used before handing a request off to OAuthRequest::from_request.
function common_remove_magic_from_request() {
	if(get_magic_quotes_gpc()) {
		$_POST=array_map('stripslashes',$_POST);
		$_GET=array_map('stripslashes',$_GET);
	}
}

function common_user_uri(&$user) {
	return common_local_url('userbyid', array('id' => $user->id));
}

function common_notice_uri(&$notice) {
	return common_local_url('shownotice',
		array('notice' => $notice->id));
}

# 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits

function common_confirmation_code($bits) {
	# 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits
	static $codechars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
	$chars = ceil($bits/5);
	$code = '';
	for ($i = 0; $i < $chars; $i++) {
		# XXX: convert to string and back
		$num = hexdec(common_good_rand(1));
		# XXX: randomness is too precious to throw away almost
		# 40% of the bits we get!
		$code .= $codechars[$num%32];
	}
	return $code;
}

# convert markup to HTML

function common_markup_to_html($c) {
	$c = preg_replace('/%%action.(\w+)%%/e', "common_local_url('\\1')", $c);
	$c = preg_replace('/%%doc.(\w+)%%/e', "common_local_url('doc', array('title'=>'\\1'))", $c);
	$c = preg_replace('/%%(\w+).(\w+)%%/e', 'common_config(\'\\1\', \'\\2\')', $c);
	return Markdown($c);
}

function common_profile_avatar_url($profile, $size=AVATAR_PROFILE_SIZE) {
	$avatar = $profile->getAvatar($size);
	if ($avatar) {
		return common_avatar_display_url($avatar);
	} else {
		return common_default_avatar($size);
	}
}

function common_profile_uri($profile) {
	if (!$profile) {
		return NULL;
	}
	$user = User::staticGet($profile->id);
	if ($user) {
		return $user->uri;
	}

	$remote = Remote_profile::staticGet($profile->id);
	if ($remote) {
		return $remote->uri;
	}
	# XXX: this is a very bad profile!
	return NULL;
}

function common_canonical_sms($sms) {
	# strip non-digits
	preg_replace('/\D/', '', $sms);
	return $sms;
}

function common_error_handler($errno, $errstr, $errfile, $errline, $errcontext) {
    switch ($errno) {
     case E_USER_ERROR:
		common_log(LOG_ERR, "[$errno] $errstr ($errfile:$errline)");
		exit(1);
		break;

	 case E_USER_WARNING:
		common_log(LOG_WARNING, "[$errno] $errstr ($errfile:$errline)");
		break;

     case E_USER_NOTICE:
		common_log(LOG_NOTICE, "[$errno] $errstr ($errfile:$errline)");
		break;
    }

	# FIXME: show error page if we're on the Web
    /* Don't execute PHP internal error handler */
    return true;
}

function common_session_token() {
	common_ensure_session();
	if (!array_key_exists('token', $_SESSION)) {
		$_SESSION['token'] = common_good_rand(64);
	}
	return $_SESSION['token'];
}

function common_disfavor_form($notice) {
	common_element_start('form', array('id' => 'disfavor-' . $notice->id,
									   'method' => 'post',
									   'class' => 'disfavor',
									   'action' => common_local_url('disfavor')));
	common_hidden('token', common_session_token());
	common_hidden('notice', $notice->id);
	common_element('input', array('type' => 'submit',
								  'id' => 'disfavor-submit-' . $notice->id,
								  'name' => 'disfavor-submit-' . $notice->id,
								  'class' => 'disfavor',
								  'value' => '♥'));
	common_element_end('form');
}

function common_favor_form($notice) {
	common_element_start('form', array('id' => 'favor-' . $notice->id,
									   'method' => 'post',
									   'class' => 'favor',
									   'action' => common_local_url('favor')));
	common_hidden('token', common_session_token());
	common_hidden('notice', $notice->id);
	common_element('input', array('type' => 'submit',
								  'id' => 'favor-submit-' . $notice->id,
								  'name' => 'favor-submit-' . $notice->id,
								  'class' => 'favor',
								  'value' => '♡'));
	common_element_end('form');
}

function common_cache_key($extra) {
	return 'laconica:' . common_keyize(common_config('site', 'name')) . ':' . $extra;
}

function common_keyize($str) {
	$str = strtolower($str);
	$str = preg_replace('/\s/', '_', $str);
	return $str;
}
