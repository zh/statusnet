<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * A queue manager interface for just doing things immediately
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 *
 * @category  QueueManager
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class UnQueueManager
{
    function enqueue($object, $queue)
    {
        $notice = $object;

        switch ($queue)
        {
         case 'omb':
            if ($this->_isLocal($notice)) {
                require_once(INSTALLDIR.'/lib/omb.php');
                omb_broadcast_notice($notice);
            }
            break;
         case 'public':
            if ($this->_isLocal($notice)) {
                require_once(INSTALLDIR.'/lib/jabber.php');
                jabber_public_notice($notice);
            }
            break;
         case 'facebook':
            if ($this->_isLocal($notice)) {
                require_once INSTALLDIR . '/lib/facebookutil.php';
                return facebookBroadcastNotice($notice);
            }
            break;
         case 'ping':
            if ($this->_isLocal($notice)) {
                require_once INSTALLDIR . '/lib/ping.php';
                return ping_broadcast_notice($notice);
            }
         case 'sms':
            require_once(INSTALLDIR.'/lib/mail.php');
            mail_broadcast_notice_sms($notice);
            break;
         case 'jabber':
            require_once(INSTALLDIR.'/lib/jabber.php');
            jabber_broadcast_notice($notice);
            break;
         case 'plugin':
            Event::handle('HandleQueuedNotice', array(&$notice));
            break;
         default:
            if (Event::handle('UnqueueHandleNotice', array(&$notice, $queue))) {
                throw new ServerException("UnQueueManager: Unknown queue: $queue");
            }
        }
    }

    function _isLocal($notice)
    {
        return ($notice->is_local == Notice::LOCAL_PUBLIC ||
                $notice->is_local == Notice::LOCAL_NONPUBLIC);
    }
}