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

        $body = self::_tidy($body, $url);

        common_debug("done with tidy");

        set_include_path(get_include_path() . PATH_SEPARATOR . INSTALLDIR . '/plugins/OStatus/extlib/hkit/');
        require_once('hkit.class.php');

        // hKit code is not clean for notices and warnings
        $old = error_reporting();
        error_reporting($old & ~E_NOTICE & ~E_WARNING);

        $h	= new hKit;
        $hcards = $h->getByString('hcard', $body);

        error_reporting($old);

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

    /**
     * hKit needs well-formed XML for its parsing.
     * We'll take the HTML body here and normalize it to XML.
     *
     * @param string $body HTML document source, possibly not-well-formed
     * @param string $url source URL
     * @return string well-formed XML document source
     * @throws Exception if HTML parsing failed.
     */
    private static function _tidy($body, $url)
    {
        if (empty($body)) {
            throw new Exception("Empty HTML could not be parsed.");
        }
        $dom = new DOMDocument();

        // Some HTML errors will trigger warnings, but still work.
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        
        $ok = $dom->loadHTML($body);

        error_reporting($old);
        
        if ($ok) {
            // If the original had xmlns or xml:lang attributes on the
            // <html>, we seen to end up with duplicates, which causes
            // parse errors. Remove em!
            //
            // For some reason we have to iterate and remove them twice,
            // *plus* they don't show up on hasAttribute() or removeAttribute().
            // This might be some weird bug in PHP or libxml2, uncertain if
            // it affects other folks consistently.
            $root = $dom->documentElement;
            foreach ($root->attributes as $i => $x) {
                if ($i == 'xmlns' || $i == 'xml:lang') {
                    $root->removeAttributeNode($x);
                }
            }
            foreach ($root->attributes as $i => $x) {
                if ($i == 'xmlns' || $i == 'xml:lang') {
                    $root->removeAttributeNode($x);
                }
            }

            // hKit doesn't give us a chance to pass the source URL for
            // resolving relative links, such as the avatar photo on a
            // Google profile. We'll slip it into a <base> tag if there's
            // not already one present.
            $bases = $dom->getElementsByTagName('base');
            if ($bases && $bases->length >= 1) {
                $base = $bases->item(0);
                if ($base->hasAttribute('href')) {
                    $base->setAttribute('href', $url);
                }
            } else {
                $base = $dom->createElement('base');
                $base->setAttribute('href', $url);
                $heads = $dom->getElementsByTagName('head');
                if ($heads || $heads->length) {
                    $head = $heads->item(0);
                } else {
                    $head = $dom->createElement('head');
                    if ($root->firstChild) {
                        $root->insertBefore($head, $root->firstChild);
                    } else {
                        $root->appendChild($head);
                    }
                }
                $head->appendChild($base);
            }
            return $dom->saveXML();
        } else {
            throw new Exception("Invalid HTML could not be parsed.");
        }
    }
}
