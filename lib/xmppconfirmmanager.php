<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010 StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Event handler for pushing new confirmations to Jabber users.
 * @fixme recommend redoing this on a queue-trigger model
 * @fixme expiration of old items got dropped in the past, put it back?
 */
class XmppConfirmManager extends IoManager
{

    /**
     * @return mixed XmppConfirmManager, or false if unneeded
     */
    public static function get()
    {
        if (common_config('xmpp', 'enabled')) {
            $site = common_config('site', 'server');
            return new XmppConfirmManager();
        } else {
            return false;
        }
    }

    /**
     * Tell the i/o master we need one instance for each supporting site
     * being handled in this process.
     */
    public static function multiSite()
    {
        return IoManager::INSTANCE_PER_SITE;
    }

    function __construct()
    {
        $this->site = common_config('site', 'server');
    }

    /**
     * 10 seconds? Really? That seems a bit frequent.
     */
    function pollInterval()
    {
        return 10;
    }

    /**
     * Ping!
     * @return boolean true if we found something
     */
    function poll()
    {
        $this->switchSite();
        $confirm = $this->next_confirm();
        if ($confirm) {
            $this->handle_confirm($confirm);
            return true;
        } else {
            return false;
        }
    }

    protected function handle_confirm($confirm)
    {
        require_once INSTALLDIR . '/lib/jabber.php';

        common_log(LOG_INFO, 'Sending confirmation for ' . $confirm->address);
        $user = User::staticGet($confirm->user_id);
        if (!$user) {
            common_log(LOG_WARNING, 'Confirmation for unknown user ' . $confirm->user_id);
            return;
        }
        $success = jabber_confirm_address($confirm->code,
                                          $user->nickname,
                                          $confirm->address);
        if (!$success) {
            common_log(LOG_ERR, 'Confirmation failed for ' . $confirm->address);
            # Just let the claim age out; hopefully things work then
            return;
        } else {
            common_log(LOG_INFO, 'Confirmation sent for ' . $confirm->address);
            # Mark confirmation sent; need a dupe so we don't have the WHERE clause
            $dupe = Confirm_address::staticGet('code', $confirm->code);
            if (!$dupe) {
                common_log(LOG_WARNING, 'Could not refetch confirm', __FILE__);
                return;
            }
            $orig = clone($dupe);
            $dupe->sent = $dupe->claimed;
            $result = $dupe->update($orig);
            if (!$result) {
                common_log_db_error($dupe, 'UPDATE', __FILE__);
                # Just let the claim age out; hopefully things work then
                return;
            }
        }
        return true;
    }

    protected function next_confirm()
    {
        $confirm = new Confirm_address();
        $confirm->whereAdd('claimed IS null');
        $confirm->whereAdd('sent IS null');
        # XXX: eventually we could do other confirmations in the queue, too
        $confirm->address_type = 'jabber';
        $confirm->orderBy('modified DESC');
        $confirm->limit(1);
        if ($confirm->find(true)) {
            common_log(LOG_INFO, 'Claiming confirmation for ' . $confirm->address);
                # working around some weird DB_DataObject behaviour
            $confirm->whereAdd(''); # clears where stuff
            $original = clone($confirm);
            $confirm->claimed = common_sql_now();
            $result = $confirm->update($original);
            if ($result) {
                common_log(LOG_INFO, 'Succeeded in claim! '. $result);
                return $confirm;
            } else {
                common_log(LOG_INFO, 'Failed in claim!');
                return false;
            }
        }
        return null;
    }

    protected function clear_old_confirm_claims()
    {
        $confirm = new Confirm();
        $confirm->claimed = null;
        $confirm->whereAdd('now() - claimed > '.CLAIM_TIMEOUT);
        $confirm->update(DB_DATAOBJECT_WHEREADD_ONLY);
        $confirm->free();
        unset($confirm);
    }

    /**
     * Make sure we're on the right site configuration
     */
    protected function switchSite()
    {
        if ($this->site != common_config('site', 'server')) {
            common_log(LOG_DEBUG, __METHOD__ . ": switching to site $this->site");
            $this->stats('switch');
            StatusNet::init($this->site);
        }
    }
}
