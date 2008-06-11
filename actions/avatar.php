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

class AvatarAction extends SettingsAction {

	function show_form($msg=NULL, $success=false) {
		common_show_header(_t('Avatar'));
		$this->settings_menu();
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions', 
						   _t('You can upload a new "avatar" (user picture) here. ' .
							  'You can\'t edit the picture after you upload it, so you should ' .
							  'edit it in your desktop image-manipulation software first. ' .
							  'Everything works best if your picture is more or less square. '));
		}

		$user = common_current_user();
		$profile = $user->getProfile();
		$original = $profile->getOriginal();

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

		common_element_start('form', array('enctype' => 'multipart/form-data',
										   'method' => 'POST',
										   'id' => 'avatar',
										   'action' =>
										   common_local_url('avatar')));
		common_element('input', array('name' => 'MAX_FILE_SIZE',
									  'type' => 'hidden',
									  'id' => 'MAX_FILE_SIZE',
									  'value' => MAX_AVATAR_SIZE));
		common_element('input', array('name' => 'avatarfile',
									  'type' => 'file',
									  'id' => 'avatarfile'));
		common_submit('submit', _t('Upload'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		switch ($_FILES['avatarfile']['error']) {
		 case UPLOAD_ERR_OK: # success, jump out
			break;
		 case UPLOAD_ERR_INI_SIZE:
		 case UPLOAD_ERR_FORM_SIZE:
			$this->show_form(_t('That file is too big.'));
			return;
		 case UPLOAD_ERR_PARTIAL:
			@unlink($_FILES['avatarfile']['tmp_name']);
			$this->show_form(_t('Partial upload.'));
			return;
		 default:
			$this->show_form(_t('System error uploading file.'));
			return;
		}

		$info = @getimagesize($_FILES['avatarfile']['tmp_name']);

		if (!$info) {
			@unlink($_FILES['avatarfile']['tmp_name']);
			$this->show_form(_t('Not an image or corrupt file.'));
			return;
		}

		switch ($info[2]) {
		 case IMAGETYPE_GIF:
		 case IMAGETYPE_JPEG:
		 case IMAGETYPE_PNG:
			break;
		 default:
			$this->show_form(_t('Unsupported image file format.'));
			return;
		}

		$user = common_current_user();
		$profile = $user->getProfile();
		
		if ($profile->setOriginal($_FILES['avatarfile']['tmp_name'])) {
			$this->show_form(_t('Avatar updated.'), true);
		} else {
			$this->show_form(_t('Failed updating avatar.'));
		}

		@unlink($_FILES['avatarfile']['tmp_name']);
	}
}

