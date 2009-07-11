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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/channel.php');

class Command
{

    var $user = null;

    function __construct($user=null)
    {
        $this->user = $user;
    }

    function execute($channel)
    {
        return false;
    }
}

class UnimplementedCommand extends Command
{
    function execute($channel)
    {
        $channel->error($this->user, _("Sorry, this command is not yet implemented."));
    }
}

class TrackingCommand extends UnimplementedCommand
{
}

class TrackOffCommand extends UnimplementedCommand
{
}

class TrackCommand extends UnimplementedCommand
{
    var $word = null;
    function __construct($user, $word)
    {
        parent::__construct($user);
        $this->word = $word;
    }
}

class UntrackCommand extends UnimplementedCommand
{
    var $word = null;
    function __construct($user, $word)
    {
        parent::__construct($user);
        $this->word = $word;
    }
}

class NudgeCommand extends UnimplementedCommand
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }
}

class InviteCommand extends UnimplementedCommand
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }
}

class StatsCommand extends Command
{
    function execute($channel)
    {
        $profile = $this->user->getProfile();

        $subs_count   = $profile->subscriptionCount();
        $subbed_count = $profile->subscriberCount();
        $notice_count = $profile->noticeCount();

        $channel->output($this->user, sprintf(_("Subscriptions: %1\$s\n".
                                   "Subscribers: %2\$s\n".
                                   "Notices: %3\$s"),
                                 $subs_count,
                                 $subbed_count,
                                 $notice_count));
    }
}

class FavCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {

        $recipient =
          common_relative_profile($this->user, common_canonical_nickname($this->other));

        if (!$recipient) {
            $channel->error($this->user, _('No such user.'));
            return;
        }
        $notice = $recipient->getCurrentNotice();
        if (!$notice) {
            $channel->error($this->user, _('User has no last notice'));
            return;
        }

        $fave = Fave::addNew($this->user, $notice);

        if (!$fave) {
            $channel->error($this->user, _('Could not create favorite.'));
            return;
        }

        $other = User::staticGet('id', $recipient->id);

        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                mail_notify_fave($other, $this->user, $notice);
            }
        }

        $this->user->blowFavesCache();

        $channel->output($this->user, _('Notice marked as fave.'));
    }
}

class WhoisCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        $recipient =
          common_relative_profile($this->user, common_canonical_nickname($this->other));

        if (!$recipient) {
            $channel->error($this->user, _('No such user.'));
            return;
        }

        $whois = sprintf(_("%1\$s (%2\$s)"), $recipient->nickname,
                         $recipient->profileurl);
        if ($recipient->fullname) {
            $whois .= "\n" . sprintf(_('Fullname: %s'), $recipient->fullname);
        }
        if ($recipient->location) {
            $whois .= "\n" . sprintf(_('Location: %s'), $recipient->location);
        }
        if ($recipient->homepage) {
            $whois .= "\n" . sprintf(_('Homepage: %s'), $recipient->homepage);
        }
        if ($recipient->bio) {
            $whois .= "\n" . sprintf(_('About: %s'), $recipient->bio);
        }
        $channel->output($this->user, $whois);
    }
}

class MessageCommand extends Command
{
    var $other = null;
    var $text = null;
    function __construct($user, $other, $text)
    {
        parent::__construct($user);
        $this->other = $other;
        $this->text = $text;
    }

    function execute($channel)
    {
        $other = User::staticGet('nickname', common_canonical_nickname($this->other));
        $len = mb_strlen($this->text);
        if ($len == 0) {
            $channel->error($this->user, _('No content!'));
            return;
        } else if ($len > 140) {
            $content = common_shorten_links($content);
            if (mb_strlen($content) > 140) {
                $channel->error($this->user, sprintf(_('Message too long - maximum is 140 characters, you sent %d'), $len));
                return;
            }
        }

        if (!$other) {
            $channel->error($this->user, _('No such user.'));
            return;
        } else if (!$this->user->mutuallySubscribed($other)) {
            $channel->error($this->user, _('You can\'t send a message to this user.'));
            return;
        } else if ($this->user->id == $other->id) {
            $channel->error($this->user, _('Don\'t send a message to yourself; just say it to yourself quietly instead.'));
            return;
        }
        $message = Message::saveNew($this->user->id, $other->id, $this->text, $channel->source());
        if ($message) {
            $channel->output($this->user, sprintf(_('Direct message to %s sent'), $this->other));
        } else {
            $channel->error($this->user, _('Error sending direct message.'));
        }
    }
}

class GetCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        $target_nickname = common_canonical_nickname($this->other);

        $target =
          common_relative_profile($this->user, $target_nickname);

        if (!$target) {
            $channel->error($this->user, _('No such user.'));
            return;
        }
        $notice = $target->getCurrentNotice();
        if (!$notice) {
            $channel->error($this->user, _('User has no last notice'));
            return;
        }
        $notice_content = $notice->content;

        $channel->output($this->user, $target_nickname . ": " . $notice_content);
    }
}

class SubCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {

        if (!$this->other) {
            $channel->error($this->user, _('Specify the name of the user to subscribe to'));
            return;
        }

        $result = subs_subscribe_user($this->user, $this->other);

        if ($result == 'true') {
            $channel->output($this->user, sprintf(_('Subscribed to %s'), $this->other));
        } else {
            $channel->error($this->user, $result);
        }
    }
}

class UnsubCommand extends Command
{

    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        if(!$this->other) {
            $channel->error($this->user, _('Specify the name of the user to unsubscribe from'));
            return;
        }

        $result=subs_unsubscribe_user($this->user, $this->other);

        if ($result) {
            $channel->output($this->user, sprintf(_('Unsubscribed from %s'), $this->other));
        } else {
            $channel->error($this->user, $result);
        }
    }
}

class OffCommand extends Command
{
    var $other = null;
    function __construct($user, $other=null)
    {
        parent::__construct($user);
        $this->other = $other;
    }
    function execute($channel)
    {
        if ($other) {
            $channel->error($this->user, _("Command not yet implemented."));
        } else {
            if ($channel->off($this->user)) {
                $channel->output($this->user, _('Notification off.'));
            } else {
                $channel->error($this->user, _('Can\'t turn off notification.'));
            }
        }
    }
}

class OnCommand extends Command
{
    var $other = null;
    function __construct($user, $other=null)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        if ($other) {
            $channel->error($this->user, _("Command not yet implemented."));
        } else {
            if ($channel->on($this->user)) {
                $channel->output($this->user, _('Notification on.'));
            } else {
                $channel->error($this->user, _('Can\'t turn on notification.'));
            }
        }
    }
}

class HelpCommand extends Command
{
    function execute($channel)
    {
        $channel->output($this->user,
                         _("Commands:\n".
                           "on - turn on notifications\n".
                           "off - turn off notifications\n".
                           "help - show this help\n".
                           "follow <nickname> - subscribe to user\n".
                           "leave <nickname> - unsubscribe from user\n".
                           "d <nickname> <text> - direct message to user\n".
                           "get <nickname> - get last notice from user\n".
                           "whois <nickname> - get profile info on user\n".
                           "fav <nickname> - add user's last notice as a 'fave'\n".
                           "stats - get your stats\n".
                           "stop - same as 'off'\n".
                           "quit - same as 'off'\n".
                           "sub <nickname> - same as 'follow'\n".
                           "unsub <nickname> - same as 'leave'\n".
                           "last <nickname> - same as 'get'\n".
                           "on <nickname> - not yet implemented.\n".
                           "off <nickname> - not yet implemented.\n".
                           "nudge <nickname> - not yet implemented.\n".
                           "invite <phone number> - not yet implemented.\n".
                           "track <word> - not yet implemented.\n".
                           "untrack <word> - not yet implemented.\n".
                           "track off - not yet implemented.\n".
                           "untrack all - not yet implemented.\n".
                           "tracks - not yet implemented.\n".
                           "tracking - not yet implemented.\n"));
    }
}
