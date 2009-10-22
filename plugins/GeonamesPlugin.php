<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to convert string locations to Geonames IDs and vice versa
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to convert string locations to Geonames IDs and vice versa
 *
 * This handles most of the events that Location class emits. It uses
 * the geonames.org Web service to convert names like 'Montreal, Quebec, Canada'
 * into IDs and lat/lon pairs.
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */

class GeonamesPlugin extends Plugin
{
    const NAMESPACE = 1;

    /**
     * convert a name into a Location object
     *
     * @param string   $name      Name to convert
     * @param string   $language  ISO code for anguage the name is in
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */

    function onLocationFromName($name, $language, &$location)
    {
        $client = HTTPClient::start();

        // XXX: break down a name by commas, narrow by each

        $str = http_build_query(array('maxRows' => 1,
                                      'q' => $name,
                                      'lang' => $language,
                                      'type' => 'json'));

        $result = $client->get('http://ws.geonames.org/search?'.$str);

        if ($result->code == "200") {
            $rj = json_decode($result->body);
            if (count($rj->geonames) > 0) {
                $n = $rj->geonames[0];

                $location = new Location();

                $location->lat              = $n->lat;
                $location->lon              = $n->lng;
                $location->names[$language] = $n->name;
                $location->location_id      = $n->geonameId;
                $location->location_ns      = self::NAMESPACE;

                // handled, don't continue processing!
                return false;
            }
        }

        // Continue processing; we don't have the answer
        return true;
    }

    /**
     * convert an id into a Location object
     *
     * @param string   $id        Name to convert
     * @param string   $ns        Name to convert
     * @param string   $language  ISO code for language for results
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */

    function onLocationFromId($id, $ns, $language, &$location)
    {
        if ($ns != self::NAMESPACE) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $client = HTTPClient::start();

        $str = http_build_query(array('geonameId' => $id,
                                      'lang' => $language));

        $result = $client->get('http://ws.geonames.org/hierarchyJSON?'.$str);

        if ($result->code == "200") {

            $rj = json_decode($result->body);

            if (count($rj->geonames) > 0) {

                $parts = array();

                foreach ($rj->geonames as $level) {
                    if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                        $parts[] = $level->name;
                    }
                }

                $last = $rj->geonames[count($rj->geonames)-1];

                if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                    $parts[] = $last->name;
                }

                $location = new Location();

                $location->location_id      = $last->geonameId;
                $location->location_ns      = self::NAMESPACE;
                $location->lat              = $last->lat;
                $location->lon              = $last->lng;
                $location->names[$language] = implode(', ', array_reverse($parts));
            }
        }

        // We're responsible for this NAMESPACE; nobody else
        // can resolve it

        return false;
    }

    /**
     * convert a lat/lon pair into a Location object
     *
     * Given a lat/lon, we try to find a Location that's around
     * it or nearby. We prefer populated places (cities, towns, villages).
     *
     * @param string   $lat       Latitude
     * @param string   $lon       Longitude
     * @param string   $language  ISO code for language for results
     * @param Location &$location Location object (may be null)
     *
     * @return boolean whether to continue (results in $location)
     */

    function onLocationFromLatLon($lat, $lon, $language, &$location)
    {
        $client = HTTPClient::start();

        $str = http_build_query(array('lat' => $lat,
                                      'lng' => $lon,
                                      'lang' => $language));

        $result =
          $client->get('http://ws.geonames.org/findNearbyPlaceNameJSON?'.$str);

        if ($result->code == "200") {

            $rj = json_decode($result->body);

            if (count($rj->geonames) > 0) {

                $n = $rj->geonames[0];

                $parts = array();

                $location = new Location();

                $parts[] = $n->name;

                if (!empty($n->adminName1)) {
                    $parts[] = $n->adminName1;
                }

                if (!empty($n->countryName)) {
                    $parts[] = $n->countryName;
                }

                $location->location_id = $n->geonameId;
                $location->location_ns = self::NAMESPACE;
                $location->lat         = $lat;
                $location->lon         = $lon;

                $location->names[$language] = implode(', ', $parts);

                // Success! We handled it, so no further processing

                return false;
            }
        }

        // For some reason we don't know, so pass.

        return true;
    }

    /**
     * Human-readable name for a location
     *
     * Given a location, we try to retrieve a human-readable name
     * in the target language.
     *
     * @param Location $location Location to get the name for
     * @param string   $language ISO code for language to find name in
     * @param string   &$name    Place to put the name
     *
     * @return boolean whether to continue
     */

    function onLocationNameLanguage($location, $language, &$name)
    {
        if ($location->location_ns != self::NAMESPACE) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $client = HTTPClient::start();

        $str = http_build_query(array('geonameId' => $id,
                                      'lang' => $language));

        $result = $client->get('http://ws.geonames.org/hierarchyJSON?'.$str);

        if ($result->code == "200") {

            $rj = json_decode($result->body);

            if (count($rj->geonames) > 0) {

                $parts = array();

                foreach ($rj->geonames as $level) {
                    if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                        $parts[] = $level->name;
                    }
                }

                $last = $rj->geonames[count($rj->geonames)-1];

                if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                    $parts[] = $last->name;
                }

                if (count($parts)) {
                    $name = implode(', ', array_reverse($parts));
                    return false;
                }
            }
        }

        return true;
    }
}
