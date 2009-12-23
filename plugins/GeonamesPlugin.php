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
    const LOCATION_NS = 1;

    public $host     = 'ws.geonames.org';
    public $username = null;
    public $token    = null;
    public $expiry   = 7776000; // 90-day expiry

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
        $loc = $this->getCache(array('name' => $name,
                                     'language' => $language));

        if (!empty($loc)) {
            $location = $loc;
            return false;
        }

        $client = HTTPClient::start();

        // XXX: break down a name by commas, narrow by each

        $result = $client->get($this->wsUrl('search',
                                            array('maxRows' => 1,
                                                  'q' => $name,
                                                  'lang' => $language,
                                                  'type' => 'json')));

        if (!$result->isOk()) {
            $this->log(LOG_WARNING, "Error code " . $result->code .
                       " from " . $this->host . " for $name");
            return true;
        }

        $rj = json_decode($result->getBody());

        if (count($rj->geonames) <= 0) {
            $this->log(LOG_WARNING, "No results in response from " .
                       $this->host . " for $name");
            return true;
        }

        $n = $rj->geonames[0];

        $location = new Location();

        $location->lat              = $n->lat;
        $location->lon              = $n->lng;
        $location->names[$language] = $n->name;
        $location->location_id      = $n->geonameId;
        $location->location_ns      = self::LOCATION_NS;

        $this->setCache(array('name' => $name,
                              'language' => $language),
                        $location);

        // handled, don't continue processing!
        return false;
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
        if ($ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $loc = $this->getCache(array('id' => $id));

        if (!empty($loc)) {
            $location = $loc;
            return false;
        }

        $client = HTTPClient::start();

        $result = $client->get($this->wsUrl('hierarchyJSON',
                                            array('geonameId' => $id,
                                                  'lang' => $language)));

        if (!$result->isOk()) {
            $this->log(LOG_WARNING,
                       "Error code " . $result->code .
                       " from " . $this->host . " for ID $id");
            return false;
        }

        $rj = json_decode($result->getBody());

        if (count($rj->geonames) <= 0) {
            $this->log(LOG_WARNING,
                       "No results in response from " .
                       $this->host . " for ID $id");
            return false;
        }

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
        $location->location_ns      = self::LOCATION_NS;
        $location->lat              = $last->lat;
        $location->lon              = $last->lng;
        $location->names[$language] = implode(', ', array_reverse($parts));

        $this->setCache(array('id' => $last->geonameId),
                        $location);

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
        $lat = rtrim($lat, "0");
        $lon = rtrim($lon, "0");

        $loc = $this->getCache(array('lat' => $lat,
                                     'lon' => $lon));

        if (!empty($loc)) {
            $location = $loc;
            return false;
        }

        $client = HTTPClient::start();

        $result =
          $client->get($this->wsUrl('findNearbyPlaceNameJSON',
                                    array('lat' => $lat,
                                          'lng' => $lon,
                                          'lang' => $language)));

        if (!$result->isOk()) {
            $this->log(LOG_WARNING,
                       "Error code " . $result->code .
                       " from " . $this->host . " for coords $lat, $lon");
            return true;
        }

        $rj = json_decode($result->getBody());

        if (count($rj->geonames) <= 0) {
            $this->log(LOG_WARNING,
                       "No results in response from " .
                       $this->host . " for coords $lat, $lon");
            return true;
        }

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
        $location->location_ns = self::LOCATION_NS;
        $location->lat         = $lat;
        $location->lon         = $lon;

        $location->names[$language] = implode(', ', $parts);

        $this->setCache(array('lat' => $lat,
                              'lon' => $lon),
                        $location);

        // Success! We handled it, so no further processing

        return false;
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
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $n = $this->getCache(array('id' => $location->location_id,
                                   'language' => $language));

        if (!empty($n)) {
            $name = $n;
            return false;
        }

        $client = HTTPClient::start();

        $result = $client->get($this->wsUrl('hierarchyJSON',
                                            array('geonameId' => $location->location_id,
                                                  'lang' => $language)));

        if (!$result->isOk()) {
            $this->log(LOG_WARNING,
                       "Error code " . $result->code .
                       " from " . $this->host . " for ID " . $location->location_id);
            return false;
        }

        $rj = json_decode($result->getBody());

        if (count($rj->geonames) <= 0) {
            $this->log(LOG_WARNING,
                       "No results " .
                       " from " . $this->host . " for ID " . $location->location_id);
            return false;
        }

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
            $this->setCache(array('id' => $location->location_id,
                                  'language' => $language),
                            $name);
        }

        return false;
    }

    /**
     * Human-readable name for a location
     *
     * Given a location, we try to retrieve a geonames.org URL.
     *
     * @param Location $location Location to get the url for
     * @param string   &$url     Place to put the url
     *
     * @return boolean whether to continue
     */

    function onLocationUrl($location, &$url)
    {
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $url = 'http://www.geonames.org/' . $location->location_id;

        // it's been filled, so don't process further.
        return false;
    }

    /**
     * Machine-readable name for a location
     *
     * Given a location, we try to retrieve a geonames.org URL.
     *
     * @param Location $location Location to get the url for
     * @param string   &$url     Place to put the url
     *
     * @return boolean whether to continue
     */

    function onLocationRdfUrl($location, &$url)
    {
        if ($location->location_ns != self::LOCATION_NS) {
            // It's not one of our IDs... keep processing
            return true;
        }

        $url = 'http://sw.geonames.org/' . $location->location_id . '/';

        // it's been filled, so don't process further.
        return false;
    }

    function getCache($attrs)
    {
        $c = common_memcache();

        if (empty($c)) {
            return null;
        }

        $key = $this->cacheKey($attrs);

        $value = $c->get($key);

        return $value;
    }

    function setCache($attrs, $loc)
    {
        $c = common_memcache();

        if (empty($c)) {
            return null;
        }

        $key = $this->cacheKey($attrs);

        $result = $c->set($key, $loc, 0, time() + $this->expiry);

        return $result;
    }

    function cacheKey($attrs)
    {
        return common_cache_key('geonames:'.
                                implode(',', array_keys($attrs)) . ':'.
                                common_keyize(implode(',', array_values($attrs))));
    }

    function wsUrl($method, $params)
    {
        if (!empty($this->username)) {
            $params['username'] = $this->username;
        }

        if (!empty($this->token)) {
            $params['token'] = $this->token;
        }

        $str = http_build_query($params);

        return 'http://'.$this->host.'/'.$method.'?'.$str;
    }
}
