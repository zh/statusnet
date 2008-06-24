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

if (!defined('LACONICA')) { exit(1); }

define('LACONICA_VERSION', '0.3.4');

define('AVATAR_PROFILE_SIZE', 96);
define('AVATAR_STREAM_SIZE', 48);
define('AVATAR_MINI_SIZE', 24);
define('MAX_AVATAR_SIZE', 256 * 1024);

# global configuration object

require_once('PEAR.php');
require_once('DB/DataObject.php');
require_once('DB/DataObject/Cast.php'); # for dates

// default configuration, overwritten in config.php

$config =
  array('site' =>
		array('name' => 'Just another Laconica microblog',
			  'server' => 'localhost',
			  'theme' => 'default',
			  'path' => '/',
			  'fancy' => false),
		'syslog' =>
		array('appname' => 'laconica', # for syslog
			  'priority' => 'debug'), # XXX: currently ignored
		'tag' =>
		array('authority' => 'INVALID TAG',
			  'date' => 'PUT A DATE HERE',
			  'prefix' => ''),
		'license' =>
		array('url' => 'http://creativecommons.org/licenses/by/3.0/',
			  'title' => 'Creative Commons Attribution 3.0',
			  'image' => 'http://i.creativecommons.org/l/by/3.0/88x31.png'),
		'mail' =>
		array('backend' => 'mail',
			  'params' => NULL),
		'nickname' =>
		array('blacklist' => array()),
		'xmpp' =>
		array('enabled' => false,
			  'server' => 'INVALID SERVER',
			  'port' => 5222,
			  'user' => 'update',
			  'resource' => 'uniquename',
			  'password' => 'blahblahblah'),
		);

$config['db'] = &PEAR::getStaticProperty('DB_DataObject','options');

$config['db'] =
  array('database' => 'YOU HAVE TO SET THIS IN config.php',
	    'schema_location' => INSTALLDIR . '/classes',
		'class_location' => INSTALLDIR . '/classes',
		'require_prefix' => 'classes/',
		'class_prefix' => '',
        'db_driver' => 'DB', # XXX: JanRain libs only work with DB
		'quote_identifiers' => false);

require_once(INSTALLDIR.'/config.php');
require_once(INSTALLDIR.'/lib/util.php');
require_once(INSTALLDIR.'/lib/action.php');
require_once(INSTALLDIR.'/lib/theme.php');
require_once(INSTALLDIR.'/lib/mail.php');

require_once(INSTALLDIR.'/classes/Avatar.php');
require_once(INSTALLDIR.'/classes/Notice.php');
require_once(INSTALLDIR.'/classes/Profile.php');
require_once(INSTALLDIR.'/classes/Remote_profile.php');
require_once(INSTALLDIR.'/classes/Subscription.php');
require_once(INSTALLDIR.'/classes/User.php');
require_once(INSTALLDIR.'/classes/Confirm_address.php');
require_once(INSTALLDIR.'/classes/Remember_me.php');
