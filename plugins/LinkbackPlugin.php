<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to do linkbacks for notices containing links
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
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once('Auth/Yadis/Yadis.php');

define('LINKBACKPLUGIN_VERSION', '0.1');

/**
 * Plugin to do linkbacks for notices containing URLs
 *
 * After new notices are saved, we check their text for URLs. If there
 * are URLs, we test each URL to see if it supports any
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Event
 */

class LinkbackPlugin extends Plugin
{
    var $notice = null;

    function __construct()
    {
        parent::__construct();
    }

    function onEndNoticeSave($notice)
    {
        if ($notice->is_local == 1) {
            // Try to avoid actually mucking with the
            // notice content
            $c = $notice->content;
            $this->notice = $notice;
            // Ignoring results
            common_replace_urls_callback($c,
                                         array($this, 'linkbackUrl'));
        }
        return true;
    }

    function linkbackUrl($url)
    {
        $orig = $url;
        $url = htmlspecialchars_decode($orig);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'))) {
            return $orig;
        }

        // XXX: Do a HEAD first to save some time/bandwidth

        $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();

        $result = $fetcher->get($url,
                                array('User-Agent: ' . $this->userAgent(),
                                      'Accept: application/html+xml,text/html'));

        if (!in_array($result->status, array('200', '206'))) {
            return $orig;
        }

        $pb = null;
        $tb = null;

        if (array_key_exists('X-Pingback', $result->headers)) {
            $pb = $result->headers['X-Pingback'];
        } else if (preg_match('/<link rel="pingback" href="([^"]+)" ?\/?>/',
                              $result->body,
                              $match)) {
            $pb = $match[1];
        }

        if (!empty($pb)) {
            $this->pingback($result->final_url, $pb);
        } else {
            $tb = $this->getTrackback($result->body, $result->final_url);
            if (!empty($tb)) {
                $this->trackback($result->final_url, $tb);
            }
        }

        return $orig;
    }

    function pingback($url, $endpoint)
    {
        $args = array($this->notice->uri, $url);

        if (!extension_loaded('xmlrpc')) {
            if (!dl('xmlrpc.so')) {
                common_log(LOG_ERR, "Can't pingback; xmlrpc extension not available.");
            }
        }

        $request = xmlrpc_encode_request('pingback.ping', $args);
        $context = stream_context_create(array('http' => array('method' => "POST",
                                                               'header' =>
                                                               "Content-Type: text/xml\r\n".
                                                               "User-Agent: " . $this->userAgent(),
                                                               'content' => $request)));
        $file = file_get_contents($endpoint, false, $context);
        $response = xmlrpc_decode($file);
        if (xmlrpc_is_fault($response)) {
            common_log(LOG_WARNING,
                       "Pingback error for '$url' ($endpoint): ".
                       "$response[faultString] ($response[faultCode])");
        } else {
            common_log(LOG_INFO,
                       "Pingback success for '$url' ($endpoint): ".
                       "'$response'");
        }
    }

    // Largely cadged from trackback_cls.php by
    // Ran Aroussi <ran@blogish.org>, GPL2 or any later version
    // http://phptrackback.sourceforge.net/

    function getTrackback($text, $url)
    {
        if (preg_match_all('/(<rdf:RDF.*?<\/rdf:RDF>)/sm', $text, $match, PREG_SET_ORDER)) {
            for ($i = 0; $i < count($match); $i++) {
                if (preg_match('|dc:identifier="' . preg_quote($url) . '"|ms', $match[$i][1])) {
                    $rdf_array[] = trim($match[$i][1]);
                }
            }

            // Loop through the RDFs array and extract trackback URIs

            $tb_array = array(); // <- holds list of trackback URIs

            if (!empty($rdf_array)) {

                for ($i = 0; $i < count($rdf_array); $i++) {
                    if (preg_match('/trackback:ping="([^"]+)"/', $rdf_array[$i], $array)) {
                        $tb_array[] = trim($array[1]);
                        break;
                    }
                }
            }

            // Return Trackbacks

            if (empty($tb_array)) {
                return null;
            } else {
                return $tb_array[0];
            }
        }

        if (preg_match_all('/(<a[^>]*?rel=[\'"]trackback[\'"][^>]*?>)/', $text, $match)) {
            foreach ($match[1] as $atag) {
                if (preg_match('/href=[\'"]([^\'"]*?)[\'"]/', $atag, $url)) {
                    return $url[1];
                }
            }
        }

        return null;

    }

    function trackback($url, $endpoint)
    {
        $profile = $this->notice->getProfile();

        $args = array('title' => sprintf(_('%1$s\'s status on %2$s'),
                                         $profile->nickname,
                                         common_exact_date($this->notice->created)),
                      'excerpt' => $this->notice->content,
                      'url' => $this->notice->uri,
                      'blog_name' => $profile->nickname);

        $fetcher = Auth_Yadis_Yadis::getHTTPFetcher();

        $result = $fetcher->post($endpoint,
                                 http_build_query($args),
                                 array('User-Agent: ' . $this->userAgent()));

        if ($result->status != '200') {
            common_log(LOG_WARNING,
                       "Trackback error for '$url' ($endpoint): ".
                       "$result->body");
        } else {
            common_log(LOG_INFO,
                       "Trackback success for '$url' ($endpoint): ".
                       "'$result->body'");
        }
    }

    function userAgent()
    {
        return 'LinkbackPlugin/'.LINKBACKPLUGIN_VERSION .
          ' Laconica/' . LACONICA_VERSION;
    }
}
