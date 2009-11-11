<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

define('STATUSNET_VERSION', '0.9.0dev');
define('LACONICA_VERSION', STATUSNET_VERSION); // compatibility

define('STATUSNET_CODENAME', 'Stand');

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

if (!function_exists('gettext')) {
    require_once("php-gettext/gettext.inc");
}

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
    $_path = (array_key_exists('SERVER_NAME', $_SERVER) && array_key_exists('SCRIPT_NAME', $_SERVER)) ?
      _sn_to_path($_SERVER['SCRIPT_NAME']) :
    null;
}

require_once(INSTALLDIR.'/lib/default.php');

// Set config values initially to default values

$config = $default;

// default configuration, overwritten in config.php

$config['db'] = &PEAR::getStaticProperty('DB_DataObject','options');

$config['db'] = $default['db'];

// Backward compatibility

$config['site']['design'] =& $config['design'];

if (function_exists('date_default_timezone_set')) {
    /* Work internally in UTC */
    date_default_timezone_set('UTC');
}

function addPlugin($name, $attrs = null)
{
    $name = ucfirst($name);
    $pluginclass = "{$name}Plugin";

    if (!class_exists($pluginclass)) {

        $files = array("local/plugins/{$pluginclass}.php",
                       "local/plugins/{$name}/{$pluginclass}.php",
                       "local/{$pluginclass}.php",
                       "local/{$name}/{$pluginclass}.php",
                       "plugins/{$pluginclass}.php",
                       "plugins/{$name}/{$pluginclass}.php");

        foreach ($files as $file) {
            $fullpath = INSTALLDIR.'/'.$file;
            if (@file_exists($fullpath)) {
                include_once($fullpath);
                break;
            }
        }
    }

    $inst = new $pluginclass();

    if (!empty($attrs)) {
        foreach ($attrs as $aname => $avalue) {
            $inst->$aname = $avalue;
        }
    }
    return $inst;
}

// From most general to most specific:
// server-wide, then vhost-wide, then for a path,
// finally for a dir (usually only need one of the last two).

if (isset($conffile)) {
    $_config_files = array($conffile);
} else {
    $_config_files = array('/etc/statusnet/statusnet.php',
                           '/etc/statusnet/laconica.php',
                           '/etc/laconica/laconica.php',
                           '/etc/statusnet/'.$_server.'.php',
                           '/etc/laconica/'.$_server.'.php');

    if (strlen($_path) > 0) {
        $_config_files[] = '/etc/statusnet/'.$_server.'_'.$_path.'.php';
        $_config_files[] = '/etc/laconica/'.$_server.'_'.$_path.'.php';
    }

    $_config_files[] = INSTALLDIR.'/config.php';
}

global $_have_a_config;
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
// XXX: Find a way to use htmlwriter for this instead of handcoded markup
if (!_have_config()) {
  echo '<p>'. _('No configuration file found. ') .'</p>';
  echo '<p>'. _('I looked for configuration files in the following places: ') .'<br/> '. implode($_config_files, '<br/>');
  echo '<p>'. _('You may wish to run the installer to fix this.') .'</p>';
  echo '<a href="install.php">'. _('Go to the installer.') .'</a>';
  exit;
}
// Fixup for statusnet.ini

$_db_name = substr($config['db']['database'], strrpos($config['db']['database'], '/') + 1);

if ($_db_name != 'statusnet' && !array_key_exists('ini_'.$_db_name, $config['db'])) {
    $config['db']['ini_'.$_db_name] = INSTALLDIR.'/classes/statusnet.ini';
}

function __autoload($cls)
{
    if (file_exists(INSTALLDIR.'/classes/' . $cls . '.php')) {
        require_once(INSTALLDIR.'/classes/' . $cls . '.php');
    } else if (file_exists(INSTALLDIR.'/lib/' . strtolower($cls) . '.php')) {
        require_once(INSTALLDIR.'/lib/' . strtolower($cls) . '.php');
    } else if (mb_substr($cls, -6) == 'Action' &&
               file_exists(INSTALLDIR.'/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php')) {
        require_once(INSTALLDIR.'/actions/' . strtolower(mb_substr($cls, 0, -6)) . '.php');
    } else if ($cls == 'OAuthRequest') {
        require_once('OAuth.php');
    } else {
        Event::handle('Autoload', array(&$cls));
    }
}

// XXX: how many of these could be auto-loaded on use?
// XXX: note that these files should not use config options
// at compile time since DB config options are not yet loaded.

require_once 'Validate.php';
require_once 'markdown.php';

require_once INSTALLDIR.'/lib/util.php';
require_once INSTALLDIR.'/lib/action.php';
require_once INSTALLDIR.'/lib/mail.php';
require_once INSTALLDIR.'/lib/subs.php';

require_once INSTALLDIR.'/lib/clientexception.php';
require_once INSTALLDIR.'/lib/serverexception.php';

// Load settings from database; note we need autoload for this

Config::loadSettings();

// XXX: if plugins should check the schema at runtime, do that here.

if ($config['db']['schemacheck'] == 'runtime') {
    Event::handle('CheckSchema');
}

// XXX: other formats here

define('NICKNAME_FMT', VALIDATE_NUM.VALIDATE_ALPHA_LOWER);

// Give plugins a chance to initialize in a fully-prepared environment

Event::handle('InitializePlugin');
