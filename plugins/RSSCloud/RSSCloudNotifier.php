<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Class to ping an rssCloud endpoint when a feed has been updated
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class RSSCloudNotifier {

    function challenge($endpoint, $feed)
    {
        $code   = common_confirmation_code(128);
        $params = array('url' => $feed, 'challenge' => $code);
        $url    = $endpoint . '?' . http_build_query($params);

        try {
            $client = new HTTPClient();
            $response = $client->get($url);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_INFO, 'RSSCloud plugin - failure testing notify handler ' .
                       $endpoint . ' - '  . $e->getMessage());
            return false;
        }

        // Check response is betweet 200 and 299 and body contains challenge data

        $status = $response->getStatus();
        $body   = $response->getBody();

        if ($status >= 200 && $status < 300) {

            if (strpos($body, $code) !== false) {
                common_log(LOG_INFO, 'RSSCloud plugin - ' .
                           "success testing notify handler:  $endpoint");
                return true;
            } else {
                common_log(LOG_INFO, 'RSSCloud plugin - ' .
                          'challenge/repsonse failed for notify handler ' .
                           $endpoint);
                common_debug('body = ' . var_export($body, true));
                return false;
            }
        } else {
            common_log(LOG_INFO, 'RSSCloud plugin - ' .
                       "failure testing notify handler:  $endpoint " .
                       ' - got HTTP ' . $status);
            common_debug('body = ' . var_export($body, true));
            return false;
        }
    }

    function postUpdate($endpoint, $feed) {

        $headers  = array();
        $postdata = array('url' => $feed);

        try {
            $client = new HTTPClient();
            $response = $client->post($endpoint, $headers, $postdata);
        } catch (HTTP_Request2_Exception $e) {
            common_log(LOG_INFO, 'RSSCloud plugin - failure notifying ' .
                       $endpoint . ' that feed ' . $feed .
                       ' has changed: ' . $e->getMessage());
            return false;
        }

        $status = $response->getStatus();

        if ($status >= 200 && $status < 300) {
            common_log(LOG_INFO, 'RSSCloud plugin - success notifying ' .
                       $endpoint . ' that feed ' . $feed . ' has changed.');
            return true;
        } else {
            common_log(LOG_INFO, 'RSSCloud plugin - failure notifying ' .
                       $endpoint . ' that feed ' . $feed .
                       ' has changed: got HTTP ' . $status);
            common_debug('body = ' . var_export($response->getBody(), true));
            return false;
        }
    }

}

