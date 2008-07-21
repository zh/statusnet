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
		$user = common_current_user();
		$profile = $user->getProfile();
		$this->form_header(_('Profile settings'), $msg, $success);

		common_element_start('form', array('method' => 'post',
										   'id' => 'profilesettings',
										   'action' =>
										   common_local_url('profilesettings')));
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
		common_submit('submit', _('Save'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		$nickname = $this->trimmed('nickname');
		$fullname = $this->trimmed('fullname');
		$homepage = $this->trimmed('homepage');
		$bio = $this->trimmed('bio');
		$location = $this->trimmed('location');
		$autosubscribe = $this->boolean('autosubscribe');
		$language = $this->trimmed('language');
		$timezone = $this->trimmed('timezone');

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

		$user->query('COMMIT');

		common_broadcast_profile($profile);

		$this->show_form(_('Settings saved.'), TRUE);
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
}
