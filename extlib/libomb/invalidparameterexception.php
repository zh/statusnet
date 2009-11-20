<?php
/**
 * Exception stating that a passed parameter is invalid
 *
 * This exception is raised when a parameter does not obey the OMB standard.
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
class OMB_InvalidParameterException extends Exception {
  public function __construct($value, $type, $parameter) {
    parent::__construct("Invalid value $value for parameter $parameter in $type");
  }
}
?>
