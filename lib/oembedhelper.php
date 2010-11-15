<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}
require_once INSTALLDIR.'/extlib/Services/oEmbed.php';


/**
 * Utility class to wrap Services_oEmbed:
 *
 * Blacklisted hosts will use an alternate lookup method:
 *  - Twitpic
 *
 * Whitelisted hosts will use known oEmbed API endpoints:
 *  - Flickr, YFrog
 *
 * Sites that provide discovery links will use them directly; a bug
 * in use of discovery links with query strings is worked around.
 *
 * Others will fall back to oohembed (unless disabled).
 * The API endpoint can be configured or disabled through config
 * as 'oohembed'/'endpoint'.
 */
class oEmbedHelper
{
    protected static $apiMap = array(
        'flickr.com' => 'http://www.flickr.com/services/oembed/',
        'yfrog.com' => 'http://www.yfrog.com/api/oembed',
    );
    protected static $functionMap = array(
        'twitpic.com' => 'oEmbedHelper::twitPic',
    );

    /**
     * Perform or fake an oEmbed lookup for the given resource.
     *
     * Some known hosts are whitelisted with API endpoints where we
     * know they exist but autodiscovery data isn't available.
     * If autodiscovery links are missing and we don't recognize the
     * host, we'll pass it to oohembed.com's public service which
     * will either proxy or fake info on a lot of sites.
     *
     * A few hosts are blacklisted due to known problems with oohembed,
     * in which case we'll look up the info another way and return
     * equivalent data.
     *
     * Throws exceptions on failure.
     *
     * @param string $url
     * @param array $params
     * @return object
     */
    public static function getObject($url, $params=array())
    {
        common_log(LOG_INFO, 'QQQ: wtf? ' . $url);
        $host = parse_url($url, PHP_URL_HOST);
        if (substr($host, 0, 4) == 'www.') {
            $host = substr($host, 4);
        }

        // Blacklist: systems with no oEmbed API of their own, which are
        // either missing from or broken on oohembed.com's proxy.
        // we know how to look data up in another way...
        if (array_key_exists($host, self::$functionMap)) {
            $func = self::$functionMap[$host];
            return call_user_func($func, $url, $params);
        }

        // Whitelist: known API endpoints for sites that don't provide discovery...
        if (array_key_exists($host, self::$apiMap)) {
            $api = self::$apiMap[$host];
            common_log(LOG_INFO, 'QQQ: going to: ' . $api);
        } else {
            $api = false;
            common_log(LOG_INFO, 'QQQ: no map for ' . $host);
        }
        return self::getObjectFrom($api, $url, $params);
    }

    /**
     * Actually do an oEmbed lookup to a particular API endpoint,
     * or to the autodiscovered target, or to oohembed.
     *
     * @param mixed $api string or false: oEmbed API endpoint URL
     * @param string $url target URL to look up info about
     * @param array $params
     * @return object
     */
    static protected function getObjectFrom($api, $url, $params=array())
    {
        $options = array();
        if ($api) {
            $options[Services_oEmbed::OPTION_API] = $api;
        }

        try {
            $oEmbed = new Services_oEmbed_Tweaked($url, $options);
        } catch (Services_oEmbed_Exception_NoSupport $e) {
            // Discovery failed... fall back to oohembed if enabled.
            $oohembed = common_config('oohembed', 'endpoint');
            if ($oohembed) {
                $options[Services_oEmbed::OPTION_API] = $oohembed;
                $oEmbed = new Services_oEmbed_Tweaked($url, $options);
            } else {
                throw $e;
            }
        }

        // And.... let's go look it up!
        return $oEmbed->getObject($params);
    }

    /**
     * Using a local function for twitpic lookups, as oohembed's adapter
     * doesn't return a valid result:
     * http://code.google.com/p/oohembed/issues/detail?id=19
     *
     * This code fetches metadata from Twitpic's own API, and attempts
     * to guess proper thumbnail size from the original's size.
     *
     * @todo respect maxwidth and maxheight params
     *
     * @param string $url
     * @param array $params
     * @return object
     */
    static function twitPic($url, $params=array())
    {
        $matches = array();
        if (preg_match('!twitpic\.com/(\w+)!', $url, $matches)) {
            $id = $matches[1];
        } else {
            throw new Exception("Invalid twitpic URL");
        }

        // Grab metadata from twitpic's API...
        // http://dev.twitpic.com/docs/2/media_show
        $data = self::json('http://api.twitpic.com/2/media/show.json',
                array('id' => $id));
        $oembed = (object)array('type' => 'photo',
                                'url' => 'http://twitpic.com/show/full/' . $data->short_id,
                                'width' => $data->width,
                                'height' => $data->height);
        if (!empty($data->message)) {
            $oembed->title = $data->message;
        }

        // Thumbnail is cropped and scaled to 150x150 box:
        // http://dev.twitpic.com/docs/thumbnails/
        $thumbSize = 150;
        $oembed->thumbnail_url = 'http://twitpic.com/show/thumb/' . $data->short_id;
        $oembed->thumbnail_width = $thumbSize;
        $oembed->thumbnail_height = $thumbSize;

        return $oembed;
    }

    /**
     * Fetch some URL and return JSON data.
     *
     * @param string $url
     * @param array $params query-string params
     * @return object
     */
    static protected function json($url, $params=array())
    {
        $data = self::http($url, $params);
        return json_decode($data);
    }

    /**
     * Hit some web API and return data on success.
     * @param string $url
     * @param array $params
     * @return string
     */
    static protected function http($url, $params=array())
    {
        $client = HTTPClient::start();
        if ($params) {
            $query = http_build_query($params, null, '&');
            if (strpos($url, '?') === false) {
                $url .= '?' . $query;
            } else {
                $url .= '&' . $query;
            }
        }
        $response = $client->get($url);
        if ($response->isOk()) {
            return $response->getBody();
        } else {
            throw new Exception('Bad HTTP response code: ' . $response->getStatus());
        }
    }
}

class Services_oEmbed_Tweaked extends Services_oEmbed
{
    protected function discover($url)
    {
        $api = parent::discover($url);
        if (strpos($api, '?') !== false) {
            // Services_oEmbed doesn't expect to find existing params
            // on its API endpoint, which may surprise you since the
            // spec says discovery URLs should include parameters... :)
            //
            // Appending a '&' on the end keeps the later-appended '?'
            // from breaking whatever the first parameters was.
            return $api . '&';
        }
        return $api;
    }

    public function getObject(array $params = array())
    {
        $api = $this->options[self::OPTION_API];
        if (strpos($api, '?') !== false) {
            // The Services_oEmbed code appends a '?' on the end, which breaks
            // the next parameter which may be something important like
            // maxwidth.
            //
            // Sticking this bogus entry into our parameters gets us past it.
            $params = array_merge(array('statusnet' => 1), $params);
        }
        return parent::getObject($params);
    }

}