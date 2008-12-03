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

class ProfilesettingsAction extends SettingsAction {

	function get_instructions() {
		return _('You can update your personal profile info here '.
				  'so people know more about you.');
	}

	function show_form($msg=NULL, $success=false) {
		$this->form_header(_('Profile settings'), $msg, $success);
		$this->show_settings_form();
		common_element('h2', NULL, _('Avatar'));
		$this->show_avatar_form();
		common_element('h2', NULL, _('Change password'));
		$this->show_password_form();
		common_element('h2', NULL, _('Delete my account'));
		$this->show_delete_form();
		common_show_footer();
	}

	function handle_post() {

		# CSRF protection

		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->show_form(_('There was a problem with your session token. Try again, please.'));
			return;
		}

		if ($this->arg('save')) {
			$this->save_profile();
		} else if ($this->arg('upload')) {
			$this->upload_avatar();
		} else if ($this->arg('changepass')) {
			$this->change_password();
		} else if ($this->arg('deleteaccount')) {
			$this->delete_account();
		}

	}

	function show_settings_form() {

		$user = common_current_user();
		$profile = $user->getProfile();

		common_element_start('form', array('method' => 'POST',
										   'id' => 'profilesettings',
										   'action' =>
										   common_local_url('profilesettings')));
		common_hidden('token', common_session_token());
		
		# too much common patterns here... abstractable?
		
		common_input('nickname', _('Nickname'),
					 ($this->arg('nickname')) ? $this->arg('nickname') : $profile->nickname,
					 _('1-64 lowercase letters or numbers, no punctuation or spaces'));
		common_input('fullname', _('Full name'),
					 ($this->arg('fullname')) ? $this->arg('fullname') : $profile->fullname);
		common_input('homepage', _('Homepage'),
					 ($this->arg('homepage')) ? $this->arg('homepage') : $profile->homepage,
					 _('URL of your homepage, blog, or profile on another site'));
		common_textarea('bio', _('Bio'),
						($this->arg('bio')) ? $this->arg('bio') : $profile->bio,
						_('Describe yourself and your interests in 140 chars'));
		common_input('location', _('Location'),
					 ($this->arg('location')) ? $this->arg('location') : $profile->location,
					 _('Where you are, like "City, State (or Region), Country"'));
		common_input('tags', _('Tags'),
					 ($this->arg('tags')) ? $this->arg('tags') : implode(' ', $user->getSelfTags()),
					 _('Tags for yourself (letters, numbers, -, ., and _), comma- or space- separated'));

		$language = common_language();
		common_dropdown('language', _('Language'), get_nice_language_list(), _('Preferred language'), TRUE, $language);
		$timezone = common_timezone();
		$timezones = array();
		foreach(DateTimeZone::listIdentifiers() as $k => $v) {
			$timezones[$v] = $v;
		}
		common_dropdown('timezone', _('Timezone'), $timezones, _('What timezone are you normally in?'), TRUE, $timezone);

		common_checkbox('autosubscribe', _('Automatically subscribe to whoever subscribes to me (best for non-humans)'),
						($this->arg('autosubscribe')) ? $this->boolean('autosubscribe') : $user->autosubscribe);

		common_submit('save', _('Save'));

		common_element_end('form');


	}

	function show_avatar_form() {

		$user = common_current_user();
		$profile = $user->getProfile();

		if (!$profile) {
			common_log_db_error($user, 'SELECT', __FILE__);
			$this->server_error(_('User without matching profile'));
			return;
		}
		
		$original = $profile->getOriginalAvatar();


		common_element_start('form', array('enctype' => 'multipart/form-data',
										   'method' => 'POST',
										   'id' => 'avatar',
										   'action' =>
										   common_local_url('profilesettings')));
		common_hidden('token', common_session_token());

		if ($original) {
			common_element('img', array('src' => $original->url,
										'class' => 'avatar original',
										'width' => $original->width,
										'height' => $original->height,
										'alt' => $user->nickname));
		}

		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);

		if ($avatar) {
			common_element('img', array('src' => $avatar->url,
										'class' => 'avatar profile',
										'width' => AVATAR_PROFILE_SIZE,
										'height' => AVATAR_PROFILE_SIZE,
										'alt' => $user->nickname));
		}


		common_element('input', array('name' => 'MAX_FILE_SIZE',
									  'type' => 'hidden',
									  'id' => 'MAX_FILE_SIZE',
									  'value' => MAX_AVATAR_SIZE));

		common_element_start('p');


		common_element('input', array('name' => 'avatarfile',
									  'type' => 'file',
									  'id' => 'avatarfile'));
		common_element_end('p');

		common_submit('upload', _('Upload'));
		common_element_end('form');

	}

	function show_password_form() {

		$user = common_current_user();
		common_element_start('form', array('method' => 'POST',
										   'id' => 'password',
										   'action' =>
										   common_local_url('profilesettings')));

		common_hidden('token', common_session_token());

		# Users who logged in with OpenID won't have a pwd
		if ($user->password) {
			common_password('oldpassword', _('Old password'));
		}
		common_password('newpassword', _('New password'),
						_('6 or more characters'));
		common_password('confirm', _('Confirm'),
						_('same as password above'));
		common_submit('changepass', _('Change'));
		common_element_end('form');
	}


	function show_feeds_list($feeds) {
		common_element_start('div', array('class' => 'feedsdel'));
		common_element('p', null, 'Feeds:');
		common_element_start('ul', array('class' => 'xoxo'));

		foreach ($feeds as $key => $value) {
			$this->common_feed_item($feeds[$key]);
		}
		common_element_end('ul');
		common_element_end('div');
	}

	function common_feed_item($feed) {
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

	function show_delete_form() {
		$user = common_current_user();
        $notices = DB_DataObject::factory('notice');
        $notices->profile_id = $user->id;
        $notice_count = (int) $notices->count();

		common_element_start('form', array('method' => 'POST',
										   'id' => 'delete',
										   'action' =>
										   common_local_url('profilesettings')));

		common_hidden('token', common_session_token());
        common_element('p', null, "You can copy your notices and contacts by saving the two links belowxbefore deleting your account. Be careful, this operation cannot be undone.");


		$this->show_feeds_list(array(0=>array('href'=>common_local_url('userrss', array('limit' => $notice_count, 'nickname' => $user->nickname)), 
											  'type' => 'rss',
											  'version' => 'RSS 1.0',
											  'item' => 'notices'),
									 1=>array('href'=>common_local_url('foaf',array('nickname' => $user->nickname)),
											  'type' => 'rdf',
											  'version' => 'FOAF',
											  'item' => 'foaf')));

		common_submit('deleteaccount', _('Delete my account'));
		common_element_end('form');
	}

	function save_profile() {
		$nickname = $this->trimmed('nickname');
		$fullname = $this->trimmed('fullname');
		$homepage = $this->trimmed('homepage');
		$bio = $this->trimmed('bio');
		$location = $this->trimmed('location');
		$autosubscribe = $this->boolean('autosubscribe');
		$language = $this->trimmed('language');
		$timezone = $this->trimmed('timezone');
		$tagstring = $this->trimmed('tags');
		
		# Some validation

		if (!Validate::string($nickname, array('min_length' => 1,
											   'max_length' => 64,
											   'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->show_form(_('Nickname must have only lowercase letters and numbers and no spaces.'));
			return;
		} else if (!User::allowed_nickname($nickname)) {
			$this->show_form(_('Not a valid nickname.'));
			return;
		} else if (!is_null($homepage) && (strlen($homepage) > 0) &&
				   !Validate::uri($homepage, array('allowed_schemes' => array('http', 'https')))) {
			$this->show_form(_('Homepage is not a valid URL.'));
			return;
		} else if (!is_null($fullname) && strlen($fullname) > 255) {
			$this->show_form(_('Full name is too long (max 255 chars).'));
			return;
		} else if (!is_null($bio) && strlen($bio) > 140) {
			$this->show_form(_('Bio is too long (max 140 chars).'));
			return;
		} else if (!is_null($location) && strlen($location) > 255) {
			$this->show_form(_('Location is too long (max 255 chars).'));
			return;
		}  else if (is_null($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers())) {
			$this->show_form(_('Timezone not selected.'));
			return;
		} else if ($this->nickname_exists($nickname)) {
			$this->show_form(_('Nickname already in use. Try another one.'));
			return;
        } else if (!is_null($language) && strlen($language) > 50) {
        	$this->show_form(_('Language is too long (max 50 chars).'));
			return;
		}

		if ($tagstring) {
			$tags = array_map('common_canonical_tag', preg_split('/[\s,]+/', $tagstring));
		} else {
			$tags = array();
		}
			
		foreach ($tags as $tag) {
			if (!common_valid_profile_tag($tag)) {
				$this->show_form(sprintf(_('Invalid tag: "%s"'), $tag));
				return;
			}
		}
		
		$user = common_current_user();

		$user->query('BEGIN');

		if ($user->nickname != $nickname ||
			$user->language != $language ||
			$user->timezone != $timezone) {

			common_debug('Updating user nickname from ' . $user->nickname . ' to ' . $nickname,
						 __FILE__);
			common_debug('Updating user language from ' . $user->language . ' to ' . $language,
						 __FILE__);
			common_debug('Updating user timezone from ' . $user->timezone . ' to ' . $timezone,
						 __FILE__);

			$original = clone($user);

			$user->nickname = $nickname;
			$user->language = $language;
			$user->timezone = $timezone;

			$result = $user->updateKeys($original);

			if ($result === FALSE) {
				common_log_db_error($user, 'UPDATE', __FILE__);
				common_server_error(_('Couldn\'t update user.'));
				return;
			} else {
				# Re-initialize language environment if it changed
				common_init_language();
			}
		}

		# XXX: XOR

		if ($user->autosubscribe ^ $autosubscribe) {

			$original = clone($user);

			$user->autosubscribe = $autosubscribe;

			$result = $user->update($original);

			if ($result === FALSE) {
				common_log_db_error($user, 'UPDATE', __FILE__);
				common_server_error(_('Couldn\'t update user for autosubscribe.'));
				return;
			}
		}

		$profile = $user->getProfile();

		$orig_profile = clone($profile);

		$profile->nickname = $user->nickname;
		$profile->fullname = $fullname;
		$profile->homepage = $homepage;
		$profile->bio = $bio;
		$profile->location = $location;
		$profile->profileurl = common_profile_url($nickname);

		common_debug('Old profile: ' . common_log_objstring($orig_profile), __FILE__);
		common_debug('New profile: ' . common_log_objstring($profile), __FILE__);

		$result = $profile->update($orig_profile);

		if (!$result) {
			common_log_db_error($profile, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t save profile.'));
			return;
		}

		# Set the user tags
		
		$result = $user->setSelfTags($tags);

		if (!$result) {
			common_server_error(_('Couldn\'t save tags.'));
			return;
		}
		
		$user->query('COMMIT');

		common_broadcast_profile($profile);

		$this->show_form(_('Settings saved.'), TRUE);
	}


	function upload_avatar() {
		switch ($_FILES['avatarfile']['error']) {
		 case UPLOAD_ERR_OK: # success, jump out
			break;
		 case UPLOAD_ERR_INI_SIZE:
		 case UPLOAD_ERR_FORM_SIZE:
			$this->show_form(_('That file is too big.'));
			return;
		 case UPLOAD_ERR_PARTIAL:
			@unlink($_FILES['avatarfile']['tmp_name']);
			$this->show_form(_('Partial upload.'));
			return;
		 default:
			$this->show_form(_('System error uploading file.'));
			return;
		}

		$info = @getimagesize($_FILES['avatarfile']['tmp_name']);

		if (!$info) {
			@unlink($_FILES['avatarfile']['tmp_name']);
			$this->show_form(_('Not an image or corrupt file.'));
			return;
		}

		switch ($info[2]) {
		 case IMAGETYPE_GIF:
		 case IMAGETYPE_JPEG:
		 case IMAGETYPE_PNG:
			break;
		 default:
			$this->show_form(_('Unsupported image file format.'));
			return;
		}

		$user = common_current_user();
		$profile = $user->getProfile();

		if ($profile->setOriginal($_FILES['avatarfile']['tmp_name'])) {
			$this->show_form(_('Avatar updated.'), true);
		} else {
			$this->show_form(_('Failed updating avatar.'));
		}

		@unlink($_FILES['avatarfile']['tmp_name']);
	}

	function nickname_exists($nickname) {
		$user = common_current_user();
		$other = User::staticGet('nickname', $nickname);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}

	function change_password() {

		$user = common_current_user();
		assert(!is_null($user)); # should already be checked

		# FIXME: scrub input

		$newpassword = $this->arg('newpassword');
		$confirm = $this->arg('confirm');
		$token = $this->arg('token');

		if (0 != strcmp($newpassword, $confirm)) {
			$this->show_form(_('Passwords don\'t match.'));
			return;
		}

		if ($user->password) {
			$oldpassword = $this->arg('oldpassword');

			if (!common_check_user($user->nickname, $oldpassword)) {
				$this->show_form(_('Incorrect old password'));
				return;
			}
		}

		$original = clone($user);

		$user->password = common_munge_password($newpassword, $user->id);

		$val = $user->validate();
		if ($val !== TRUE) {
			$this->show_form(_('Error saving user; invalid.'));
			return;
		}

		if (!$user->update($original)) {
			common_server_error(_('Can\'t save new password.'));
			return;
		}

		$this->show_form(_('Password saved.'), true);
	}

	function delete_account() {
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

        $notice = new Notice;
        $notice->profile_id = $user->id;
        $n_notices_deleted = $notice->delete();

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

        $reply_from = new Reply;
        $reply_from->profile_id = $user->id;
        $n_replies_from_deleted = $reply_from->delete();

        // not sure if this should be deleted...
        //TODO: test
        if (1) {
            $reply_to = new Reply;
            $reply_to->replied_id = $user->id;
            $reply_to->find();
            while ($reply_to->fetch()) {
                $str = print_r($reply_to, true);
            }
//            $n_replies_to_deleted = $reply_to->delete();
        }

        $subscriber = new Subscription;
        $subscriber->subscriber = $user->id;
        $n_subscribers_deleted = $subscriber->delete();

        $subscribed = new Subscription;
        $subscribed->subscribed = $user->id;
        $n_subscribeds_deleted = $subscribed->delete();

        $user_openid = new User_openid;
        $user_openid->user_id = $user->id;
        $n_user_openids_deleted = $user_openid->delete();

        // last steps
        if (0) {
            $profile = new Profile;
            $profile->id = $user->id;
            $profile->delete_avatars();
            $n_profiles_deleted = $profile->delete();
            $n_users_deleted = $user->delete();
        }

		$this->show_form(_("Your account has been deleted. ($str)"), true);
    }
}
