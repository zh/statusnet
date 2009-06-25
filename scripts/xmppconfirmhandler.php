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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'i::';
$longoptions = array('id::');

$helptext = <<<END_OF_JABBER_HELP
Daemon script for pushing new confirmations to Jabber users.

    -i --id           Identity (default none)

END_OF_JABBER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once INSTALLDIR . '/lib/jabber.php';
require_once INSTALLDIR . '/lib/xmppqueuehandler.php';

define('CLAIM_TIMEOUT', 1200);

class XmppConfirmHandler extends XmppQueueHandler
{
    var $_id = 'confirm';

    function class_name()
    {
        return 'XmppConfirmHandler';
    }

    function run()
    {
        if (!$this->start()) {
            return false;
        }
        $this->log(LOG_INFO, 'checking for queued confirmations');
        do {
            $confirm = $this->next_confirm();
            if ($confirm) {
                $this->log(LOG_INFO, 'Sending confirmation for ' . $confirm->address);
                $user = User::staticGet($confirm->user_id);
                if (!$user) {
                    $this->log(LOG_WARNING, 'Confirmation for unknown user ' . $confirm->user_id);
                    continue;
                }
                $success = jabber_confirm_address($confirm->code,
                                                  $user->nickname,
                                                  $confirm->address);
                if (!$success) {
                    $this->log(LOG_ERR, 'Confirmation failed for ' . $confirm->address);
                    # Just let the claim age out; hopefully things work then
                    continue;
                } else {
                    $this->log(LOG_INFO, 'Confirmation sent for ' . $confirm->address);
                    # Mark confirmation sent; need a dupe so we don't have the WHERE clause
                    $dupe = Confirm_address::staticGet('code', $confirm->code);
                    if (!$dupe) {
                        common_log(LOG_WARNING, 'Could not refetch confirm', __FILE__);
                        continue;
                    }
                    $orig = clone($dupe);
                    $dupe->sent = $dupe->claimed;
                    $result = $dupe->update($orig);
                    if (!$result) {
                        common_log_db_error($dupe, 'UPDATE', __FILE__);
                        # Just let the claim age out; hopefully things work then
                        continue;
                    }
                    $dupe->free();
                    unset($dupe);
                }
                $user->free();
                unset($user);
                $confirm->free();
                unset($confirm);
                $this->idle(0);
            } else {
#                $this->clear_old_confirm_claims();
                $this->idle(10);
            }
        } while (true);
        if (!$this->finish()) {
            return false;
        }
        return true;
    }

    function next_confirm()
    {
        $confirm = new Confirm_address();
        $confirm->whereAdd('claimed IS null');
        $confirm->whereAdd('sent IS null');
        # XXX: eventually we could do other confirmations in the queue, too
        $confirm->address_type = 'jabber';
        $confirm->orderBy('modified DESC');
        $confirm->limit(1);
        if ($confirm->find(true)) {
            $this->log(LOG_INFO, 'Claiming confirmation for ' . $confirm->address);
                # working around some weird DB_DataObject behaviour
            $confirm->whereAdd(''); # clears where stuff
            $original = clone($confirm);
            $confirm->claimed = common_sql_now();
            $result = $confirm->update($original);
            if ($result) {
                $this->log(LOG_INFO, 'Succeeded in claim! '. $result);
                return $confirm;
            } else {
                $this->log(LOG_INFO, 'Failed in claim!');
                return false;
            }
        }
        return null;
    }

    function clear_old_confirm_claims()
    {
        $confirm = new Confirm();
        $confirm->claimed = null;
        $confirm->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
        $confirm->update(DB_DATAOBJECT_WHEREADD_ONLY);
        $confirm->free();
        unset($confirm);
    }
}

// Abort immediately if xmpp is not enabled, otherwise the daemon chews up
// lots of CPU trying to connect to unconfigured servers
if (common_config('xmpp','enabled')==false) {
    print "Aborting daemon - xmpp is disabled\n";
    exit();
}

if (have_option('i')) {
    $id = get_option_value('i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$handler = new XmppConfirmHandler($id);

$handler->runOnce();

