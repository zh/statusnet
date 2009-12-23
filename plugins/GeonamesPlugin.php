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

        try {
            $geonames = $this->getGeonames('search',
                                           array('maxRows' => 1,
                                                 'q' => $name,
                                                 'lang' => $language,
                                                 'type' => 'xml'));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for $name: " . $e->getMessage());
            return true;
        }

        $n = $geonames[0];

        $location = new Location();

        $location->lat              = (string)$n->lat;
        $location->lon              = (string)$n->lng;
        $location->names[$language] = (string)$n->name;
        $location->location_id      = (string)$n->geonameId;
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

        try {
            $geonames = $this->getGeonames('hierarchy',
                                           array('geonameId' => $id,
                                                 'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for ID $id: " . $e->getMessage());
            return false;
        }

        $parts = array();

        foreach ($geonames as $level) {
            if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                $parts[] = (string)$level->name;
            }
        }

        $last = $geonames[count($geonames)-1];

        if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
            $parts[] = (string)$last->name;
        }

        $location = new Location();

        $location->location_id      = (string)$last->geonameId;
        $location->location_ns      = self::LOCATION_NS;
        $location->lat              = (string)$last->lat;
        $location->lon              = (string)$last->lng;
        $location->names[$language] = implode(', ', array_reverse($parts));

        $this->setCache(array('id' => (string)$last->geonameId),
                        $location);

        // We're responsible for this namespace; nobody else
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

        try {
          $geonames = $this->getGeonames('findNearbyPlaceName',
                                         array('lat' => $lat,
                                               'lng' => $lon,
                                               'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for coords $lat, $lon: " . $e->getMessage());
            return true;
        }

        $n = $geonames[0];

        $parts = array();

        $location = new Location();

        $parts[] = (string)$n->name;

        if (!empty($n->adminName1)) {
            $parts[] = (string)$n->adminName1;
        }

        if (!empty($n->countryName)) {
            $parts[] = (string)$n->countryName;
        }

        $location->location_id = (string)$n->geonameId;
        $location->location_ns = self::LOCATION_NS;
        $location->lat         = (string)$lat;
        $location->lon         = (string)$lon;

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

        $id = $location->location_id;

        $n = $this->getCache(array('id' => $id,
                                   'language' => $language));

        if (!empty($n)) {
            $name = $n;
            return false;
        }

        try {
            $geonames = $this->getGeonames('hierarchy',
                                           array('geonameId' => $id,
                                                 'lang' => $language));
        } catch (Exception $e) {
            $this->log(LOG_WARNING, "Error for ID $id: " . $e->getMessage());
            return false;
        }

        $parts = array();

        foreach ($geonames as $level) {
            if (in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
                $parts[] = (string)$level->name;
            }
        }

        $last = $geonames[count($geonames)-1];

        if (!in_array($level->fcode, array('PCLI', 'ADM1', 'PPL'))) {
            $parts[] = (string)$last->name;
        }

        if (count($parts)) {
            $name = implode(', ', array_reverse($parts));
            $this->setCache(array('id' => $id,
                                  'language' => $language),
                            $name);
        }

        return false;
    }

    /**
     * Human-readable URL for a location
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

        $str = http_build_query($params, null, '&');

        return 'http://'.$this->host.'/'.$method.'?'.$str;
    }

    function getGeonames($method, $params)
    {
        $client = HTTPClient::start();

        $result = $client->get($this->wsUrl($method, $params));

        if (!$result->isOk()) {
            throw new Exception("HTTP error code " . $result->code);
        }

        $document = new SimpleXMLElement($result->getBody());

        if (empty($document)) {
            throw new Exception("No results in response");
        }

        if (isset($document->status)) {
            throw new Exception("Error #".$document->status['value']." ('".$document->status['message']."')");
        }

        // Array of elements

        return $document->geoname;
    }
}
