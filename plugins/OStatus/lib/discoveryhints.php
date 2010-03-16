<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Some utilities for generating hint data
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

class DiscoveryHints {

    static function fromXRD($xrd)
    {
        $hints = array();

        foreach ($xrd->links as $link) {
            switch ($link['rel']) {
            case Discovery::PROFILEPAGE:
                $hints['profileurl'] = $link['href'];
                break;
            case Salmon::NS_REPLIES:
                $hints['salmon'] = $link['href'];
                break;
            case Discovery::UPDATESFROM:
                $hints['feedurl'] = $link['href'];
                break;
            case Discovery::HCARD:
                $hints['hcardurl'] = $link['href'];
                break;
            default:
                break;
            }
        }

        return $hints;
    }

    static function fromHcardUrl($url)
    {
        $client = new HTTPClient();
        $client->setHeader('Accept', 'text/html,application/xhtml+xml');
        $response = $client->get($url);

        if (!$response->isOk()) {
            return null;
        }

        return self::hcardHints($response->getBody(),
                                $response->getUrl());
    }

    static function hcardHints($body, $url)
    {
        common_debug("starting tidy");

        $body = self::_tidy($body);

        common_debug("done with tidy");

        set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/plugins/OStatus/extlib/hkit/');
        require_once('hkit.class.php');

        $h	= new hKit;

        $hcards = $h->getByString('hcard', $body);

        if (empty($hcards)) {
            return array();
        }

        if (count($hcards) == 1) {
            $hcard = $hcards[0];
        } else {
            foreach ($hcards as $try) {
                if (array_key_exists('url', $try)) {
                    if (is_string($try['url']) && $try['url'] == $url) {
                        $hcard = $try;
                        break;
                    } else if (is_array($try['url'])) {
                        foreach ($try['url'] as $tryurl) {
                            if ($tryurl == $url) {
                                $hcard = $try;
                                break 2;
                            }
                        }
                    }
                }
            }
            // last chance; grab the first one
            if (empty($hcard)) {
                $hcard = $hcards[0];
            }
        }

        $hints = array();

        if (array_key_exists('nickname', $hcard)) {
            $hints['nickname'] = $hcard['nickname'];
        }

        if (array_key_exists('fn', $hcard)) {
            $hints['fullname'] = $hcard['fn'];
        } else if (array_key_exists('n', $hcard)) {
            $hints['fullname'] = implode(' ', $hcard['n']);
        }

        if (array_key_exists('photo', $hcard)) {
            $hints['avatar'] = $hcard['photo'];
        }

        if (array_key_exists('note', $hcard)) {
            $hints['bio'] = $hcard['note'];
        }

        if (array_key_exists('adr', $hcard)) {
            if (is_string($hcard['adr'])) {
                $hints['location'] = $hcard['adr'];
            } else if (is_array($hcard['adr'])) {
                $hints['location'] = implode(' ', $hcard['adr']);
            }
        }

        if (array_key_exists('url', $hcard)) {
            if (is_string($hcard['url'])) {
                $hints['homepage'] = $hcard['url'];
            } else if (is_array($hcard['url'])) {
                // HACK get the last one; that's how our hcards look
                $hints['homepage'] = $hcard['url'][count($hcard['url'])-1];
            }
        }

        return $hints;
    }

    private static function _tidy($body)
    {
        if (function_exists('tidy_parse_string')) {
            common_debug("Tidying with extension");
            $text = tidy_parse_string($body);
            $text = tidy_clean_repair($text);
            return $body;
        } else if ($fullpath = self::_findProgram('tidy')) {
            common_debug("Tidying with program $fullpath");
            $tempfile = tempnam('/tmp', 'snht'); // statusnet hcard tidy
            file_put_contents($tempfile, $source);
            exec("$fullpath -utf8 -indent -asxhtml -numeric -bare -quiet $tempfile", $tidy);
            unlink($tempfile);
            return implode("\n", $tidy);
        } else {
            common_debug("Not tidying.");
            return $body;
        }
    }

    private static function _findProgram($name)
    {
        $path = $_ENV['PATH'];

        $parts = explode(':', $path);

        foreach ($parts as $part) {
            $fullpath = $part . '/' . $name;
            if (is_executable($fullpath)) {
                return $fullpath;
            }
        }

        return null;
    }
}
