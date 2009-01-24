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

/* XXX: break up into separate modules (HTTP, user, files) */

// Show a server error

function common_server_error($msg, $code=500)
{
    $err = new ServerErrorAction($msg, $code);
    $err->showPage();
}

// Show a user error
function common_user_error($msg, $code=400)
{
    $err = new ClientErrorAction($msg, $code);
    $err->showPage();
}

function common_init_locale($language=null)
{
    if(!$language) {
        $language = common_language();
    }
    putenv('LANGUAGE='.$language);
    putenv('LANG='.$language);
    return setlocale(LC_ALL, $language . ".utf8",
                     $language . ".UTF8",
                     $language . ".utf-8",
                     $language . ".UTF-8",
                     $language);
}

function common_init_language()
{
    mb_internal_encoding('UTF-8');
    $language = common_language();
    // So we don't have to make people install the gettext locales
    $locale_set = common_init_locale($language);
    bindtextdomain("laconica", common_config('site','locale_path'));
    bind_textdomain_codeset("laconica", "UTF-8");
    textdomain("laconica");
    setlocale(LC_CTYPE, 'C');
    if(!$locale_set) {
        common_log(LOG_INFO,'Language requested:'.$language.' - locale could not be set:',__FILE__);
    }
}

function common_timezone()
{
    if (common_logged_in()) {
        $user = common_current_user();
        if ($user->timezone) {
            return $user->timezone;
        }
    }

    global $config;
    return $config['site']['timezone'];
}

function common_language()
{

    // If there is a user logged in and they've set a language preference
    // then return that one...
    if (common_logged_in()) {
        $user = common_current_user();
        $user_language = $user->language;
        if ($user_language)
          return $user_language;
    }

    // Otherwise, find the best match for the languages requested by the
    // user's browser...
    $httplang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null;
    if (!empty($httplang)) {
        $language = client_prefered_language($httplang);
        if ($language)
          return $language;
    }

    // Finally, if none of the above worked, use the site's default...
    return common_config('site', 'language');
}
// salted, hashed passwords are stored in the DB

function common_munge_password($password, $id)
{
    return md5($password . $id);
}

// check if a username exists and has matching password
function common_check_user($nickname, $password)
{
    // NEVER allow blank passwords, even if they match the DB
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

// is the current user logged in?
function common_logged_in()
{
    return (!is_null(common_current_user()));
}

function common_have_session()
{
    return (0 != strcmp(session_id(), ''));
}

function common_ensure_session()
{
    if (!common_have_session()) {
        @session_start();
    }
}

// Three kinds of arguments:
// 1) a user object
// 2) a nickname
// 3) null to clear

// Initialize to false; set to null if none found

$_cur = false;

function common_set_user($user)
{

    global $_cur;

    if (is_null($user) && common_have_session()) {
        $_cur = null;
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
        $_cur = $user;
        return $_cur;
    }
    return false;
}

function common_set_cookie($key, $value, $expiration=0)
{
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
define('REMEMBERME_EXPIRY', 30 * 24 * 60 * 60); // 30 days

function common_rememberme($user=null)
{
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

    // Wrap the insert in some good ol' fashioned transaction code

    $rm->query('BEGIN');

    $result = $rm->insert();

    if (!$result) {
        common_log_db_error($rm, 'INSERT', __FILE__);
        common_debug('Error adding rememberme record for ' . $user->nickname, __FILE__);
        return false;
    }

    $rm->query('COMMIT');

    common_debug('Inserted rememberme record (' . $rm->code . ', ' . $rm->user_id . '); result = ' . $result . '.', __FILE__);

    $cookieval = $rm->user_id . ':' . $rm->code;

    common_log(LOG_INFO, 'adding rememberme cookie "' . $cookieval . '" for ' . $user->nickname);

    common_set_cookie(REMEMBERME, $cookieval, time() + REMEMBERME_EXPIRY);

    return true;
}

function common_remembered_user()
{

    $user = null;

    $packed = isset($_COOKIE[REMEMBERME]) ? $_COOKIE[REMEMBERME] : null;

    if (!$packed) {
        return null;
    }

    list($id, $code) = explode(':', $packed);

    if (!$id || !$code) {
        common_log(LOG_WARNING, 'Malformed rememberme cookie: ' . $packed);
        common_forgetme();
        return null;
    }

    $rm = Remember_me::staticGet($code);

    if (!$rm) {
        common_log(LOG_WARNING, 'No such remember code: ' . $code);
        common_forgetme();
        return null;
    }

    if ($rm->user_id != $id) {
        common_log(LOG_WARNING, 'Rememberme code for wrong user: ' . $rm->user_id . ' != ' . $id);
        common_forgetme();
        return null;
    }

    $user = User::staticGet($rm->user_id);

    if (!$user) {
        common_log(LOG_WARNING, 'No such user for rememberme: ' . $rm->user_id);
        common_forgetme();
        return null;
    }

    // successful!
    $result = $rm->delete();

    if (!$result) {
        common_log_db_error($rm, 'DELETE', __FILE__);
        common_log(LOG_WARNING, 'Could not delete rememberme: ' . $code);
        common_forgetme();
        return null;
    }

    common_log(LOG_INFO, 'logging in ' . $user->nickname . ' using rememberme code ' . $rm->code);

    common_set_user($user);
    common_real_login(false);

    // We issue a new cookie, so they can log in
    // automatically again after this session

    common_rememberme($user);

    return $user;
}

// must be called with a valid user!

function common_forgetme()
{
    common_set_cookie(REMEMBERME, '', 0);
}

// who is the current user?
function common_current_user()
{
    global $_cur;

    if ($_cur === false) {

        if (isset($_REQUEST[session_name()]) || (isset($_SESSION['userid']) && $_SESSION['userid'])) {
            common_ensure_session();
            $id = isset($_SESSION['userid']) ? $_SESSION['userid'] : false;
            if ($id) {
                $_cur = User::staticGet($id);
                return $_cur;
            }
        }

        // that didn't work; try to remember; will init $_cur to null on failure
        $_cur = common_remembered_user();

        if ($_cur) {
            common_debug("Got User " . $_cur->nickname);
            common_debug("Faking session on remembered user");
            // XXX: Is this necessary?
            $_SESSION['userid'] = $_cur->id;
        }
    }

    return $_cur;
}

// Logins that are 'remembered' aren't 'real' -- they're subject to
// cookie-stealing. So, we don't let them do certain things. New reg,
// OpenID, and password logins _are_ real.

function common_real_login($real=true)
{
    common_ensure_session();
    $_SESSION['real_login'] = $real;
}

function common_is_real_login()
{
    return common_logged_in() && $_SESSION['real_login'];
}

// get canonical version of nickname for comparison
function common_canonical_nickname($nickname)
{
    // XXX: UTF-8 canonicalization (like combining chars)
    return strtolower($nickname);
}

// get canonical version of email for comparison
function common_canonical_email($email)
{
    // XXX: canonicalize UTF-8
    // XXX: lcase the domain part
    return $email;
}

define('URL_REGEX', '^|[ \t\r\n])((ftp|http|https|gopher|mailto|news|nntp|telnet|wais|file|prospero|aim|webcal):(([A-Za-z0-9$_.+!*(),;/?:@&~=-])|%[A-Fa-f0-9]{2}){2,}(#([a-zA-Z0-9][a-zA-Z0-9$_.+!*(),;/?:@&~=%-]*))?([A-Za-z0-9$_+!*();/?:~-]))');

function common_render_content($text, $notice)
{
    $r = common_render_text($text);
    $id = $notice->profile_id;
    $r = preg_replace('/(^|\s+)@([A-Za-z0-9]{1,64})/e', "'\\1@'.common_at_link($id, '\\2')", $r);
    $r = preg_replace('/^T ([A-Z0-9]{1,64}) /e', "'T '.common_at_link($id, '\\1').' '", $r);
    $r = preg_replace('/(^|\s+)@#([A-Za-z0-9]{1,64})/e', "'\\1@#'.common_at_hash_link($id, '\\2')", $r);
    $r = preg_replace('/(^|\s)!([A-Za-z0-9]{1,64})/e', "'\\1!'.common_group_link($id, '\\2')", $r);
    return $r;
}

function common_render_text($text)
{
    $r = htmlspecialchars($text);

    $r = preg_replace('/[\x{0}-\x{8}\x{b}-\x{c}\x{e}-\x{19}]/', '', $r);
    $r = preg_replace_callback('@https?://[^\]>\s]+@', 'common_render_uri_thingy', $r);
    $r = preg_replace('/(^|\s+)#([A-Za-z0-9_\-\.]{1,64})/e', "'\\1#'.common_tag_link('\\2')", $r);
    // XXX: machine tags
    return $r;
}

function common_render_uri_thingy($matches)
{
    $uri = $matches[0];
    $trailer = '';

    // Some heuristics for extracting URIs from surrounding punctuation
    // Strip from trailing text...
    if (preg_match('/^(.*)([,.:"\']+)$/', $uri, $matches)) {
        $uri = $matches[1];
        $trailer = $matches[2];
    }

    $pairs = array(
                   ']' => '[', // technically disallowed in URIs, but used in Java docs
                   ')' => '(', // far too frequent in Wikipedia and MSDN
                   );
    $final = substr($uri, -1, 1);
    if (isset($pairs[$final])) {
        $openers = substr_count($uri, $pairs[$final]);
        $closers = substr_count($uri, $final);
        if ($closers > $openers) {
            // Assume the paren was opened outside the URI
            $uri = substr($uri, 0, -1);
            $trailer = $final . $trailer;
        }
    }
    if ($longurl = common_longurl($uri)) {
        $longurl = htmlentities($longurl, ENT_QUOTES, 'UTF-8');
        $title = " title='$longurl'";
    }
    else $title = '';

    return '<a href="' . $uri . '"' . $title . ' class="extlink">' . $uri . '</a>' . $trailer;
}

function common_longurl($short_url)
{
    $long_url = common_shorten_link($short_url, true);
    if ($long_url === $short_url) return false;
    return $long_url;
}

function common_longurl2($uri)
{
    $uri_e = urlencode($uri);
    $longurl = unserialize(file_get_contents("http://api.longurl.org/v1/expand?format=php&url=$uri_e"));
    if (empty($longurl['long_url']) || $uri === $longurl['long_url']) return false;
    return stripslashes($longurl['long_url']);
}

function common_shorten_links($text)
{
    if (mb_strlen($text) <= 140) return $text;
    static $cache = array();
    if (isset($cache[$text])) return $cache[$text];
    // \s = not a horizontal whitespace character (since PHP 5.2.4)
    return $cache[$text] = preg_replace('@https?://[^)\]>\s]+@e', "common_shorten_link('\\0')", $text);
}

function common_shorten_link($url, $reverse = false)
{
    static $url_cache = array();
    if ($reverse) return isset($url_cache[$url]) ? $url_cache[$url] : $url;

    $user = common_current_user();

    $curlh = curl_init();
    curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, 20); // # seconds to wait
    curl_setopt($curlh, CURLOPT_USERAGENT, 'Laconica');
    curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);

    switch($user->urlshorteningservice) {
     case 'ur1.ca':
        $short_url_service = new LilUrl;
        $short_url = $short_url_service->shorten($url);
        break;

     case '2tu.us':
        $short_url_service = new TightUrl;
        $short_url = $short_url_service->shorten($url);
        break;

     case 'ptiturl.com':
        $short_url_service = new PtitUrl;
        $short_url = $short_url_service->shorten($url);
        break;

     case 'bit.ly':
        curl_setopt($curlh, CURLOPT_URL, 'http://bit.ly/api?method=shorten&long_url='.urlencode($url));
        $short_url = current(json_decode(curl_exec($curlh))->results)->hashUrl;
        break;

     case 'is.gd':
        curl_setopt($curlh, CURLOPT_URL, 'http://is.gd/api.php?longurl='.urlencode($url));
        $short_url = curl_exec($curlh);
        break;
     case 'snipr.com':
        curl_setopt($curlh, CURLOPT_URL, 'http://snipr.com/site/snip?r=simple&link='.urlencode($url));
        $short_url = curl_exec($curlh);
        break;
     case 'metamark.net':
        curl_setopt($curlh, CURLOPT_URL, 'http://metamark.net/api/rest/simple?long_url='.urlencode($url));
        $short_url = curl_exec($curlh);
        break;
     case 'tinyurl.com':
        curl_setopt($curlh, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url='.urlencode($url));
        $short_url = curl_exec($curlh);
        break;
     default:
        $short_url = false;
    }

    curl_close($curlh);

    if ($short_url) {
        $url_cache[(string)$short_url] = $url;
        return (string)$short_url;
    }
    return $url;
}

function common_xml_safe_str($str)
{
    $xmlStr = htmlentities(iconv('UTF-8', 'UTF-8//IGNORE', $str), ENT_NOQUOTES, 'UTF-8');

    // Replace control, formatting, and surrogate characters with '*', ala Twitter
    return preg_replace('/[\p{Cc}\p{Cf}\p{Cs}]/u', '*', $str);
}

function common_tag_link($tag)
{
    $canonical = common_canonical_tag($tag);
    $url = common_local_url('tag', array('tag' => $canonical));
    return '<span class="tag"><a href="' . htmlspecialchars($url) . '" rel="tag">' . htmlspecialchars($tag) . '</a></span>';
}

function common_canonical_tag($tag)
{
    return strtolower(str_replace(array('-', '_', '.'), '', $tag));
}

function common_valid_profile_tag($str)
{
    return preg_match('/^[A-Za-z0-9_\-\.]{1,64}$/', $str);
}

function common_at_link($sender_id, $nickname)
{
    $sender = Profile::staticGet($sender_id);
    $recipient = common_relative_profile($sender, common_canonical_nickname($nickname));
    if ($recipient) {
        return '<span class="vcard"><a href="'.htmlspecialchars($recipient->profileurl).'" class="url"><span class="fn nickname">'.$nickname.'</span></a></span>';
    } else {
        return $nickname;
    }
}

function common_group_link($sender_id, $nickname)
{
    $sender = Profile::staticGet($sender_id);
    $group = User_group::staticGet('nickname', common_canonical_nickname($nickname));
    if ($group && $sender->isMember($group)) {
        return '<span class="vcard"><a href="'.htmlspecialchars($group->permalink()).'" class="url"><span class="fn nickname">'.$nickname.'</span></a></span>';
    } else {
        return $nickname;
    }
}

function common_at_hash_link($sender_id, $tag)
{
    $user = User::staticGet($sender_id);
    if (!$user) {
        return $tag;
    }
    $tagged = Profile_tag::getTagged($user->id, common_canonical_tag($tag));
    if ($tagged) {
        $url = common_local_url('subscriptions',
                                array('nickname' => $user->nickname,
                                      'tag' => $tag));
        return '<span class="tag"><a href="'.htmlspecialchars($url).'" rel="tag">'.$tag.'</a></span>';
    } else {
        return $tag;
    }
}

function common_relative_profile($sender, $nickname, $dt=null)
{
    // Try to find profiles this profile is subscribed to that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscribed from subscription where subscriber = '.$sender->id.' and subscribed = id)', 'AND');
    $recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // Try to find profiles that listen to this profile and that have this nickname
    $recipient = new Profile();
    // XXX: use a join instead of a subquery
    $recipient->whereAdd('EXISTS (SELECT subscriber from subscription where subscribed = '.$sender->id.' and subscriber = id)', 'AND');
    $recipient->whereAdd('nickname = "' . trim($nickname) . '"', 'AND');
    if ($recipient->find(true)) {
        // XXX: should probably differentiate between profiles with
        // the same name by date of most recent update
        return $recipient;
    }
    // If this is a local user, try to find a local user with that nickname.
    $sender = User::staticGet($sender->id);
    if ($sender) {
        $recipient_user = User::staticGet('nickname', $nickname);
        if ($recipient_user) {
            return $recipient_user->getProfile();
        }
    }
    // Otherwise, no links. @messages from local users to remote users,
    // or from remote users to other remote users, are just
    // outside our ability to make intelligent guesses about
    return null;
}

// where should the avatar go for this user?

function common_avatar_filename($id, $extension, $size=null, $extra=null)
{
    global $config;

    if ($size) {
        return $id . '-' . $size . (($extra) ? ('-' . $extra) : '') . $extension;
    } else {
        return $id . '-original' . (($extra) ? ('-' . $extra) : '') . $extension;
    }
}

function common_avatar_path($filename)
{
    global $config;
    return INSTALLDIR . '/avatar/' . $filename;
}

function common_avatar_url($filename)
{
    return common_path('avatar/'.$filename);
}

function common_avatar_display_url($avatar)
{
    $server = common_config('avatar', 'server');
    if ($server) {
        return 'http://'.$server.'/'.$avatar->filename;
    } else {
        return $avatar->url;
    }
}

function common_default_avatar($size)
{
    static $sizenames = array(AVATAR_PROFILE_SIZE => 'profile',
                              AVATAR_STREAM_SIZE => 'stream',
                              AVATAR_MINI_SIZE => 'mini');
    return theme_path('default-avatar-'.$sizenames[$size].'.png');
}

function common_local_url($action, $args=null, $fragment=null)
{
    $url = null;
    if (common_config('site','fancy')) {
        $url = common_fancy_url($action, $args);
    } else {
        $url = common_simple_url($action, $args);
    }
    if (!is_null($fragment)) {
        $url .= '#'.$fragment;
    }
    return $url;
}

function common_fancy_url($action, $args=null)
{
    switch (strtolower($action)) {
     case 'public':
        if ($args && isset($args['page'])) {
            return common_path('?page=' . $args['page']);
        } else {
            return common_path('');
        }
     case 'featured':
        if ($args && isset($args['page'])) {
            return common_path('featured?page=' . $args['page']);
        } else {
            return common_path('featured');
        }
     case 'favorited':
        if ($args && isset($args['page'])) {
            return common_path('favorited?page=' . $args['page']);
        } else {
            return common_path('favorited');
        }
     case 'publicrss':
        return common_path('rss');
     case 'publicatom':
        return common_path("api/statuses/public_timeline.atom");
     case 'publicxrds':
        return common_path('xrds');
     case 'featuredrss':
        return common_path('featuredrss');
     case 'favoritedrss':
        return common_path('favoritedrss');
     case 'opensearch':
        if ($args && $args['type']) {
            return common_path('opensearch/'.$args['type']);
        } else {
            return common_path('opensearch/people');
        }
     case 'doc':
        return common_path('doc/'.$args['title']);
     case 'block':
     case 'login':
     case 'logout':
     case 'subscribe':
     case 'unsubscribe':
     case 'invite':
        return common_path('main/'.$action);
     case 'tagother':
        return common_path('main/tagother?id='.$args['id']);
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
     case 'nudge':
        return common_path($args['nickname'].'/nudge');
     case 'openidlogin':
        return common_path('main/openid');
     case 'profilesettings':
        return common_path('settings/profile');
     case 'passwordsettings':
        return common_path('settings/password');
     case 'emailsettings':
        return common_path('settings/email');
     case 'openidsettings':
        return common_path('settings/openid');
     case 'smssettings':
        return common_path('settings/sms');
     case 'twittersettings':
        return common_path('settings/twitter');
     case 'othersettings':
        return common_path('settings/other');
     case 'deleteprofile':
        return common_path('settings/delete');
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
     case 'microsummary':
     case 'xrds':
     case 'foaf':
        return common_path($args['nickname'].'/'.$action);
     case 'all':
     case 'replies':
     case 'inbox':
     case 'outbox':
        if ($args && isset($args['page'])) {
            return common_path($args['nickname'].'/'.$action.'?page=' . $args['page']);
        } else {
            return common_path($args['nickname'].'/'.$action);
        }
     case 'subscriptions':
     case 'subscribers':
        $nickname = $args['nickname'];
        unset($args['nickname']);
        if (isset($args['tag'])) {
            $tag = $args['tag'];
            unset($args['tag']);
        }
        $params = http_build_query($args);
        if ($params) {
            return common_path($nickname.'/'.$action . (($tag) ? '/' . $tag : '') . '?' . $params);
        } else {
            return common_path($nickname.'/'.$action . (($tag) ? '/' . $tag : ''));
        }
     case 'allrss':
        return common_path($args['nickname'].'/all/rss');
     case 'repliesrss':
        return common_path($args['nickname'].'/replies/rss');
     case 'userrss':
        if (isset($args['limit']))
          return common_path($args['nickname'].'/rss?limit=' . $args['limit']);
        return common_path($args['nickname'].'/rss');
     case 'showstream':
        if ($args && isset($args['page'])) {
            return common_path($args['nickname'].'?page=' . $args['page']);
        } else {
            return common_path($args['nickname']);
        }

     case 'usertimeline':
        return common_path("api/statuses/user_timeline/".$args['nickname'].".atom");
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
     case 'avatarsettings':
        return common_path('settings/avatar');
     case 'groupsearch':
        return common_path('search/group' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'peoplesearch':
        return common_path('search/people' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'noticesearch':
        return common_path('search/notice' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'noticesearchrss':
        return common_path('search/notice/rss' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'avatarbynickname':
        return common_path($args['nickname'].'/avatar/'.$args['size']);
     case 'tag':
        $path = 'tag/' . $args['tag'];
        unset($args['tag']);
        return common_path($path . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'publictagcloud':
        return common_path('tags');
     case 'peopletag':
        $path = 'peopletag/' . $args['tag'];
        unset($args['tag']);
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
     case 'favoritesrss':
        return common_path($args['nickname'].'/favorites/rss');
     case 'showmessage':
        return common_path('message/' . $args['message']);
     case 'newmessage':
        return common_path('message/new' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'api':
        // XXX: do fancy URLs for all the API methods
        switch (strtolower($args['apiaction'])) {
         case 'statuses':
            switch (strtolower($args['method'])) {
             case 'user_timeline.rss':
                return common_path('api/statuses/user_timeline/'.$args['argument'].'.rss');
             case 'user_timeline.atom':
                return common_path('api/statuses/user_timeline/'.$args['argument'].'.atom');
             case 'user_timeline.json':
                return common_path('api/statuses/user_timeline/'.$args['argument'].'.json');
             case 'user_timeline.xml':
                return common_path('api/statuses/user_timeline/'.$args['argument'].'.xml');
             default: return common_simple_url($action, $args);
            }
         default: return common_simple_url($action, $args);
        }
     case 'sup':
        if ($args && isset($args['seconds'])) {
            return common_path('main/sup?seconds='.$args['seconds']);
        } else {
            return common_path('main/sup');
        }
     case 'newgroup':
        return common_path('group/new');
     case 'showgroup':
        return common_path('group/'.$args['nickname']);
     case 'editgroup':
        return common_path('group/'.$args['nickname'].'/edit');
     case 'joingroup':
        return common_path('group/'.$args['nickname'].'/join');
     case 'leavegroup':
        return common_path('group/'.$args['nickname'].'/leave');
     case 'groupbyid':
        return common_path('group/'.$args['id'].'/id');
     case 'grouprss':
        return common_path('group/'.$args['nickname'].'/rss');
     case 'groupmembers':
        return common_path('group/'.$args['nickname'].'/members');
     case 'grouplogo':
        return common_path('group/'.$args['nickname'].'/logo');
     case 'usergroups':
        return common_path($args['nickname'].'/groups' . (($args) ? ('?' . http_build_query($args)) : ''));
     case 'groups':
        return common_path('search/group' . (($args) ? ('?' . http_build_query($args)) : ''));
     default:
        return common_simple_url($action, $args);
    }
}

function common_simple_url($action, $args=null)
{
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

function common_path($relative)
{
    global $config;
    $pathpart = ($config['site']['path']) ? $config['site']['path']."/" : '';
    return "http://".$config['site']['server'].'/'.$pathpart.$relative;
}

function common_date_string($dt)
{
    // XXX: do some sexy date formatting
    // return date(DATE_RFC822, $dt);
    $t = strtotime($dt);
    $now = time();
    $diff = $now - $t;

    if ($now < $t) { // that shouldn't happen!
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

function common_exact_date($dt)
{
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

function common_date_w3dtf($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format(DATE_W3C);
}

function common_date_rfc2822($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('r');
}

function common_date_iso8601($dt)
{
    $dateStr = date('d F Y H:i:s', strtotime($dt));
    $d = new DateTime($dateStr, new DateTimeZone('UTC'));
    $d->setTimezone(new DateTimeZone(common_timezone()));
    return $d->format('c');
}

function common_sql_now()
{
    return strftime('%Y-%m-%d %H:%M:%S', time());
}

function common_redirect($url, $code=307)
{
    static $status = array(301 => "Moved Permanently",
                           302 => "Found",
                           303 => "See Other",
                           307 => "Temporary Redirect");

    header("Status: ${code} $status[$code]");
    header("Location: $url");

    $xo = new XMLOutputter();
    $xo->startXML('a',
                  '-//W3C//DTD XHTML 1.0 Strict//EN',
                  'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd');
    $xo->element('a', array('href' => $url), $url);
    $xo->endXML();
    exit;
}

function common_broadcast_notice($notice, $remote=false)
{

    // Check to see if notice should go to Twitter
    $flink = Foreign_link::getByUserID($notice->profile_id, 1); // 1 == Twitter
    if (($flink->noticesync & FOREIGN_NOTICE_SEND) == FOREIGN_NOTICE_SEND) {

        // If it's not a Twitter-style reply, or if the user WANTS to send replies...

        if (!preg_match('/^@[a-zA-Z0-9_]{1,15}\b/u', $notice->content) ||
            (($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) == FOREIGN_NOTICE_SEND_REPLY)) {

            $result = common_twitter_broadcast($notice, $flink);

            if (!$result) {
                common_debug('Unable to send notice: ' . $notice->id . ' to Twitter.', __FILE__);
            }
        }
    }

    if (common_config('queue', 'enabled')) {
        // Do it later!
        return common_enqueue_notice($notice);
    } else {
        return common_real_broadcast($notice, $remote);
    }
}

function common_twitter_broadcast($notice, $flink)
{
    global $config;
    $success = true;
    $fuser = $flink->getForeignUser();
    $twitter_user = $fuser->nickname;
    $twitter_password = $flink->credentials;
    $uri = 'http://www.twitter.com/statuses/update.json';

    // XXX: Hack to get around PHP cURL's use of @ being a a meta character
    $statustxt = preg_replace('/^@/', ' @', $notice->content);

    $options = array(
                     CURLOPT_USERPWD         => "$twitter_user:$twitter_password",
                     CURLOPT_POST            => true,
                     CURLOPT_POSTFIELDS        => array(
                                                        'status'    => $statustxt,
                                                        'source'    => $config['integration']['source']
                                                        ),
                     CURLOPT_RETURNTRANSFER    => true,
                     CURLOPT_FAILONERROR        => true,
                     CURLOPT_HEADER            => false,
                     CURLOPT_FOLLOWLOCATION    => true,
                     CURLOPT_USERAGENT        => "Laconica",
                     CURLOPT_CONNECTTIMEOUT    => 120,  // XXX: Scary!!!! How long should this be?
                     CURLOPT_TIMEOUT            => 120,

                     # Twitter is strict about accepting invalid "Expect" headers
                     CURLOPT_HTTPHEADER => array('Expect:')
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

    return $success;
}

// Stick the notice on the queue

function common_enqueue_notice($notice)
{
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

function common_dequeue_notice($notice)
{
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

function common_real_broadcast($notice, $remote=false)
{
    $success = true;
    if (!$remote) {
        // Make sure we have the OMB stuff
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

function common_broadcast_profile($profile)
{
    // XXX: optionally use a queue system like http://code.google.com/p/microapps/wiki/NQDQ
    require_once(INSTALLDIR.'/lib/omb.php');
    omb_broadcast_profile($profile);
    // XXX: Other broadcasts...?
    return true;
}

function common_profile_url($nickname)
{
    return common_local_url('showstream', array('nickname' => $nickname));
}

// Should make up a reasonable root URL

function common_root_url()
{
    return common_path('');
}

// returns $bytes bytes of random data as a hexadecimal string
// "good" here is a goal and not a guarantee

function common_good_rand($bytes)
{
    // XXX: use random.org...?
    if (file_exists('/dev/urandom')) {
        return common_urandom($bytes);
    } else { // FIXME: this is probably not good enough
        return common_mtrand($bytes);
    }
}

function common_urandom($bytes)
{
    $h = fopen('/dev/urandom', 'rb');
    // should not block
    $src = fread($h, $bytes);
    fclose($h);
    $enc = '';
    for ($i = 0; $i < $bytes; $i++) {
        $enc .= sprintf("%02x", (ord($src[$i])));
    }
    return $enc;
}

function common_mtrand($bytes)
{
    $enc = '';
    for ($i = 0; $i < $bytes; $i++) {
        $enc .= sprintf("%02x", mt_rand(0, 255));
    }
    return $enc;
}

function common_set_returnto($url)
{
    common_ensure_session();
    $_SESSION['returnto'] = $url;
}

function common_get_returnto()
{
    common_ensure_session();
    return $_SESSION['returnto'];
}

function common_timestamp()
{
    return date('YmdHis');
}

function common_ensure_syslog()
{
    static $initialized = false;
    if (!$initialized) {
        global $config;
        openlog($config['syslog']['appname'], 0, LOG_USER);
        $initialized = true;
    }
}

function common_log($priority, $msg, $filename=null)
{
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

function common_debug($msg, $filename=null)
{
    if ($filename) {
        common_log(LOG_DEBUG, basename($filename).' - '.$msg);
    } else {
        common_log(LOG_DEBUG, $msg);
    }
}

function common_log_db_error(&$object, $verb, $filename=null)
{
    $objstr = common_log_objstring($object);
    $last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
    common_log(LOG_ERR, $last_error->message . '(' . $verb . ' on ' . $objstr . ')', $filename);
}

function common_log_objstring(&$object)
{
    if (is_null($object)) {
        return "null";
    }
    $arr = $object->toArray();
    $fields = array();
    foreach ($arr as $k => $v) {
        $fields[] = "$k='$v'";
    }
    $objstring = $object->tableName() . '[' . implode(',', $fields) . ']';
    return $objstring;
}

function common_valid_http_url($url)
{
    return Validate::uri($url, array('allowed_schemes' => array('http', 'https')));
}

function common_valid_tag($tag)
{
    if (preg_match('/^tag:(.*?),(\d{4}(-\d{2}(-\d{2})?)?):(.*)$/', $tag, $matches)) {
        return (Validate::email($matches[1]) ||
                preg_match('/^([\w-\.]+)$/', $matches[1]));
    }
    return false;
}

/* Following functions are copied from MediaWiki GlobalFunctions.php
 * and written by Evan Prodromou. */

function common_accept_to_prefs($accept, $def = '*/*')
{
    // No arg means accept anything (per HTTP spec)
    if(!$accept) {
        return array($def => 1);
    }

    $prefs = array();

    $parts = explode(',', $accept);

    foreach($parts as $part) {
        // FIXME: doesn't deal with params like 'text/html; level=1'
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

function common_mime_type_match($type, $avail)
{
    if(array_key_exists($type, $avail)) {
        return $type;
    } else {
        $parts = explode('/', $type);
        if(array_key_exists($parts[0] . '/*', $avail)) {
            return $parts[0] . '/*';
        } elseif(array_key_exists('*/*', $avail)) {
            return '*/*';
        } else {
            return null;
        }
    }
}

function common_negotiate_type($cprefs, $sprefs)
{
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

function common_config($main, $sub)
{
    global $config;
    return isset($config[$main][$sub]) ? $config[$main][$sub] : false;
}

function common_copy_args($from)
{
    $to = array();
    $strip = get_magic_quotes_gpc();
    foreach ($from as $k => $v) {
        $to[$k] = ($strip) ? stripslashes($v) : $v;
    }
    return $to;
}

// Neutralise the evil effects of magic_quotes_gpc in the current request.
// This is used before handing a request off to OAuthRequest::from_request.
function common_remove_magic_from_request()
{
    if(get_magic_quotes_gpc()) {
        $_POST=array_map('stripslashes',$_POST);
        $_GET=array_map('stripslashes',$_GET);
    }
}

function common_user_uri(&$user)
{
    return common_local_url('userbyid', array('id' => $user->id));
}

function common_notice_uri(&$notice)
{
    return common_local_url('shownotice',
                            array('notice' => $notice->id));
}

// 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits

function common_confirmation_code($bits)
{
    // 36 alphanums - lookalikes (0, O, 1, I) = 32 chars = 5 bits
    static $codechars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $chars = ceil($bits/5);
    $code = '';
    for ($i = 0; $i < $chars; $i++) {
        // XXX: convert to string and back
        $num = hexdec(common_good_rand(1));
        // XXX: randomness is too precious to throw away almost
        // 40% of the bits we get!
        $code .= $codechars[$num%32];
    }
    return $code;
}

// convert markup to HTML

function common_markup_to_html($c)
{
    $c = preg_replace('/%%action.(\w+)%%/e', "common_local_url('\\1')", $c);
    $c = preg_replace('/%%doc.(\w+)%%/e', "common_local_url('doc', array('title'=>'\\1'))", $c);
    $c = preg_replace('/%%(\w+).(\w+)%%/e', 'common_config(\'\\1\', \'\\2\')', $c);
    return Markdown($c);
}

function common_profile_avatar_url($profile, $size=AVATAR_PROFILE_SIZE)
{
    $avatar = $profile->getAvatar($size);
    if ($avatar) {
        return common_avatar_display_url($avatar);
    } else {
        return common_default_avatar($size);
    }
}

function common_profile_uri($profile)
{
    if (!$profile) {
        return null;
    }
    $user = User::staticGet($profile->id);
    if ($user) {
        return $user->uri;
    }

    $remote = Remote_profile::staticGet($profile->id);
    if ($remote) {
        return $remote->uri;
    }
    // XXX: this is a very bad profile!
    return null;
}

function common_canonical_sms($sms)
{
    // strip non-digits
    preg_replace('/\D/', '', $sms);
    return $sms;
}

function common_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
{
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

    // FIXME: show error page if we're on the Web
    /* Don't execute PHP internal error handler */
    return true;
}

function common_session_token()
{
    common_ensure_session();
    if (!array_key_exists('token', $_SESSION)) {
        $_SESSION['token'] = common_good_rand(64);
    }
    return $_SESSION['token'];
}

function common_cache_key($extra)
{
    return 'laconica:' . common_keyize(common_config('site', 'name')) . ':' . $extra;
}

function common_keyize($str)
{
    $str = strtolower($str);
    $str = preg_replace('/\s/', '_', $str);
    return $str;
}

function common_memcache()
{
    static $cache = null;
    if (!common_config('memcached', 'enabled')) {
        return null;
    } else {
        if (!$cache) {
            $cache = new Memcache();
            $servers = common_config('memcached', 'server');
            if (is_array($servers)) {
                foreach($servers as $server) {
                    $cache->addServer($server);
                }
            } else {
                $cache->addServer($servers);
            }
        }
        return $cache;
    }
}

function common_compatible_license($from, $to)
{
    // XXX: better compatibility check needed here!
    return ($from == $to);
}
