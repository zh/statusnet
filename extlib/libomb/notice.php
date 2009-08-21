<?php
require_once 'invalidparameterexception.php';
require_once 'Validate.php';
require_once 'helper.php';

/**
 * OMB Notice representation
 *
 * This class represents an OMB notice.
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

class OMB_Notice {
  protected $author;
  protected $uri;
  protected $content;
  protected $url;
  protected $license_url; /* url is an own addition for clarification. */
  protected $seealso_url; /* url is an own addition for clarification. */
  protected $seealso_disposition;
  protected $seealso_mediatype;
  protected $seealso_license_url; /* url is an addition for clarification. */

  /* The notice as OMB param array. Cached and rebuild on usage.
     false while outdated. */
  protected $param_array;

  /**
   * Constructor for OMB_Notice
   *
   * Initializes the OMB_Notice object with author, uri and content.
   * These parameters are mandatory for postNotice.
   *
   * @param object $author  An OMB_Profile object representing the author of the
   *                        notice.
   * @param string $uri     The notice URI as defined by the OMB. A unique and
   *                        unchanging identifier for a notice.
   * @param string $content The content of the notice. 140 chars recommended,
   *                        but there is no limit.
   *
   * @access public
   */
  public function __construct($author, $uri, $content) {
    $this->content = $content;
    if (is_null($author)) {
      throw new OMB_InvalidParameterException('', 'notice', 'omb_listenee');
    }
    $this->author = $author;

    if (!Validate::uri($uri)) {
      throw new OMB_InvalidParameterException($uri, 'notice', 'omb_notice');
    }
    $this->uri = $uri;

    $this->param_array = false;
  }

  /**
   * Returns the notice as array
   *
   * The method returns an array which contains the whole notice as array. The
   * array is cached and only rebuilt on changes of the notice.
   * Empty optional values are not passed.
   *
   *  @access  public
   *  @returns array The notice as parameter array
   */
  public function asParameters() {
    if ($this->param_array !== false) {
      return $this->param_array;
    }

    $this->param_array = array(
                 'omb_notice' => $this->uri,
                 'omb_notice_content' => $this->content);

    if (!is_null($this->url))
      $this->param_array['omb_notice_url'] = $this->url;

    if (!is_null($this->license_url))
      $this->param_array['omb_notice_license'] = $this->license_url;

    if (!is_null($this->seealso_url)) {
      $this->param_array['omb_seealso'] = $this->seealso_url;

      /* This is actually a free interpretation of the OMB standard. We assume
         that additional seealso parameters are not of any use if seealso itself
         is not set. */
      if (!is_null($this->seealso_disposition))
        $this->param_array['omb_seealso_disposition'] =
                                                     $this->seealso_disposition;

      if (!is_null($this->seealso_mediatype))
        $this->param_array['omb_seealso_mediatype'] = $this->seealso_mediatype;

      if (!is_null($this->seealso_license_url))
        $this->param_array['omb_seealso_license'] = $this->seealso_license_url;
    }
    return $this->param_array;
  }

  /**
   * Builds an OMB_Notice object from array
   *
   * The method builds an OMB_Notice object from the passed parameters array.
   * The array MUST provide a notice URI and content. The array fields HAVE TO
   * be named according to the OMB standard, i. e. omb_notice_* and
   * omb_seealso_*. Values are handled as not passed if the corresponding array
   * fields are not set or the empty string.
   *
   * @param object $author     An OMB_Profile object representing the author of
   *                           the notice.
   * @param string $parameters An array containing the notice parameters.
   *
   * @access public
   *
   * @returns OMB_Notice The built OMB_Notice.
   */
  public static function fromParameters($author, $parameters) {
    $notice = new OMB_Notice($author, $parameters['omb_notice'],
                             $parameters['omb_notice_content']);

    if (isset($parameters['omb_notice_url'])) {
      $notice->setURL($parameters['omb_notice_url']);
    }

    if (isset($parameters['omb_notice_license'])) {
      $notice->setLicenseURL($parameters['omb_notice_license']);
    }

    if (isset($parameters['omb_seealso'])) {
      $notice->setSeealsoURL($parameters['omb_seealso']);
    }

    if (isset($parameters['omb_seealso_disposition'])) {
      $notice->setSeealsoDisposition($parameters['omb_seealso_disposition']);
    }

    if (isset($parameters['omb_seealso_mediatype'])) {
      $notice->setSeealsoMediatype($parameters['omb_seealso_mediatype']);
    }

    if (isset($parameters['omb_seealso_license'])) {
      $notice->setSeealsoLicenseURL($parameters['omb_seealso_license']);
    }
    return $notice;
  }

  public function getAuthor() {
    return $this->author;
  }

  public function getIdentifierURI() {
    return $this->uri;
  }

  public function getContent() {
    return $this->content;
  }

  public function getURL() {
    return $this->url;
  }

  public function getLicenseURL() {
    return $this->license_url;
  }

  public function getSeealsoURL() {
    return $this->seealso_url;
  }

  public function getSeealsoDisposition() {
    return $this->seealso_disposition;
  }

  public function getSeealsoMediatype() {
    return $this->seealso_mediatype;
  }

  public function getSeealsoLicenseURL() {
    return $this->seealso_license_url;
  }

  public function setURL($url) {
    if ($url === '') {
      $url = null;
    } elseif (!OMB_Helper::validateURL($url)) {
      throw new OMB_InvalidParameterException($url, 'notice', 'omb_notice_url');
    }
    $this->url = $url;
    $this->param_array = false;
  }

  public function setLicenseURL($license_url) {
    if ($license_url === '') {
      $license_url = null;
    } elseif (!OMB_Helper::validateURL($license_url)) {
      throw new OMB_InvalidParameterException($license_url, 'notice',
                                              'omb_notice_license');
    }
    $this->license_url = $license_url;
    $this->param_array = false;
  }

  public function setSeealsoURL($seealso_url) {
    if ($seealso_url === '') {
      $seealso_url = null;
    } elseif (!OMB_Helper::validateURL($seealso_url)) {
      throw new OMB_InvalidParameterException($seealso_url, 'notice',
                                              'omb_seealso');
    }
    $this->seealso_url = $seealso_url;
    $this->param_array = false;
  }

  public function setSeealsoDisposition($seealso_disposition) {
    if ($seealso_disposition === '') {
      $seealso_disposition = null;
    } elseif ($seealso_disposition !== 'link' && $seealso_disposition !== 'inline') {
      throw new OMB_InvalidParameterException($seealso_disposition, 'notice',
                                              'omb_seealso_disposition');
    }
    $this->seealso_disposition = $seealso_disposition;
    $this->param_array = false;
  }

  public function setSeealsoMediatype($seealso_mediatype) {
    if ($seealso_mediatype === '') {
      $seealso_mediatype = null;
    } elseif (!OMB_Helper::validateMediaType($seealso_mediatype)) {
      throw new OMB_InvalidParameterException($seealso_mediatype, 'notice',
                                              'omb_seealso_mediatype');
    }
    $this->seealso_mediatype = $seealso_mediatype;
    $this->param_array = false;
  }

  public function setSeealsoLicenseURL($seealso_license_url) {
    if ($seealso_license_url === '') {
      $seealso_license_url = null;
    } elseif (!OMB_Helper::validateURL($seealso_license_url)) {
      throw new OMB_InvalidParameterException($seealso_license_url, 'notice',
                                              'omb_seealso_license');
    }
    $this->seealso_license_url = $seealso_license_url;
    $this->param_array = false;
  }
}
?>
