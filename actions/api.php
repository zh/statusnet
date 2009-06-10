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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

class ApiAction extends Action
{

    var $user;
    var $content_type;
    var $api_arg;
    var $api_method;
    var $api_action;

    function handle($args)
    {
        parent::handle($args);

        $this->api_action = $this->arg('apiaction');
        $method = $this->arg('method');
        $argument = $this->arg('argument');

        if (isset($argument)) {
            $cmdext = explode('.', $argument);
            $this->api_arg =  $cmdext[0];
            $this->api_method = $method;
            $this->content_type = strtolower($cmdext[1]);
        } else {

            # Requested format / content-type will be an extension on the method
            $cmdext = explode('.', $method);
            $this->api_method = $cmdext[0];
            $this->content_type = strtolower($cmdext[1]);
        }

        if ($this->requires_auth()) {
            if (!isset($_SERVER['PHP_AUTH_USER'])) {

                # This header makes basic auth go
                header('WWW-Authenticate: Basic realm="Laconica API"');

                # If the user hits cancel -- bam!
                $this->show_basic_auth_error();
            } else {
                $nickname = $_SERVER['PHP_AUTH_USER'];
                $password = $_SERVER['PHP_AUTH_PW'];
                $user = common_check_user($nickname, $password);

                if ($user) {
                    $this->user = $user;
                    $this->process_command();
                } else {
                    # basic authentication failed
                    common_log(LOG_WARNING, "Failed API auth attempt, nickname: $nickname.");
                    $this->show_basic_auth_error();
                }
            }
        } else {

			# Caller might give us a username even if not required
			if (isset($_SERVER['PHP_AUTH_USER'])) {
				$user = User::staticGet('nickname', $_SERVER['PHP_AUTH_USER']);
				if ($user) {
					$this->user = $user;
				}
				# Twitter doesn't throw an error if the user isn't found
			}

            $this->process_command();
        }
    }

    function process_command()
    {
        $action = "twitapi$this->api_action";
        $actionfile = INSTALLDIR."/actions/$action.php";

        if (file_exists($actionfile)) {
            require_once($actionfile);
            $action_class = ucfirst($action)."Action";
            $action_obj = new $action_class();

            if (!$action_obj->prepare($this->args)) {
                return;
            }

            if (method_exists($action_obj, $this->api_method)) {
                $apidata = array(    'content-type' => $this->content_type,
                                    'api_method' => $this->api_method,
                                    'api_arg' => $this->api_arg,
                                    'user' => $this->user);

                call_user_func(array($action_obj, $this->api_method), $_REQUEST, $apidata);
            } else {
                $this->clientError("API method not found!", $code=404);
            }
        } else {
            $this->clientError("API method not found!", $code=404);
        }
    }

    # Whitelist of API methods that don't need authentication
    function requires_auth()
    {
        static $noauth = array( 'statuses/public_timeline',
                                'statuses/show',
                                'users/show',
                                'help/test',
                                'help/downtime_schedule',
                                'laconica/version',
                                'laconica/config',
                                'laconica/wadl');

        static $bareauth = array('statuses/user_timeline',
                                 'statuses/friends_timeline',
                                 'statuses/friends',
                                 'statuses/replies',
                                 'statuses/mentions',
                                 'statuses/followers',
                                 'favorites/favorites');

        $fullname = "$this->api_action/$this->api_method";

        // If the site is "private", all API methods except laconica/config
        // need authentication
        if (common_config('site', 'private')) {
            return $fullname != 'laconica/config' || false;
        }

        if (in_array($fullname, $bareauth)) {
            # bareauth: only needs auth if without an argument or query param specifying user
            if ($this->api_arg || $this->arg('id') || is_numeric($this->arg('user_id')) || $this->arg('screen_name')) {
                return false;
            } else {
                return true;
            }
        } else if (in_array($fullname, $noauth)) {
            # noauth: never needs auth
            return false;
        } else {
            # everybody else needs auth
            return true;
        }
    }

    function show_basic_auth_error()
    {
        header('HTTP/1.1 401 Unauthorized');
        $msg = 'Could not authenticate you.';

        if ($this->content_type == 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            $this->startXML();
            $this->elementStart('hash');
            $this->element('error', null, $msg);
            $this->element('request', null, $_SERVER['REQUEST_URI']);
            $this->elementEnd('hash');
            $this->endXML();
        } else if ($this->content_type == 'json')  {
            header('Content-Type: application/json; charset=utf-8');
            $error_array = array('error' => $msg, 'request' => $_SERVER['REQUEST_URI']);
            print(json_encode($error_array));
        } else {
            header('Content-type: text/plain');
            print "$msg\n";
        }
    }

    function isReadOnly($args)
    {
        $apiaction = $args['apiaction'];
        $method = $args['method'];

        list($cmdtext, $fmt) = explode('.', $method);

        static $write_methods = array(
            'account' => array('update_location', 'update_delivery_device', 'end_session'),
            'blocks' => array('create', 'destroy'),
            'direct_messages' => array('create', 'destroy'),
            'favorites' => array('create', 'destroy'),
            'friendships' => array('create', 'destroy'),
            'help' => array(),
            'notifications' => array('follow', 'leave'),
            'statuses' => array('update', 'destroy'),
            'users' => array()
        );

        if (array_key_exists($apiaction, $write_methods)) {
            if (!in_array($cmdtext, $write_methods[$apiaction])) {
                return true;
            }
        }

        return false;
    }
}
