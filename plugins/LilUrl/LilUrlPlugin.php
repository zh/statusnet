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

require_once(INSTALLDIR.'/lib/Shorturl_api.php');

class LilUrlPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onInitializePlugin(){
        $this->registerUrlShortener(
            'ur1.ca',
            array('freeService'=>true),
            array('LilUrl',array('http://ur1.ca/'))
        );
    }
}

class LilUrl extends ShortUrlApi
{
    protected function shorten_imp($url) {
        $data['longurl'] = $url;
        $response = $this->http_post($data);
        if (!$response) return $url;
        $y = @simplexml_load_string($response);
        if (!isset($y->body)) return $url;
        $x = $y->body->p[0]->a->attributes();
        if (isset($x['href'])) return $x['href'];
        return $url;
    }
}
