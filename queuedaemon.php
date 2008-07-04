#!/usr/bin/env php
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

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
	print "This script must be run from the command line\n";
	exit();
}

define('INSTALLDIR', dirname(__FILE__));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');

# Notices should be broadcast in 30 minutes or less

define('CLAIM_TIMEOUT', 30 * 60);

function qd_log($priority, $msg) {
	common_log($level, 'queuedaemon: '.$msg);
}

function qd_top_item() {
	
	$qi = new Queue_item();
	$qi->orderBy('created');
	$qi->whereAdd('claimed is NULL');
	
	$qi->limit(1);

	$cnt = $qi->find(TRUE);
	
	if ($cnt) {
		# XXX: potential race condition
		# can we force it to only update if claimed is still NULL
		# (or old)?
		qd_log(LOG_INFO, 'claiming queue item = ' . $qi->notice_id);
		$orig = clone($qi);
		$qi->claimed = DB_DataObject_Cast::dateTime();
		$result = $qi->update($orig);
		if ($result) {
			qd_log(LOG_INFO, 'claim succeeded.');
			return $qi;
		} else {
			qd_log(LOG_INFO, 'claim failed.');
		}
	}
	$qi = NULL;
	return NULL;
}

function qd_clear_old_claims() {
	$qi = new Queue_item();
	$qi->orderBy('created');
	$qi->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
	if ($qi->find()) {
		while ($qi->fetch()) {
			$orig = clone($qi);
			$qi->claimed = NULL;
			$qi->update($orig);
		}
	}
}

function qd_is_remote($notice) {
	$user = User::staticGet($notice->profile_id);
	return !$user;
}

$in_a_row = 0;

do {
	qd_log(LOG_INFO, 'checking for queued notices');
	$qi = qd_top_item();
	if ($qi) {
		$in_a_row++;
		qd_log(LOG_INFO, 'Got queue item #'.$in_a_row.' enqueued '.common_exact_date($qi->created));
		$notice = Notice::staticGet($qi->notice_id);
		if ($notice) {
			qd_log(LOG_INFO, 'broadcasting notice ID = ' . $notice->id);
			# XXX: what to do if broadcast fails?
			common_real_broadcast($notice, qd_is_remote($notice));
			qd_log(LOG_INFO, 'finished broadcasting notice ID = ' . $notice->id);
			$notice = NULL;
		} else {
			qd_log(LOG_WARNING, 'queue item for notice that does not exist');
		}
		$qi->delete();
		$qi = NULL;
	} else {
		# qd_clear_old_claims();
		# In busy times, sleep less
		$sleeptime = 30000000/($in_a_row+1);
		qd_log(LOG_INFO, 'sleeping ' . $sleeptime . ' microseconds');
		usleep($sleeptime);
		$in_a_row = 0;
	}
	# Clear the DB_DataObject cache so we get fresh data
	$GLOBALS['_DB_DATAOBJECT']['CACHE'] = array();
} while (true);

?>
