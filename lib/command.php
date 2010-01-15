<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

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

class NudgeCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }
    function execute($channel)
    {
        $recipient = User::staticGet('nickname', $this->other);
        if(! $recipient){
            $channel->error($this->user, sprintf(_('Could not find a user with nickname %s'),
                               $this->other));
        }else{
            if ($recipient->id == $this->user->id) {
                $channel->error($this->user, _('It does not make a lot of sense to nudge yourself!'));
            }else{
                if ($recipient->email && $recipient->emailnotifynudge) {
                    mail_notify_nudge($this->user, $recipient);
                }
                // XXX: notify by IM
                // XXX: notify by SMS
                $channel->output($this->user, sprintf(_('Nudge sent to %s'),
                               $recipient->nickname));
            }
        }
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
        if(substr($this->other,0,1)=='#'){
            //favoriting a specific notice_id

            $notice = Notice::staticGet(substr($this->other,1));
            if (!$notice) {
                $channel->error($this->user, _('Notice with that id does not exist'));
                return;
            }
            $recipient = $notice->getProfile();
        }else{
            //favoriting a given user's last notice

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
class JoinCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {

        $nickname = common_canonical_nickname($this->other);
        $group    = User_group::staticGet('nickname', $nickname);
        $cur      = $this->user;

        if (!$group) {
            $channel->error($cur, _('No such group.'));
            return;
        }

        if ($cur->isMember($group)) {
            $channel->error($cur, _('You are already a member of that group'));
            return;
        }
        if (Group_block::isBlocked($group, $cur->getProfile())) {
          $channel->error($cur, _('You have been blocked from that group by the admin.'));
            return;
        }

        try {
            if (Event::handle('StartJoinGroup', array($group, $cur))) {
                Group_member::join($group->id, $cur->id);
                Event::handle('EndJoinGroup', array($group, $cur));
            }
        } catch (Exception $e) {
            $channel->error($cur, sprintf(_('Could not join user %s to group %s'),
                                          $cur->nickname, $group->nickname));
            return;
        }

        $channel->output($cur, sprintf(_('%s joined group %s'),
                                              $cur->nickname,
                                              $group->nickname));
    }

}
class DropCommand extends Command
{
    var $other = null;

    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {

        $nickname = common_canonical_nickname($this->other);
        $group    = User_group::staticGet('nickname', $nickname);
        $cur      = $this->user;

        if (!$group) {
            $channel->error($cur, _('No such group.'));
            return;
        }

        if (!$cur->isMember($group)) {
            $channel->error($cur, _('You are not a member of that group.'));
            return;
        }

        try {
            if (Event::handle('StartLeaveGroup', array($group, $cur))) {
                Group_member::leave($group->id, $cur->id);
                Event::handle('EndLeaveGroup', array($group, $cur));
            }
        } catch (Exception $e) {
            $channel->error($cur, sprintf(_('Could not remove user %s to group %s'),
                                          $cur->nickname, $group->nickname));
            return;
        }

        $channel->output($cur, sprintf(_('%s left group %s'),
                                              $cur->nickname,
                                              $group->nickname));
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
        }

        $this->text = common_shorten_links($this->text);

        if (Message::contentTooLong($this->text)) {
            $channel->error($this->user, sprintf(_('Message too long - maximum is %d characters, you sent %d'),
                                                 Message::maxContent(), mb_strlen($this->text)));
            return;
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
            $message->notify();
            $channel->output($this->user, sprintf(_('Direct message to %s sent'), $this->other));
        } else {
            $channel->error($this->user, _('Error sending direct message.'));
        }
    }
}

class RepeatCommand extends Command
{
    var $other = null;
    function __construct($user, $other)
    {
        parent::__construct($user);
        $this->other = $other;
    }

    function execute($channel)
    {
        if(substr($this->other,0,1)=='#'){
            //repeating a specific notice_id

            $notice = Notice::staticGet(substr($this->other,1));
            if (!$notice) {
                $channel->error($this->user, _('Notice with that id does not exist'));
                return;
            }
            $recipient = $notice->getProfile();
        }else{
            //repeating a given user's last notice

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
        }

        if($this->user->id == $notice->profile_id)
        {
            $channel->error($this->user, _('Cannot repeat your own notice'));
            return;
        }

        if ($recipient->hasRepeated($notice->id)) {
            $channel->error($this->user, _('Already repeated that notice'));
            return;
        }

        $repeat = $notice->repeat($this->user->id, $channel->source);

        if ($repeat) {
            common_broadcast_notice($repeat);
            $channel->output($this->user, sprintf(_('Notice from %s repeated'), $recipient->nickname));
        } else {
            $channel->error($this->user, _('Error repeating notice.'));
        }
    }
}

class ReplyCommand extends Command
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
        if(substr($this->other,0,1)=='#'){
            //replying to a specific notice_id

            $notice = Notice::staticGet(substr($this->other,1));
            if (!$notice) {
                $channel->error($this->user, _('Notice with that id does not exist'));
                return;
            }
            $recipient = $notice->getProfile();
        }else{
            //replying to a given user's last notice

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
        }

        $len = mb_strlen($this->text);

        if ($len == 0) {
            $channel->error($this->user, _('No content!'));
            return;
        }

        $this->text = common_shorten_links($this->text);

        if (Notice::contentTooLong($this->text)) {
            $channel->error($this->user, sprintf(_('Notice too long - maximum is %d characters, you sent %d'),
                                                 Notice::maxContent(), mb_strlen($this->text)));
            return;
        }

        $notice = Notice::saveNew($this->user->id, $this->text, $channel->source(),
                                  array('reply_to' => $notice->id));

        if ($notice) {
            $channel->output($this->user, sprintf(_('Reply to %s sent'), $recipient->nickname));
        } else {
            $channel->error($this->user, _('Error saving notice.'));
        }
        common_broadcast_notice($notice);
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

class LoginCommand extends Command
{
    function execute($channel)
    {
        $disabled = common_config('logincommand','disabled');
        $disabled = isset($disabled) && $disabled;
        if($disabled) {
            $channel->error($this->user, _('Login command is disabled'));
            return;
        }

        try {
            $login_token = Login_token::makeNew($this->user);
        } catch (Exception $e) {
            $channel->error($this->user, $e->getMessage());
        }

        $channel->output($this->user,
            sprintf(_('This link is useable only once, and is good for only 2 minutes: %s'),
                    common_local_url('otp',
                        array('user_id' => $login_token->user_id, 'token' => $login_token->token))));
    }
}

class SubscriptionsCommand extends Command
{
    function execute($channel)
    {
        $profile = $this->user->getSubscriptions(0);
        $nicknames=array();
        while ($profile->fetch()) {
            $nicknames[]=$profile->nickname;
        }
        if(count($nicknames)==0){
            $out=_('You are not subscribed to anyone.');
        }else{
            $out = ngettext('You are subscribed to this person:',
                'You are subscribed to these people:',
                count($nicknames));
            $out .= ' ';
            $out .= implode(', ',$nicknames);
        }
        $channel->output($this->user,$out);
    }
}

class SubscribersCommand extends Command
{
    function execute($channel)
    {
        $profile = $this->user->getSubscribers();
        $nicknames=array();
        while ($profile->fetch()) {
            $nicknames[]=$profile->nickname;
        }
        if(count($nicknames)==0){
            $out=_('No one is subscribed to you.');
        }else{
            $out = ngettext('This person is subscribed to you:',
                'These people are subscribed to you:',
                count($nicknames));
            $out .= ' ';
            $out .= implode(', ',$nicknames);
        }
        $channel->output($this->user,$out);
    }
}

class GroupsCommand extends Command
{
    function execute($channel)
    {
        $group = $this->user->getGroups();
        $groups=array();
        while ($group->fetch()) {
            $groups[]=$group->nickname;
        }
        if(count($groups)==0){
            $out=_('You are not a member of any groups.');
        }else{
            $out = ngettext('You are a member of this group:',
                'You are a member of these groups:',
                count($nicknames));
            $out.=implode(', ',$groups);
        }
        $channel->output($this->user,$out);
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
                           "groups - lists the groups you have joined\n".
                           "subscriptions - list the people you follow\n".
                           "subscribers - list the people that follow you\n".
                           "leave <nickname> - unsubscribe from user\n".
                           "d <nickname> <text> - direct message to user\n".
                           "get <nickname> - get last notice from user\n".
                           "whois <nickname> - get profile info on user\n".
                           "fav <nickname> - add user's last notice as a 'fave'\n".
                           "fav #<notice_id> - add notice with the given id as a 'fave'\n".
                           "repeat #<notice_id> - repeat a notice with a given id\n".
                           "repeat <nickname> - repeat the last notice from user\n".
                           "reply #<notice_id> - reply to notice with a given id\n".
                           "reply <nickname> - reply to the last notice from user\n".
                           "join <group> - join group\n".
                           "login - Get a link to login to the web interface\n".
                           "drop <group> - leave group\n".
                           "stats - get your stats\n".
                           "stop - same as 'off'\n".
                           "quit - same as 'off'\n".
                           "sub <nickname> - same as 'follow'\n".
                           "unsub <nickname> - same as 'leave'\n".
                           "last <nickname> - same as 'get'\n".
                           "on <nickname> - not yet implemented.\n".
                           "off <nickname> - not yet implemented.\n".
                           "nudge <nickname> - remind a user to update.\n".
                           "invite <phone number> - not yet implemented.\n".
                           "track <word> - not yet implemented.\n".
                           "untrack <word> - not yet implemented.\n".
                           "track off - not yet implemented.\n".
                           "untrack all - not yet implemented.\n".
                           "tracks - not yet implemented.\n".
                           "tracking - not yet implemented.\n"));
    }
}
