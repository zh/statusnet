<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to push RSS/Atom updates to a PubSubHubBub hub
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class SimpleUrlPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onInitializePlugin(){
        $this->registerUrlShortener(
            'is.gd',
            array(),
            array('SimpleUrl',array('http://is.gd/api.php?longurl='))
        );
        $this->registerUrlShortener(
            'snipr.com',
            array(),
            array('SimpleUrl',array('http://snipr.com/site/snip?r=simple&link='))
        );
        $this->registerUrlShortener(
            'metamark.net',
            array(),
            array('SimpleUrl',array('http://metamark.net/api/rest/simple?long_url='))
        );
        $this->registerUrlShortener(
            'tinyurl.com',
            array(),
            array('SimpleUrl',array('http://tinyurl.com/api-create.php?url='))
        );
    }
}

class SimpleUrl extends ShortUrlApi
{
    protected function shorten_imp($url) {
        $curlh = curl_init();
        curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, 20); // # seconds to wait
        curl_setopt($curlh, CURLOPT_USERAGENT, 'StatusNet');
        curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curlh, CURLOPT_URL, $this->service_url.urlencode($url));
        $short_url = curl_exec($curlh);

        curl_close($curlh);
        return $short_url;
    }
}
