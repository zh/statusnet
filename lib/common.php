<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) { exit(1); }

define('LACONICA_VERSION', '0.8.0dev');

define('AVATAR_PROFILE_SIZE', 96);
define('AVATAR_STREAM_SIZE', 48);
define('AVATAR_MINI_SIZE', 24);

define('NOTICES_PER_PAGE', 20);
define('PROFILES_PER_PAGE', 20);

define('FOREIGN_NOTICE_SEND', 1);
define('FOREIGN_NOTICE_RECV', 2);
define('FOREIGN_NOTICE_SEND_REPLY', 4);

define('FOREIGN_FRIEND_SEND', 1);
define('FOREIGN_FRIEND_RECV', 2);

define_syslog_variables();

# append our extlib dir as the last-resort place to find libs

set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/extlib/');

# global configuration object

require_once('PEAR.php');
require_once('DB/DataObject.php');
require_once('DB/DataObject/Cast.php'); # for dates

require_once(INSTALLDIR.'/lib/language.php');

// This gets included before the config file, so that admin code and plugins
// can use it

require_once(INSTALLDIR.'/lib/event.php');
require_once(INSTALLDIR.'/lib/plugin.php');

function _sn_to_path($sn)
{
    $past_root = substr($sn, 1);
    $last_slash = strrpos($past_root, '/');
    if ($last_slash > 0) {
        $p = substr($past_root, 0, $last_slash);
    } else {
        $p = '';
    }
    return $p;
}

// try to figure out where we are. $server and $path
// can be set by including module, else we guess based
// on HTTP info.

if (isset($server)) {
    $_server = $server;
} else {
    $_server = array_key_exists('SERVER_NAME', $_SERVER) ?
      strtolower($_SERVER['SERVER_NAME']) :
    null;
}

if (isset($path)) {
    $_path = $path;
} else {
    $_path = array_key_exists('SCRIPT_NAME', $_SERVER) ?
      _sn_to_path($_SERVER['SCRIPT_NAME']) :
    null;
}

// default configuration, overwritten in config.php

$config =
  array('site' =>
        array('name' => 'Just another Laconica microblog',
              'server' => $_server,
              'theme' => 'default',
              'design' =>
              array('backgroundcolor' => '#CEE1E9',
                    'contentcolor' => '#FFFFFF',
                    'sidebarcolor' => '#C8D1D5',
                    'textcolor' => '#000000',
                    'linkcolor' => '#002E6E',
                    'backgroundimage' => null,
                    'disposition' => 1),
              'path' => $_path,
              'logfile' => null,
              'logo' => null,
              'logdebug' => false,
              'fancy' => false,
              'locale_path' => INSTALLDIR.'/locale',
              'language' => 'en_US',
              'languages' => get_all_languages(),
              'email' =>
              array_key_exists('SERVER_ADMIN', $_SERVER) ? $_SERVER['SERVER_ADMIN'] : null,
              'broughtby' => null,
              'timezone' => 'UTC',
              'broughtbyurl' => null,
              'closed' => false,
              'inviteonly' => false,
              'private' => false,
              'ssl' => 'never',
              'sslserver' => null,
              'dupelimit' => 60), # default for same person saying the same thing
        'syslog' =>
        array('appname' => 'laconica', # for syslog
              'priority' => 'debug'), # XXX: currently ignored
        'queue' =>
        array('enabled' => false,
              'subsystem' => 'db', # default to database, or 'stomp'
              'stomp_server' => null,
              'queue_basename' => 'laconica',
              'stomp_username' => null,
              'stomp_password' => null,
              ),
        'license' =>
        array('url' => 'http://creativecommons.org/licenses/by/3.0/',
              'title' => 'Creative Commons Attribution 3.0',
              'image' => 'http://i.creativecommons.org/l/by/3.0/80x15.png'),
        'mail' =>
        array('backend' => 'mail',
              'params' => null),
        'nickname' =>
        array('blacklist' => array(),
              'featured' => array()),
        'profile' =>
        array('banned' => array()),
        'avatar' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/avatar/',
              'path' => $_path . '/avatar/'),
        'background' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/background/',
              'path' => $_path . '/background/'),
        'public' =>
        array('localonly' => true,
              'blacklist' => array(),
              'autosource' => array()),
        'theme' =>
        array('server' => null,
              'dir' => null,
              'path'=> null),
        'throttle' =>
        array('enabled' => false, // whether to throttle edits; false by default
              'count' => 20, // number of allowed messages in timespan
              'timespan' => 600), // timespan for throttling
        'xmpp' =>
        array('enabled' => false,
              'server' => 'INVALID SERVER',
              'port' => 5222,
              'user' => 'update',
              'encryption' => true,
              'resource' => 'uniquename',
              'password' => 'blahblahblah',
              'host' => null, # only set if != server
              'debug' => false, # print extra debug info
              'public' => array()), # JIDs of users who want to receive the public stream
        'sphinx' =>
        array('enabled' => false,
              'server' => 'localhost',
              'port' => 3312),
        'tag' =>
        array('dropoff' => 864000.0),
        'popular' =>
        array('dropoff' => 864000.0),
        'daemon' =>
        array('piddir' => '/var/run',
              'user' => false,
              'group' => false),
        'twitterbridge' =>
        array('enabled' => false),
        'integration' =>
        array('source' => 'Laconica', # source attribute for Twitter
              'taguri' => $_server.',2009'), # base for tag URIs
        'memcached' =>
        array('enabled' => false,
              'server' => 'localhost',
              'base' => null,
              'port' => 11211),
 		'ping' =>
        array('notify' => array()),
        'inboxes' =>
        array('enabled' => true), # on by default for new sites
        'newuser' =>
        array('subscribe' => null,
              'welcome' => null),
        'snapshot' =>
        array('run' => 'web',
              'frequency' => 10000,
              'reporturl' => 'http://laconi.ca/stats/report'),
        'attachments' =>
        array('server' => null,
              'dir' => INSTALLDIR . '/file/',
              'path' => $_path . '/file/',
              'supported' => array('image/png',
                                   'image/jpeg',
                                   'image/gif',
                                   'image/svg+xml',
                                   'audio/mpeg',
                                   'audio/x-speex',
                                   'application/ogg',
                                   'application/pdf',
                                   'application/vnd.oasis.opendocument.text',
                                   'application/vnd.oasis.opendocument.text-template',
                                   'application/vnd.oasis.opendocument.graphics',
                                   'application/vnd.oasis.opendocument.graphics-template',
                                   'application/vnd.oasis.opendocument.presentation',
                                   'application/vnd.oasis.opendocument.presentation-template',
                                   'application/vnd.oasis.opendocument.spreadsheet',
                                   'application/vnd.oasis.opendocument.spreadsheet-template',
                                   'application/vnd.oasis.opendocument.chart',
                                   'application/vnd.oasis.opendocument.chart-template',
                                   'application/vnd.oasis.opendocument.image',
                                   'application/vnd.oasis.opendocument.image-template',
                                   'application/vnd.oasis.opendocument.formula',
                                   'application/vnd.oasis.opendocument.formula-template',
                                   'application/vnd.oasis.opendocument.text-master',
                                   'application/vnd.oasis.opendocument.text-web',
                                   'application/x-zip',
                                   'application/zip',
                                   'text/plain',
                                   'video/mpeg',
                                   'video/mp4',
                                   'video/quicktime',
                                   'video/mpeg'),
        'file_quota' => 5000000,
        'user_quota' => 50000000,
        'monthly_quota' => 15000000,
        'uploads' => true,
        'filecommand' => '/usr/bin/file',
        ),
        'group' =>
        array('maxaliases' => 3),
        'oohembed' => array('endpoint' => 'http://oohembed.com/oohembed/'),
        'search' =>
        array('type' => 'fulltext'),
        );

$config['db'] = &PEAR::getStaticProperty('DB_DataObject','options');

$config['db'] =
  array('database' => 'YOU HAVE TO SET THIS IN config.php',
        'schema_location' => INSTALLDIR . '/classes',
        'class_location' => INSTALLDIR . '/classes',
        'require_prefix' => 'classes/',
        'class_prefix' => '',
        'mirror' => null,
        'utf8' => true,
        'db_driver' => 'DB', # XXX: JanRain libs only work with DB
        'quote_identifiers' => false,
        'type' => 'mysql' );

if (function_exists('date_default_timezone_set')) {
    /* Work internally in UTC */
    date_default_timezone_set('UTC');
}

// From most general to most specific:
// server-wide, then vhost-wide, then for a path,
// finally for a dir (usually only need one of the last two).

if (isset($conffile)) {
    $_config_files = array($conffile);
} else {
    $_config_files = array('/etc/laconica/laconica.php',
                           '/etc/laconica/'.$_server.'.php');

    if (strlen($_path) > 0) {
        $_config_files[] = '/etc/laconica/'.$_server.'_'.$_path.'.php';
    }

    $_config_files[] = INSTALLDIR.'/config.php';
}

$_have_a_config = false;

foreach ($_config_files as $_config_file) {
    if (@file_exists($_config_file)) {
        include_once($_config_file);
        $_have_a_config = true;
    }
}

function _have_config()
{
    global $_have_a_config;
    return $_have_a_config;
}

// XXX: Throw a conniption if database not installed

// Fixup for laconica.ini

$_db_name = substr($config['db']['database'], strrpos($config['db']['database'], '/') + 1);

if ($_db_name != 'laconica' && !array_key_exists('ini_'.$_db_name, $config['db'])) {
    $config['db']['ini_'.$_db_name] = INSTALLDIR.'/classes/laconica.ini';
}

// XXX: how many of these could be auto-loaded on use?

require_once 'Validate.php';
require_once 'markdown.php';

require_once INSTALLDIR.'/lib/util.php';
require_once INSTALLDIR.'/lib/action.php';
require_once INSTALLDIR.'/lib/theme.php';
require_once INSTALLDIR.'/lib/mail.php';
require_once INSTALLDIR.'/lib/subs.php';
require_once INSTALLDIR.'/lib/Shorturl_api.php';
require_once INSTALLDIR.'/lib/twitter.php';

require_once INSTALLDIR.'/lib/clientexception.php';
require_once INSTALLDIR.'/lib/serverexception.php';

// XXX: other formats here

define('NICKNAME_FMT', VALIDATE_NUM.VALIDATE_ALPHA_LOWER);

function __autoload($class)
{
    if ($class == 'OAuthRequest') {
        require_once('OAuth.php');
    } else if (file_exists(INSTALLDIR.'/classes/' . $class . '.php')) {
        require_once(INSTALLDIR.'/classes/' . $class . '.php');
    } else if (file_exists(INSTALLDIR.'/lib/' . strtolower($class) . '.php')) {
        require_once(INSTALLDIR.'/lib/' . strtolower($class) . '.php');
    } else if (mb_substr($class, -6) == 'Action' &&
               file_exists(INSTALLDIR.'/actions/' . strtolower(mb_substr($class, 0, -6)) . '.php')) {
        require_once(INSTALLDIR.'/actions/' . strtolower(mb_substr($class, 0, -6)) . '.php');
    }
}

// Give plugins a chance to initialize in a fully-prepared environment

Event::handle('InitializePlugin');
