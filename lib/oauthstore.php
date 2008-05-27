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

require_once(INSTALLDIR.'/lib/omb.php');

class LaconicaOAuthDataStore extends OAuthDataStore {

	# We just keep a record of who's contacted us
	
	function lookup_consumer($consumer_key) {
		$con = new Consumer('key', $consumer_key);
		if (!$con) {
			$con = new Consumer();
			$con->consumer_key = $consumer_key;
			$con->seed = common_good_rand(16);
			$con->created = DB_DataObject_Cast::dateTime();
			if (!$con->insert()) {
				return NULL;
			}
		}
		return new OAuthConsumer($con->consumer_key, '');
	}
	
	function lookup_token($consumer, $token_type, $token) {
		$t = new Token();
		$t->consumer_key = $consumer->consumer_key;
		$t->tok = $token;
		$t->type = ($token_type == 'access') ? 1 : 0;
		if ($t->find(true)) {
			return new OAuthToken($t->tok, $t->secret);
		} else {
			return NULL;
		}
	}
	
	function lookup_nonce($consumer, $token, $nonce, $timestamp) {
		$n = new Nonce();
		$n->consumer_key = $consumer->consumer_key;
		$n->tok = $token;
		$n->nonce = $nonce;
		if ($n->find(TRUE)) {
			return TRUE;
		} else {
			$n->timestamp = $timestamp;
			$n->created = DB_DataObject_Cast::dateTime();
			$n->insert();
			return FALSE;
		}
	}
	
	function fetch_request_token($consumer) {
		$t = new Token();
		$t->consumer_key = $consumer->consumer_key;
		$t->tok = common_good_rand(16);
		$t->secret = common_good_rand(16);
		$t->type = 0; # request
		$t->state = 0;
		$t->created = DB_DataObject_Cast::dateTime();
		if (!$t->insert()) {
			return NULL;
		} else {
			return new OAuthToken($t->tok, $t->secret);
		}
	}

	function fetch_access_token($token, $consumer) {
		$rt = new Token();
		$rt->consumer_key = $consumer->consumer_key;
		$rt->tok = $token;
		if ($rt->find(TRUE) && $rt->state == 1) {
			$at = new Token();
			$at->consumer_key = $consumer->consumer_key;
			$at->tok = common_good_rand(16);
			$at->secret = common_good_rand(16);
			$at->type = 1; # access
			$at->created = DB_DataObject_Cast::dateTime();
			if (!$at->insert()) {
				return NULL;
			} else {
				# burn the old one
				$orig_rt = clone($rt);
				$rt->state = 2; # used
				if (!$rt->update($orig_rt)) {
					return NULL;
				} else {
					return new OAuthToken($at->tok, $at->secret);
				}
			}
		} else {
			return NULL;
		}
	}
}
