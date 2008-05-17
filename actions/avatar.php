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

if (!defined('LACONICA')) { exit(1) }

class AvatarAction extends SettingsAction {

	function show_form($msg=NULL, $success=false) {
		common_show_header(_t('Avatar'));
		$this->settings_menu();
		$this->message($msg, $success);

		$user = common_current_user();
		$profile = $user->getProfile();
		$original = $profile->getOriginal();

		if ($original) {
			common_element('img', array('src' => $original->url,
										'class' => 'avatar original',
										'width' => $original->width,
										'height' => $original->height));
		}

		$avatar = $profile->getAvatar(AVATAR_PROFILE_SIZE);

		if ($avatar) {
			common_element('img', array('src' => $avatar->url,
										'class' => 'avatar profile',
										'width' => AVATAR_PROFILE_SIZE,
										'height' => AVATAR_PROFILE_SIZE));
		}

		common_start_element('form', array('enctype' => 'multipart/form-data',
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
		common_element('input', array('name' => 'submit',
									  'type' => 'submit',
									  'id' => 'submit'),
					   _t('Upload'));
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

		$filename = common_avatar_filename($user, image_type_to_extension($info[2]));
		$filepath = common_avatar_path($filename);

		if (!move_uploaded_file($_FILES['avatarfile']['tmp_name'], $filepath)) {
			@unlink($_FILES['avatarfile']['tmp_name']);
			$this->show_form(_t('System error uploading file.'));
			return;
		}

		$avatar = DB_DataObject::factory('avatar');

		$avatar->profile_id = $user->id;
		$avatar->width = $info[0];
		$avatar->height = $info[1];
		$avatar->mediatype = image_type_to_mime_type($info[2]);
		$avatar->filename = $filename;
		$avatar->original = true;
		$avatar->url = common_avatar_url($filename);

		foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
			$scaled[] = $this->scale_avatar($user, $avatar, $size);
		}

		# XXX: start a transaction here

		if (!$this->delete_old_avatars($user)) {
			@unlink($filepath);
			common_server_error(_t('Error deleting old avatars.'));
			return;
		}

		if (!$avatar->insert()) {
			@unlink($filepath);
			common_server_error(_t('Error inserting avatar.'));
			return;
		}

		foreach ($scaled as $s) {
			if (!$s->insert()) {
				common_server_error(_t('Error inserting scaled avatar.'));
				return;
			}
		}

		# XXX: end transaction here

		$this->show_form(_t('Avatar updated.'), true);
	}
	
	function scale_avatar($user, $avatar, $size) {
		$image_s = imagecreatetruecolor($size, $size);
		$image_a = $this->avatar_to_image($avatar);
		
		$square = min($avatar->width, $avatar->height);
		
		imagecopyresampled($image_s, $image_a, 0, 0, 0, 0,
						   $size, $size, $square, $square);

		$ext = ($avatar->mediattype == 'image/jpeg') ? ".jpg" : ".png";
		
		$filename = common_avatar_filename($user, $ext, $size);
		
		if ($avatar->mediatype == 'image/jpeg') {
			imagejpeg($image_s, common_avatar_path($filename));
		} else {
			imagepng($image_s, common_avatar_path($filename));
		}
		
		$scaled = DB_DataObject::factory('avatar');
		$scaled->profile_id = $avatar->profile_id;
		$scaled->width = $size;
		$scaled->height = $size;
		$scaled->original = false;
		$scaled->mediatype = ($avatar->mediattype == 'image/jpeg') ? 'image/jpeg' : 'image/png';
		$scaled->filename = $filename;
		$scaled->url = common_avatar_url($filename);
		
		return $scaled;
	}
	
	function avatar_to_image($avatar) {
		$filepath = common_avatar_path($avatar->filename);
		if ($avatar->mediatype == 'image/gif') {
			return imagecreatefromgif($filepath);
		} else if ($avatar->mediatype == 'image/jpeg') {
			return imagecreatefromjpeg($filepath);			
		} else if ($avatar->mediatype == 'image/png') {
			return imagecreatefrompng($filepath);
		} else {
			common_server_error(_t('Unsupported image type:') . $avatar->mediatype);
			return NULL;
		}
	}
	
	function delete_old_avatars($user) {
		$avatar = DB_DataObject::factory('avatar');
		$avatar->profile_id = $user->id;
		$avatar->find();
		while ($avatar->fetch()) {
			$avatar->delete();
		}
	}
}

