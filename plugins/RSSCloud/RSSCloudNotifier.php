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

    function postUpdate($endpoint, $feed) {
        common_debug("CloudNotifier->notify: $feed");

        $params = 'url=' . urlencode($feed);

        $result = $this->httpPost($endpoint, $params);

        // XXX: Make all this use CurlClient (lib/curlclient.php)

        if ($result) {
            common_debug('RSSCloud plugin - success notifying cloud endpoint!');
        } else {
            common_debug('RSSClous plugin - failure notifying cloud endpoint!');
        }

        return $result;
    }

    function userAgent()
    {
        return 'rssCloudPlugin/' . RSSCLOUDPLUGIN_VERSION .
          ' StatusNet/' . STATUSNET_VERSION;
    }

    private function httpPost($url, $params) {

        $options = array(CURLOPT_URL            => $url,
                         CURLOPT_POST           => true,
                         CURLOPT_POSTFIELDS     => $params,
                         CURLOPT_USERAGENT      => $this->userAgent(),
                         CURLOPT_RETURNTRANSFER => true,
                         CURLOPT_FAILONERROR    => true,
                         CURLOPT_HEADER         => false,
                         CURLOPT_FOLLOWLOCATION => true,
                         CURLOPT_CONNECTTIMEOUT => 5,
                         CURLOPT_TIMEOUT        => 5);

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);

        curl_close($ch);

        if ($info['http_code'] == 200) {
            return true;
        } else {
            return false;
        }
    }

}


