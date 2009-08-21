<?php
require_once 'invalidparameterexception.php';
require_once 'Validate.php';
require_once 'helper.php';

/**
 * OMB profile representation
 *
 * This class represents an OMB profile.
 *
 * Do not call the setters with null values. Instead, if you want to delete a
 * field, pass an empty string. The getters will return null for empty fields.
 *
 * PHP version 5
 *
 * LICENSE: This program is free software: you can redistribute it and/or modify
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
 * @package   OMB
 * @author    Adrian Lang <mail@adrianlang.de>
 * @copyright 2009 Adrian Lang
 * @license   http://www.gnu.org/licenses/agpl.html GNU AGPL 3.0
 **/

class OMB_Profile {
  protected $identifier_uri;
  protected $profile_url;
  protected $nickname;
  protected $license_url;
  protected $fullname;
  protected $homepage;
  protected $bio;
  protected $location;
  protected $avatar_url;

  /* The profile as OMB param array. Cached and rebuild on usage.
     false while outdated. */
  protected $param_array;

  /**
   * Constructor for OMB_Profile
   *
   * Initializes the OMB_Profile object with an identifier uri.
   *
   * @param string $identifier_uri The profile URI as defined by the OMB. A unique
   *                               and unchanging identifier for a profile.
   *
   * @access public
   */
  public function __construct($identifier_uri) {
    if (!Validate::uri($identifier_uri)) {
      throw new OMB_InvalidParameterException($identifier_uri, 'profile',
                                                'omb_listenee or omb_listener');
    }
    $this->identifier_uri = $identifier_uri;
    $this->param_array = false;
  }

  /**
   * Returns the profile as array
   *
   * The method returns an array which contains the whole profile as array. The
   * array is cached and only rebuilt on changes of the profile.
   *
   * @param bool   $force_all Specifies whether empty fields should be added to
   *                          the array as well. This is neccessary to clear
   *                          fields via updateProfile.
   *
   * @param string $prefix    The common prefix to the key for all parameters.
   *
   * @access public
   *
   * @return array The profile as parameter array
   */
  public function asParameters($prefix, $force_all = false) {
    if ($this->param_array === false) {
      $this->param_array = array('' => $this->identifier_uri);

      if ($force_all || !is_null($this->profile_url)) {
        $this->param_array['_profile'] = $this->profile_url;
      }

      if ($force_all || !is_null($this->homepage)) {
        $this->param_array['_homepage'] = $this->homepage;
      }

      if ($force_all || !is_null($this->nickname)) {
        $this->param_array['_nickname'] = $this->nickname;
      }

      if ($force_all || !is_null($this->license_url)) {
        $this->param_array['_license'] = $this->license_url;
      }

      if ($force_all || !is_null($this->fullname)) {
        $this->param_array['_fullname'] = $this->fullname;
      }

      if ($force_all || !is_null($this->bio)) {
        $this->param_array['_bio'] = $this->bio;
      }

      if ($force_all || !is_null($this->location)) {
        $this->param_array['_location'] = $this->location;
      }

      if ($force_all || !is_null($this->avatar_url)) {
        $this->param_array['_avatar'] = $this->avatar_url;
      }

    }
    $ret = array();
    foreach ($this->param_array as $k => $v) {
      $ret[$prefix . $k] = $v;
    }
    return $ret;
  }

  /**
   * Builds an OMB_Profile object from array
   *
   * The method builds an OMB_Profile object from the passed parameters array. The
   * array MUST provide a profile URI. The array fields HAVE TO be named according
   * to the OMB standard. The prefix (omb_listener or omb_listenee) is passed as a
   * parameter.
   *
   * @param string $parameters An array containing the profile parameters.
   * @param string $prefix     The common prefix of the profile parameter keys.
   *
   * @access public
   *
   * @returns OMB_Profile The built OMB_Profile.
   */
  public static function fromParameters($parameters, $prefix) {
    if (!isset($parameters[$prefix])) {
      throw new OMB_InvalidParameterException('', 'profile', $prefix);
    }

    $profile = new OMB_Profile($parameters[$prefix]);
    $profile->updateFromParameters($parameters, $prefix);
    return $profile;
  }

  /**
   * Update from array
   *
   * Updates from the passed parameters array. The array does not have to
   * provide a profile URI. The array fields HAVE TO be named according to the
   * OMB standard. The prefix (omb_listener or omb_listenee) is passed as a
   * parameter.
   *
   * @param string $parameters An array containing the profile parameters.
   * @param string $prefix     The common prefix of the profile parameter keys.
   *
   * @access public
   */
  public function updateFromParameters($parameters, $prefix) {
    if (isset($parameters[$prefix.'_profile'])) {
      $this->setProfileURL($parameters[$prefix.'_profile']);
    }

    if (isset($parameters[$prefix.'_license'])) {
      $this->setLicenseURL($parameters[$prefix.'_license']);
    }

    if (isset($parameters[$prefix.'_nickname'])) {
      $this->setNickname($parameters[$prefix.'_nickname']);
    }

    if (isset($parameters[$prefix.'_fullname'])) {
      $this->setFullname($parameters[$prefix.'_fullname']);
    }

    if (isset($parameters[$prefix.'_homepage'])) {
      $this->setHomepage($parameters[$prefix.'_homepage']);
    }

    if (isset($parameters[$prefix.'_bio'])) {
      $this->setBio($parameters[$prefix.'_bio']);
    }

    if (isset($parameters[$prefix.'_location'])) {
      $this->setLocation($parameters[$prefix.'_location']);
    }

    if (isset($parameters[$prefix.'_avatar'])) {
      $this->setAvatarURL($parameters[$prefix.'_avatar']);
    }
  }

  public function getIdentifierURI() {
    return $this->identifier_uri;
  }

  public function getProfileURL() {
    return $this->profile_url;
  }

  public function getHomepage() {
    return $this->homepage;
  }

  public function getNickname() {
    return $this->nickname;
  }

  public function getLicenseURL() {
    return $this->license_url;
  }

  public function getFullname() {
    return $this->fullname;
  }

  public function getBio() {
    return $this->bio;
  }

  public function getLocation() {
    return $this->location;
  }

  public function getAvatarURL() {
    return $this->avatar_url;
  }

  public function setProfileURL($profile_url) {
    if (!OMB_Helper::validateURL($profile_url)) {
      throw new OMB_InvalidParameterException($profile_url, 'profile',
                                    'omb_listenee_profile or omb_listener_profile');
    }
    $this->profile_url = $profile_url;
    $this->param_array = false;
  }

  public function setNickname($nickname) {
    if (!Validate::string($nickname,
                          array('min_length' => 1,
                                'max_length' => 64,
                                'format' => VALIDATE_NUM . VALIDATE_ALPHA))) {
      throw new OMB_InvalidParameterException($nickname, 'profile', 'nickname');
    }

    $this->nickname = $nickname;
    $this->param_array = false;
  }

  public function setLicenseURL($license_url) {
    if (!OMB_Helper::validateURL($license_url)) {
      throw new OMB_InvalidParameterException($license_url, 'profile',
                                    'omb_listenee_license or omb_listener_license');
    }
    $this->license_url = $license_url;
    $this->param_array = false;
  }

  public function setFullname($fullname) {
    if ($fullname === '') {
      $fullname = null;
    } elseif (!Validate::string($fullname, array('max_length' => 255))) {
      throw new OMB_InvalidParameterException($fullname, 'profile', 'fullname');
    }
    $this->fullname = $fullname;
    $this->param_array = false;
  }

  public function setHomepage($homepage) {
    if ($homepage === '') {
      $homepage = null;
    }
    $this->homepage = $homepage;
    $this->param_array = false;
  }

  public function setBio($bio) {
    if ($bio === '') {
      $bio = null;
    } elseif (!Validate::string($bio, array('max_length' => 140))) {
      throw new OMB_InvalidParameterException($bio, 'profile', 'fullname');
    }
    $this->bio = $bio;
    $this->param_array = false;
  }

  public function setLocation($location) {
    if ($location === '') {
      $location = null;
    } elseif (!Validate::string($location, array('max_length' => 255))) {
      throw new OMB_InvalidParameterException($location, 'profile', 'fullname');
    }
    $this->location = $location;
    $this->param_array = false;
  }

  public function setAvatarURL($avatar_url) {
    if ($avatar_url === '') {
      $avatar_url = null;
    } elseif (!OMB_Helper::validateURL($avatar_url)) {
      throw new OMB_InvalidParameterException($avatar_url, 'profile',
                                      'omb_listenee_avatar or omb_listener_avatar');
    }
    $this->avatar_url = $avatar_url;
    $this->param_array = false;
  }

}
?>
