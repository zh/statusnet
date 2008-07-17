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

class ApiAction extends Action {

	var $user;
	var $content_type;
	var $api_arg;
	var $api_method;
	var $api_action;
	
	function handle($args) {
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
			#content type will be an extension on the method
			$cmdext = explode('.', $method);
			$this->api_method = $cmdext[0];
			$this->content_type = strtolower($cmdext[1]);
		}
		
		# common_debug("apiaction = $this->api_action, method = $this->api_method, argument = $this->api_arg, ctype = $this->content_type");
						
		# XXX Maybe check to see if the command actually exists first?
		if($this->requires_auth()) {
			if (!isset($_SERVER['PHP_AUTH_USER'])) {
				
				# This header makes basic auth go
				header('WWW-Authenticate: Basic realm="Laconica API');
				
				# if the user hits cancel -- bam!
				common_show_basic_auth_error();		
			} else {
				$nickname = $_SERVER['PHP_AUTH_USER'];
				$password = $_SERVER['PHP_AUTH_PW'];
				$user = common_check_user($nickname, $password);
				
				if ($user) {
					$this->user = $user;
					$this->process_command();
				} else {
					# basic authentication failed
					common_show_basic_auth_error();		
				}			
			}
		} else {
			$this->process_command();
		}	
	}
	
	function process_command() {		
		$action = "twitapi$this->api_action";
		$actionfile = INSTALLDIR."/actions/$action.php";		
		if (file_exists($actionfile)) {
			require_once($actionfile);
			$action_class = ucfirst($action)."Action";
			$action_obj = new $action_class();

			if (method_exists($action_obj, $this->api_method)) {
				
				$apidata = array(	'content-type' => $this->content_type,
									'api_method' => $this->api_method,
									'api_arg' => $this->api_arg,
									'user' => $this->user);
				
				call_user_func(array($action_obj, $this->api_method), $_REQUEST, $apidata);
				# all API methods should exit()
			}
		}
		common_user_error("API method not found!", $code=404);
	}


	# Whitelist of API methods that don't need authentication
	function requires_auth() {
		static $noauth = array(	'statuses/public_timeline', 
								'help/test', 
								'help/downtime_schedule');
		if (in_array("$this->api_action/$this->api_method", $noauth)) {
			return false;
		}		
		return true;
	}
		
}
