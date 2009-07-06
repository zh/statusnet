#!/usr/bin/env php
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

# Abort if called from a web server

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<ENDOFHELP
inbox_users.php <idfile>

Update users to use inbox table. Listed in an ID file, default 'ids.txt'.

ENDOFHELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

$id_file = (count($args) > 1) ? $args[0] : 'ids.txt';

common_log(LOG_INFO, 'Updating user inboxes.');

$ids = file($id_file);

foreach ($ids as $id) {

	$user = User::staticGet('id', $id);

	if (!$user) {
		common_log(LOG_WARNING, 'No such user: ' . $id);
		continue;
	}

	if ($user->inboxed) {
		common_log(LOG_WARNING, 'Already inboxed: ' . $id);
		continue;
	}

    common_log(LOG_INFO, 'Updating inbox for user ' . $user->id);

	$user->query('BEGIN');

	$old_inbox = new Notice_inbox();
	$old_inbox->user_id = $user->id;

	$result = $old_inbox->delete();

	if (is_null($result) || $result === false) {
		common_log_db_error($old_inbox, 'DELETE', __FILE__);
		continue;
	}

	$old_inbox->free();

	$inbox = new Notice_inbox();

	$result = $inbox->query('INSERT INTO notice_inbox (user_id, notice_id, created) ' .
							'SELECT ' . $user->id . ', notice.id, notice.created ' .
							'FROM subscription JOIN notice ON subscription.subscribed = notice.profile_id ' .
							'WHERE subscription.subscriber = ' . $user->id . ' ' .
							'AND notice.created >= subscription.created ' .
							'AND NOT EXISTS (SELECT user_id, notice_id ' .
							'FROM notice_inbox ' .
							'WHERE user_id = ' . $user->id . ' ' .
							'AND notice_id = notice.id) ' .
				                        'ORDER BY notice.created DESC ' .
							'LIMIT 0, 1000');

	if (is_null($result) || $result === false) {
		common_log_db_error($inbox, 'INSERT', __FILE__);
		continue;
	}

	$orig = clone($user);
	$user->inboxed = 1;
	$result = $user->update($orig);

	if (!$result) {
		common_log_db_error($user, 'UPDATE', __FILE__);
		continue;
	}

	$user->query('COMMIT');

	$inbox->free();
	unset($inbox);

	if ($cache) {
		$cache->delete(common_cache_key('user:notices_with_friends:' . $user->id));
		$cache->delete(common_cache_key('user:notices_with_friends:' . $user->id . ';last'));
	}
}
