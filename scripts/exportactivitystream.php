<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

$shortoptions = 'i:n:f:';
$longoptions = array('id=', 'nickname=', 'file=');

$helptext = <<<END_OF_EXPORTACTIVITYSTREAM_HELP
exportactivitystream.php [options]
Export a StatusNet user history to a file

  -i --id       ID of user to export
  -n --nickname nickname of the user to export
  -f --file     file to export to (default STDOUT)

END_OF_EXPORTACTIVITYSTREAM_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

class UserActivityStream extends AtomUserNoticeFeed
{
    function __construct($user, $indent = true)
    {
        parent::__construct($user, null, $indent);

        $subscriptions = $this->getSubscriptions();
        $subscribers   = $this->getSubscribers();
        $faves         = $this->getFaves();
        $notices       = $this->getNotices();

        $objs = array_merge($subscriptions, $subscribers, $faves, $notices);

        // Sort by create date

        usort($objs, 'UserActivityStream::compareObject');

        foreach ($objs as $obj) {
            $act = $obj->asActivity();
            $this->addEntryRaw($act->asString(false));
        }
    }

    function compareObject($a, $b)
    {
        $ac = strtotime((empty($a->created)) ? $a->modified : $a->created);
        $bc = strtotime((empty($b->created)) ? $b->modified : $b->created);

        return (($ac == $bc) ? 0 : (($ac < $bc) ? 1 : -1));
    }

    function getSubscriptions()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscriber = $this->user->id;

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscribed != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    function getSubscribers()
    {
        $subs = array();

        $sub = new Subscription();

        $sub->subscribed = $this->user->id;

        if ($sub->find()) {
            while ($sub->fetch()) {
                if ($sub->subscriber != $this->user->id) {
                    $subs[] = clone($sub);
                }
            }
        }

        return $subs;
    }

    function getFaves()
    {
        $faves = array();

        $fave = new Fave();

        $fave->user_id = $this->user->id;

        if ($fave->find()) {
            while ($fave->fetch()) {
                $faves[] = clone($fave);
            }
        }

        return $faves;
    }

    function getNotices()
    {
        $notices = array();

        $notice = new Notice();

        $notice->profile_id = $this->user->id;

        if ($notice->find()) {
            while ($notice->fetch()) {
                $notices[] = clone($notice);
            }
        }

        return $notices;
    }
}

function getUser()
{
    $user = null;

    if (have_option('i', 'id')) {
        $id = get_option_value('i', 'id');
        $user = User::staticGet('id', $id);
        if (empty($user)) {
            throw new Exception("Can't find user with id '$id'.");
        }
    } else if (have_option('n', 'nickname')) {
        $nickname = get_option_value('n', 'nickname');
        $user = User::staticGet('nickname', $nickname);
        if (empty($user)) {
            throw new Exception("Can't find user with nickname '$nickname'");
        }
    } else {
        show_help();
        exit(1);
    }

    return $user;
}

try {
    $user = getUser();
    $actstr = new UserActivityStream($user);
    print $actstr->getString();
} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
