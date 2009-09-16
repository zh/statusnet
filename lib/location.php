<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class for locations
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Location
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * class for locations
 *
 * @category Location
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class Location
{
    public $lat;
    public $lon;
    public $location_id;
    public $location_ns;

    var $names;

    const geonames = 1;
    const whereOnEarth = 2;

    static function fromName($name, $language=null, $location_ns=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }
        if (is_null($location_ns)) {
            $location_ns = common_config('location', 'namespace');
        }

        $location = null;

        if (Event::handle('LocationFromName', array($name, $language, $location_ns, &$location))) {

            switch ($location_ns) {
             case Location::geonames:
                return Location::fromGeonamesName($name, $language);
                break;
             case Location::whereOnEarth:
                return Location::fromWhereOnEarthName($name, $language);
                break;
            }
        }

        return $location;
    }

    static function fromGeonamesName($name, $language)
    {
        $location = null;
        $client = HTTPClient::start();

        // XXX: break down a name by commas, narrow by each

        $str = http_build_query(array('maxRows' => 1,
                                      'q' => $name,
                                      'lang' => $language,
                                      'type' => 'json'));

        $result = $client->get('http://ws.geonames.org/search?'.$str);

        if ($result->code == "200") {
            $rj = json_decode($result->body);
            if (count($rj['geonames']) > 0) {
                $n = $rj['geonames'][0];
                $location = new Location();
                $location->lat = $n->lat;
                $location->lon = $n->lon;
                $location->name = $n->name;
                $location->location_id = $n->geonameId;
                $location->location_ns = Location:geonames;
            }
        }

        return $location;
    }

    static function fromId($location_id, $location_ns = null)
    {
        if (is_null($location_ns)) {
            $location_ns = common_config('location', 'namespace');
        }
    }

    function getName($language=null)
    {
        if (is_null($language)) {
            $language = common_language();
        }

        if (array_key_exists($this->names, $language)) {
            return $this->names[$language];
        }
    }
}
