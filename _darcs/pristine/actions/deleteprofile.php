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

class DeleteprofileAction extends Action
{
    function handle($args)
    {
        parent::handle($args);
        $this->server_error(_('Code not yet ready.'));
        return;
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $this->handle_post();
        }
        else if ('GET' === $_SERVER['REQUEST_METHOD']) {
            $this->show_form();
        }
    }

    function get_instructions()
    {
        return _('Export and delete your user information.');
    }

    function form_header($title, $msg=null, $success=false)
    {
        common_show_header($title,
                           null,
                           array($msg, $success),
                           array($this, 'show_top'));
    }

    function show_feeds_list($feeds)
    {
        common_element_start('div', array('class' => 'feedsdel'));
        common_element('p', null, 'Feeds:');
        common_element_start('ul', array('class' => 'xoxo'));

        foreach ($feeds as $key => $value) {
            $this->common_feed_item($feeds[$key]);
        }
        common_element_end('ul');
        common_element_end('div');
    }

    //TODO move to common.php (and retrace its origin)
    function common_feed_item($feed)
    {
        $user = common_current_user();
        $nickname = $user->nickname;

        switch($feed['item']) {
            case 'notices': default:
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's ".$feed['version']." notice feed";
                $feed['textContent'] = "RSS";
                break;

            case 'foaf':
                $feed_classname = "foaf";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's FOAF file";
                $feed['textContent'] = "FOAF";
                break;
        }
        common_element_start('li');
        common_element('a', array('href' => $feed['href'],
                                  'class' => $feed_classname,
                                  'type' => $feed_mimetype,
                                  'title' => $feed_title),
                            $feed['textContent']);
        common_element_end('li');
    }

    function show_form($msg=null, $success=false)
    {
        $this->form_header(_('Delete my account'), $msg, $success);
        common_element('h2', null, _('Delete my account confirmation'));
        $this->show_confirm_delete_form();
        common_show_footer();
    }

    function show_confirm_delete_form()
    {
        $user = common_current_user();
        $notices = DB_DataObject::factory('notice');
        $notices->profile_id = $user->id;
        $notice_count = (int) $notices->count();

        common_element_start('form', array('method' => 'POST',
                                           'id' => 'delete',
                                           'action' =>
                                           common_local_url('deleteprofile')));

        common_hidden('token', common_session_token());
        common_element('p', null, "Last chance to copy your notices and contacts by saving the two links below before deleting your account. Be careful, this operation cannot be undone.");

        $this->show_feeds_list(array(0=>array('href'=>common_local_url('userrss', array('limit' => $notice_count, 'nickname' => $user->nickname)),
                                              'type' => 'rss',
                                              'version' => 'RSS 1.0',
                                              'item' => 'notices'),
                                     1=>array('href'=>common_local_url('foaf',array('nickname' => $user->nickname)),
                                              'type' => 'rdf',
                                              'version' => 'FOAF',
                                              'item' => 'foaf')));

        common_checkbox('confirmation', _('Check if you are sure you want to delete your account.'));

        common_submit('deleteaccount', _('Delete my account'));
        common_element_end('form');
    }

    function handle_post()
    {
        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if ($this->arg('deleteaccount') && $this->arg('confirmation')) {
            $this->delete_account();
        }
        $this->show_form();
    }

    function delete_account()
    {
        $user = common_current_user();
        assert(!is_null($user)); # should already be checked

        // deleted later through the profile
        /*
        $avatar = new Avatar;
        $avatar->profile_id = $user->id;
        $n_avatars_deleted = $avatar->delete();
        */

        $fave = new Fave;
        $fave->user_id = $user->id;
        $n_faves_deleted = $fave->delete();

        $confirmation = new Confirm_address;
        $confirmation->user_id = $user->id;
        $n_confirmations_deleted = $confirmation->delete();

        // TODO foreign stuff...

        $invitation = new Invitation;
        $invitation->user_id = $user->id;
        $n_invitations_deleted = $invitation->delete();

        $message_from = new Message;
        $message_from->from_profile = $user->id;
        $n_messages_from_deleted = $message_from->delete();

        $message_to = new Message;
        $message_to->to_profile = $user->id;
        $n_messages_to_deleted = $message_to->delete();

        $notice_inbox = new Notice_inbox;
        $notice_inbox->user_id = $user->id;
        $n_notices_inbox_deleted = $notice_inbox->delete();

        $profile_tagger = new Profile_tag;
        $profile_tagger->tagger = $user->id;
        $n_profiles_tagger_deleted = $profile_tagger->delete();

        $profile_tagged = new Profile_tag;
        $profile_tagged->tagged = $user->id;
        $n_profiles_tagged_deleted = $profile_tagged->delete();

        $remember_me = new Remember_me;
        $remember_me->user_id = $user->id;
        $n_remember_mes_deleted = $remember_me->delete();

        $reply= new Reply;
        $reply->profile_id = $user->id;
        $n_replies_deleted = $reply->delete();

        // FIXME we're not removings replies to deleted notices.
        //       notices should take care of that themselves.

        $notice = new Notice;
        $notice->profile_id = $user->id;
        $n_notices_deleted = $notice->delete();

        $subscriber = new Subscription;
        $subscriber->subscriber = $user->id;
        $n_subscribers_deleted = $subscriber->delete();

        $subscribed = new Subscription;
        $subscribed->subscribed = $user->id;
        $n_subscribeds_deleted = $subscribed->delete();

        $user_openid = new User_openid;
        $user_openid->user_id = $user->id;
        $n_user_openids_deleted = $user_openid->delete();

        $profile = new Profile;
        $profile->id = $user->id;
        $profile->delete_avatars();
        $n_profiles_deleted = $profile->delete();
        $n_users_deleted = $user->delete();

        // logout and redirect to public
        common_set_user(null);
        common_real_login(false); # not logged in
        common_forgetme(); # don't log back in!
        common_redirect(common_local_url('public'));
    }

    function show_top($arr)
    {
        $msg = $arr[0];
        $success = $arr[1];
        if ($msg) {
            $this->message($msg, $success);
        } else {
            $inst = $this->get_instructions();
            $output = common_markup_to_html($inst);
            common_element_start('div', 'instructions');
            common_raw($output);
            common_element_end('div');
        }
        $this->settings_menu();
    }

    function settings_menu()
    {
        # action => array('prompt', 'title')
        $menu =
          array('profilesettings' =>
                array(_('Profile'),
                      _('Change your profile settings')),
                'emailsettings' =>
                array(_('Email'),
                      _('Change email handling')),
                'openidsettings' =>
                array(_('OpenID'),
                      _('Add or remove OpenIDs')),
                'smssettings' =>
                array(_('SMS'),
                      _('Updates by SMS')),
                'imsettings' =>
                array(_('IM'),
                      _('Updates by instant messenger (IM)')),
                'twittersettings' =>
                array(_('Twitter'),
                      _('Twitter integration options')),
                'othersettings' =>
                array(_('Other'),
                      _('Other options')));

        $action = $this->trimmed('action');
        common_element_start('ul', array('id' => 'nav_views'));
        foreach ($menu as $menuaction => $menudesc) {
            if ($menuaction == 'imsettings' &&
                !common_config('xmpp', 'enabled')) {
                continue;
            }
            common_menu_item(common_local_url($menuaction),
                    $menudesc[0],
                    $menudesc[1],
                    $action == $menuaction);
        }
        common_element_end('ul');
    }
}

