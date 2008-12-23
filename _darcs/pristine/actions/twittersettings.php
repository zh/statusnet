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

require_once(INSTALLDIR.'/lib/settingsaction.php');

define('SUBSCRIPTIONS', 80);

class TwittersettingsAction extends SettingsAction {

    function get_instructions() {
        return _('Add your Twitter account to automatically send your notices to Twitter, ' .
            'and subscribe to Twitter friends already here.');
    }

    function show_form($msg=null, $success=false) {
        $user = common_current_user();
        $profile = $user->getProfile();
        $fuser = null;
        $flink = Foreign_link::getByUserID($user->id, 1); // 1 == Twitter

        if ($flink) {
            $fuser = $flink->getForeignUser();
        }

        $this->form_header(_('Twitter settings'), $msg, $success);
        common_element_start('form', array('method' => 'post',
                                           'id' => 'twittersettings',
                                           'action' =>
                                           common_local_url('twittersettings')));
        common_hidden('token', common_session_token());

        common_element('h2', null, _('Twitter Account'));

        if ($fuser) {
            common_element_start('p');

            common_element('span', 'twitter_user', $fuser->nickname);
            common_element('a', array('href' => $fuser->uri),  $fuser->uri);
            common_element('span', 'input_instructions',
                           _('Current verified Twitter account.'));
            common_hidden('flink_foreign_id', $flink->foreign_id);
            common_element_end('p');
            common_submit('remove', _('Remove'));
        } else {
            common_input('twitter_username', _('Twitter user name'),
                         ($this->arg('twitter_username')) ? $this->arg('twitter_username') : $profile->nickname,
                         _('No spaces, please.')); // hey, it's what Twitter says

            common_password('twitter_password', _('Twitter password'));
        }

        common_element('h2', null, _('Preferences'));

        common_checkbox('noticesync', _('Automatically send my notices to Twitter.'),
                        ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND) : true);

        common_checkbox('replysync', _('Send local "@" replies to Twitter.'),
                        ($flink) ? ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true);

        common_checkbox('friendsync', _('Subscribe to my Twitter friends here.'),
                        ($flink) ? ($flink->friendsync & FOREIGN_FRIEND_RECV) : false);

        if ($flink) {
            common_submit('save', _('Save'));
        } else {
            common_submit('add', _('Add'));
        }

        $this->show_twitter_subscriptions();

        common_element_end('form');

        common_show_footer();
    }

    function subscribed_twitter_users() {

        $current_user = common_current_user();

        $qry = 'SELECT user.* ' .
            'FROM subscription ' .
            'JOIN user ON subscription.subscribed = user.id ' .
            'JOIN foreign_link ON foreign_link.user_id = user.id ' .
            'WHERE subscriber = %d ' .
            'ORDER BY user.nickname';

        $user = new User();

        $user->query(sprintf($qry, $current_user->id));

        $users = array();

        while ($user->fetch()) {

            // Don't include the user's own self-subscription
            if ($user->id != $current_user->id) {
                $users[] = clone($user);
            }
        }

        return $users;
    }

    function show_twitter_subscriptions() {

        $friends = $this->subscribed_twitter_users();
        $friends_count = count($friends);

        if ($friends_count > 0) {

            common_element('h3', null, _('Twitter Friends'));
            common_element_start('div', array('id' => 'subscriptions'));
            common_element_start('ul', array('id' => 'subscriptions_avatars'));

            for ($i = 0; $i < min($friends_count, SUBSCRIPTIONS); $i++) {

                $other = Profile::staticGet($friends[$i]->id);

                if (!$other) {
                    common_log_db_error($subs, 'SELECT', __FILE__);
                    continue;
                }

                common_element_start('li');
                common_element_start('a', array('title' => ($other->fullname) ?
                                                $other->fullname :
                                                $other->nickname,
                                                'href' => $other->profileurl,
                                                'rel' => 'contact',
                                                'class' => 'subscription'));
                $avatar = $other->getAvatar(AVATAR_MINI_SIZE);
                common_element('img', array('src' => (($avatar) ? common_avatar_display_url($avatar) :  common_default_avatar(AVATAR_MINI_SIZE)),
                                            'width' => AVATAR_MINI_SIZE,
                                            'height' => AVATAR_MINI_SIZE,
                                            'class' => 'avatar mini',
                                            'alt' =>  ($other->fullname) ?
                                            $other->fullname :
                                            $other->nickname));
                common_element_end('a');
                common_element_end('li');

            }

            common_element_end('ul');
            common_element_end('div');

        }

        // XXX Figure out a way to show all Twitter friends... ?

        /*
        if ($subs_count > SUBSCRIPTIONS) {
            common_element_start('p', array('id' => 'subscriptions_viewall'));

            common_element('a', array('href' => common_local_url('subscriptions',
                                                                 array('nickname' => $profile->nickname)),
                                      'class' => 'moresubscriptions'),
                           _('All subscriptions'));
            common_element_end('p');
        }
        */

    }

    function handle_post() {

        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->save_preferences();
        } else if ($this->arg('add')) {
            $this->add_twitter_acct();
        } else if ($this->arg('remove')) {
            $this->remove_twitter_acct();
        } else {
            $this->show_form(_('Unexpected form submission.'));
        }
    }

    function add_twitter_acct() {

        $screen_name = $this->trimmed('twitter_username');
        $password = $this->trimmed('twitter_password');
        $noticesync = $this->boolean('noticesync');
        $replysync = $this->boolean('replysync');
        $friendsync = $this->boolean('friendsync');

        if (!Validate::string($screen_name,
                array(    'min_length' => 1,
                        'max_length' => 15,
                         'format' => VALIDATE_NUM . VALIDATE_ALPHA . '_'))) {
            $this->show_form(
                _('Username must have only numbers, upper- and lowercase letters, and underscore (_). 15 chars max.'));
            return;
        }

        if (!$this->verify_credentials($screen_name, $password)) {
            $this->show_form(_('Could not verify your Twitter credentials!'));
            return;
        }

        $twit_user = twitter_user_info($screen_name, $password);

        if (!$twit_user) {
            $this->show_form(sprintf(_('Unable to retrieve account information for "%s" from Twitter.'),
                $screen_name));
            return;
        }

        if (!save_twitter_user($twit_user->id, $screen_name)) {
            $this->show_form(_('Unable to save your Twitter settings!'));
            return;
        }

        $user = common_current_user();

        $flink = DB_DataObject::factory('foreign_link');
        $flink->user_id = $user->id;
        $flink->foreign_id = $twit_user->id;
        $flink->service = 1; // Twitter
        $flink->credentials = $password;
        $flink->created = common_sql_now();

        $this->set_flags($flink, $noticesync, $replysync, $friendsync);

        $flink_id = $flink->insert();

        if (!$flink_id) {
            common_log_db_error($flink, 'INSERT', __FILE__);
            $this->show_form(_('Unable to save your Twitter settings!'));
            return;
        }

        if ($friendsync) {
            save_twitter_friends($user, $twit_user->id, $screen_name, $password);
        }

        $this->show_form(_('Twitter settings saved.'), true);
    }

    function remove_twitter_acct() {

        $user = common_current_user();
        $flink = Foreign_link::getByUserID($user->id, 1);
        $flink_foreign_id = $this->arg('flink_foreign_id');

        # Maybe an old tab open...?
        if ($flink->foreign_id != $flink_foreign_id) {
            $this->show_form(_('That is not your Twitter account.'));
            return;
        }

        $result = $flink->delete();

        if (!$result) {
            common_log_db_error($flink, 'DELETE', __FILE__);
            common_server_error(_('Couldn\'t remove Twitter user.'));
            return;
        }

        $this->show_form(_('Twitter account removed.'), TRUE);
    }

    function save_preferences() {

        $noticesync = $this->boolean('noticesync');
        $friendsync = $this->boolean('friendsync');
        $replysync = $this->boolean('replysync');

        $user = common_current_user();

        $flink = Foreign_link::getByUserID($user->id, 1);

        if (!$flink) {
            common_log_db_error($flink, 'SELECT', __FILE__);
            $this->show_form(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        $twitter_id = $flink->foreign_id;
        $password = $flink->credentials;

        $fuser = $flink->getForeignUser();

        if (!$fuser) {
            common_log_db_error($fuser, 'SELECT', __FILE__);
            $this->show_form(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        $screen_name = $fuser->nickname;

        $original = clone($flink);
        $this->set_flags($flink, $noticesync, $replysync, $friendsync);
        $result = $flink->update($original);

        if ($result === FALSE) {
            common_log_db_error($flink, 'UPDATE', __FILE__);
            $this->show_form(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        if ($friendsync) {
            save_twitter_friends($user, $flink->foreign_id, $screen_name, $password);
        }

        $this->show_form(_('Twitter preferences saved.'));
    }

    function verify_credentials($screen_name, $password) {
        $uri = 'http://twitter.com/account/verify_credentials.json';
        $data = get_twitter_data($uri, $screen_name, $password);

        if (!$data) {
            return false;
        }

        $user = json_decode($data);

        if (!$user) {
            return false;
        }

         $twitter_id = $user->status->id;

        if ($twitter_id) {
            return $twitter_id;
        }

        return false;
    }

    function set_flags(&$flink, $noticesync, $replysync, $friendsync) {
        if ($noticesync) {
            $flink->noticesync |= FOREIGN_NOTICE_SEND;
        } else {
            $flink->noticesync &= ~FOREIGN_NOTICE_SEND;
        }

        if ($replysync) {
            $flink->noticesync |= FOREIGN_NOTICE_SEND_REPLY;
        } else {
            $flink->noticesync &= ~FOREIGN_NOTICE_SEND_REPLY;
        }

        if ($friendsync) {
            $flink->friendsync |= FOREIGN_FRIEND_RECV;
        } else {
            $flink->friendsync &= ~FOREIGN_FRIEND_RECV;
        }

        $flink->profilesync = 0;
    }

}