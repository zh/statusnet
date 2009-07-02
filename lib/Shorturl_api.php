<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) { exit(1); }

class ShortUrlApi
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

    protected function shorten_imp($url) {
        return "To Override";
    }

    private function is_long($url) {
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

class LilUrl extends ShortUrlApi
{
    function __construct()
    {
        parent::__construct('http://ur1.ca/');
    }

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


class PtitUrl extends ShortUrlApi
{
    function __construct()
    {
        parent::__construct('http://ptiturl.com/?creer=oui&action=Reduire&url=');
    }

    protected function shorten_imp($url) {
        $response = $this->http_get($url);
        if (!$response) return $url;
        $response = $this->tidy($response);
        $y = @simplexml_load_string($response);
        if (!isset($y->body)) return $url;
        $xml = $y->body->center->table->tr->td->pre->a->attributes();
        if (isset($xml['href'])) return $xml['href'];
        return $url;
    }
}

class TightUrl extends ShortUrlApi
{
    function __construct()
    {
        parent::__construct('http://2tu.us/?save=y&url=');
    }

    protected function shorten_imp($url) {
        $response = $this->http_get($url);
        if (!$response) return $url;
        $response = $this->tidy($response);
        $y = @simplexml_load_string($response);
        if (!isset($y->body)) return $url;
        $xml = $y->body->p[0]->code[0]->a->attributes();
        if (isset($xml['href'])) return $xml['href'];
        return $url;
    }
}

