<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to use bit.ly URL shortening services.
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2009 Free Software Foundation, Inc http://www.fsf.org
 * @copyright 2010 StatusNet, Inc http://status.net/
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/UrlShortener/UrlShortenerPlugin.php';

class BitlyUrlPlugin extends UrlShortenerPlugin
{
    public $shortenerName = 'bit.ly';
    public $serviceUrl = 'http://bit.ly/api?method=shorten&version=2.0.1&longUrl=%s';
    public $login;
    public $apiKey;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->serviceUrl)){
            throw new Exception(_m("You must specify a serviceUrl for bit.ly shortening."));
        }
        if(!isset($this->login)){
            throw new Exception(_m("You must specify a login name for bit.ly shortening."));
        }
        if(!isset($this->login)){
            throw new Exception(_m("You must specify an API key for bit.ly shortening."));
        }
    }

    /**
     * Short a URL
     * @param url
     * @return string shortened version of the url, or null if URL shortening failed
     */
    protected function shorten($url) {
        $response = $this->query($url);
        if ($this->isOk($url, $response)) {
            return $this->decode($url, $response->getBody());
        } else {
            return null;
        }
    }

    /**
     * Inject API key into query before sending out...
     *
     * @param string $url
     * @return HTTPResponse
     */
    protected function query($url)
    {
        // http://code.google.com/p/bitly-api/wiki/ApiDocumentation#/shorten
        $params = http_build_query(array(
            'login' => $this->login,
            'apiKey' => $this->apiKey), '', '&');
        $serviceUrl = sprintf($this->serviceUrl, $url) . '&' . $params;

        $request = HTTPClient::start();
        return $request->get($serviceUrl);
    }

    /**
     * JSON decode for API result
     */
    protected function decode($url, $body)
    {
        $json = json_decode($body, true);
        return $json['results'][$url]['shortUrl'];
    }

    /**
     * JSON decode for API result
     */
    protected function isOk($url, $response)
    {
        $code = 'unknown';
        $msg = '';
        if ($response->isOk()) {
            $body = $response->getBody();
            common_log(LOG_INFO, $body);
            $json = json_decode($body, true);
            if ($json['statusCode'] == 'OK') {
                $data = $json['results'][$url];
                if (isset($data['shortUrl'])) {
                    return true;
                } else if (isset($data['statusCode']) && $data['statusCode'] == 'ERROR') {
                    $code = $data['errorCode'];
                    $msg = $data['errorMessage'];
                }
            } else if ($json['statusCode'] == 'ERROR') {
                $code = $json['errorCode'];
                $msg = $json['errorMessage'];
            }
            common_log(LOG_ERR, "bit.ly returned error $code $msg for $url");
        }
        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => sprintf('BitlyUrl (%s)', $this->shortenerName),
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews, Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:BitlyUrl',
                            'rawdescription' =>
                            sprintf(_m('Uses <a href="http://%1$s/">%1$s</a> URL-shortener service.'),
                                    $this->shortenerName));

        return true;
    }
}
