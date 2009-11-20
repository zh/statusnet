<?php
/**
 * Exception stating that the remote service had a failure
 *
 * This exception is raised when a remote service failed to return a valid
 * response to a request or send a valid request.
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
class OMB_RemoteServiceException extends Exception {
  public static function fromYadis($request_uri, $result) {
    if ($result->status == 200) {
        $err = 'Got wrong response ' . $result->body;
    } else {
        $err = 'Got error code ' . $result->status . ' with response ' . $result->body;
    }
    return new OMB_RemoteServiceException($request_uri . ': ' .  $err);
  }

  public static function forRequest($action_uri, $failure) {
    return new OMB_RemoteServiceException("Handler for $action_uri: " .  $failure);
  }
}
?>
