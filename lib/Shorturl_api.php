<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

abstract class ShortUrlApi
{
    protected $service_url;
    protected $long_limit = 27;

    function __construct($service_url)
    {
        $this->service_url = $service_url;
    }

    function shorten($url)
    {
        if ($this->is_long($url)) return $this->shorten_imp($url);
        return $url;
    }

    protected  abstract function shorten_imp($url);

    protected function is_long($url) {
        return strlen($url) >= common_config('site', 'shorturllength');
    }

    protected function http_post($data)
    {
        $request = HTTPClient::start();
        $response = $request->post($this->service_url, null, $data);
        return $response->getBody();
    }

    protected function http_get($url)
    {
        $request = HTTPClient::start();
        $response = $request->get($this->service_url . urlencode($url));
        return $response->getBody();
    }

    protected function tidy($response) {
        $response = str_replace('&nbsp;', ' ', $response);
        $config = array('output-xhtml' => true);
        $tidy = new tidy;
        $tidy->parseString($response, $config, 'utf8');
        $tidy->cleanRepair();
        return (string)$tidy;
    }
}

