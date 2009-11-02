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

    protected function http_post($data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->service_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (($code < 200) || ($code >= 400)) return false;
        return $response;
    }

    protected function http_get($url) {
        $encoded_url = urlencode($url);
        return file_get_contents("{$this->service_url}$encoded_url");
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

