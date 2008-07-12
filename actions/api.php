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

// XXX: Not sure of terminology yet... maybe call things "api_methods" insteads of "commands"

class ApiAction extends Action {

	function handle($args) {
		parent::handle($args);

		$command = $this->arg('command');
		
		# XXX Maybe check to see if the command actually exists first
		
		if($this->requires_auth($command)) {
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
					$this->process_command($command, $nickname, $password);
				} else {
					# basic authentication failed
					common_show_basic_auth_error();		
				}			
			}
		
		} else {
			$this->process_command($command);
		}
	}
	
	# this is where we can dispatch off to api Class files
	function process_command($command, $nickname=NULL, $password=NULL) {
	
		$parts = explode('.', $command);
		$api_action = "api_$parts[0]";
		$extension = $parts[1]; # requested content type
				
		$api_actionfile = INSTALLDIR."/actions/$api_action.php";
		
		if (file_exists($api_actionfile)) {
			require_once($api_actionfile);
			$action_class = ucfirst($api_action)."Action";
			$action_obj = new $action_class();

			# need to pass off nick and password and stuff ... put in $args? constructor? 
			# pull from $_REQUEST later?
			call_user_func(array($action_obj, 'handle'), $_REQUEST);
		} else {
			
			# need appropriate API error functs
			print "\nerror!\n";
		}
	}

	# Whitelist of API methods that don't need authentication
	function requires_auth($command) {
		
		# The only command that doesn't in Twitter's API is public_timeline
		if (ereg('^public_timeline.*$', $command)) {
			return false;
		}
		return true;
	}
		
}
