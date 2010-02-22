<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

define('WEBFINGER_SERVICE_REL_VALUE', 'lrdd');

/**
 * Implement the webfinger protocol.
 */

class Webfinger
{
    const PROFILEPAGE = 'http://webfinger.net/rel/profile-page';
    const UPDATESFROM = 'http://schemas.google.com/g/2010#updates-from';

    /**
     * Perform a webfinger lookup given an account.
     */

    public function lookup($id)
    {
        $id = $this->normalize($id);
        list($name, $domain) = explode('@', $id);

        $links = $this->getServiceLinks($domain);
        if (!$links) {
            return false;
        }

        $services = array();
        foreach ($links as $link) {
            if ($link['template']) {
                return $this->getServiceDescription($link['template'], $id);
            }
            if ($link['href']) {
                return $this->getServiceDescription($link['href'], $id);
            }
        }
    }

    /**
     * Normalize an account ID
     */
    function normalize($id)
    {
        if (substr($id, 0, 7) == 'acct://') {
            return substr($id, 7);
        } else if (substr($id, 0, 5) == 'acct:') {
            return substr($id, 5);
        }

        return $id;
    }

    function getServiceLinks($domain)
    {
        $url = 'http://'. $domain .'/.well-known/host-meta';
        $content = $this->fetchURL($url);
        if (empty($content)) {
            common_log(LOG_DEBUG, 'Error fetching host-meta');
            return false;
        }
        $result = XRD::parse($content);

        // Ensure that the host == domain (spec may include signing later)
        if ($result->host != $domain) {
            return false;
        }

        $links = array();
        foreach ($result->links as $link) {
            if ($link['rel'] == WEBFINGER_SERVICE_REL_VALUE) {
                $links[] = $link;
            }

        }
        return $links;
    }

    function getServiceDescription($template, $id)
    {
        $url = $this->applyTemplate($template, 'acct:' . $id);

        $content = $this->fetchURL($url);

        return XRD::parse($content);
    }

    function fetchURL($url)
    {
        try {
            $client = new HTTPClient();
            $response = $client->get($url);
        } catch (HTTP_Request2_Exception $e) {
            return false;
        }

        if ($response->getStatus() != 200) {
            return false;
        }

        return $response->getBody();
    }

    function applyTemplate($template, $id)
    {
        $template = str_replace('{uri}', urlencode($id), $template);

        return $template;
    }

    function getHostMeta($domain, $template) {
        $xrd = new XRD();
        $xrd->host = $domain;
        $xrd->links[] = array('rel' => 'lrdd',
                              'template' => $template,
                              'title' => array('Resource Descriptor'));

        return $xrd->toXML();
    }
}

