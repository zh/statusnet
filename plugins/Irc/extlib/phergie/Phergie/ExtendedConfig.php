<?php
/**
 * StatusNet - the distributed open-source microblogging tool
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
 *
 * Extends the configuration class (Phergie_Config) to allow passing config
 * array instead of loading from file
 *
 * @category  Phergie
 * @package   Phergie_Extended_Config
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class Phergie_Extended_Config extends Phergie_Config {
    /**
     * Incorporates an associative array of settings into the current
     * configuration settings.
     *
     * @param array $array Array of settings
     *
     * @return Phergie_Config Provides a fluent interface
     * @throws Phergie_Config_Exception
     */
    public function readArray($array) {
        $settings = $array;
        if (!is_array($settings)) {
            throw new Phergie_Config_Exception(
                'Parameter is not an array',
                Phergie_Config_Exception::ERR_ARRAY_NOT_RETURNED
            );
        }

        $this->settings += $settings;

        return $this;
    }
}
