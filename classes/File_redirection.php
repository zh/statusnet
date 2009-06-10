<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';
require_once INSTALLDIR.'/classes/File.php';
require_once INSTALLDIR.'/classes/File_oembed.php';

define('USER_AGENT', 'Laconica user agent / file probe');


/**
 * Table Definition for file_redirection
 */

class File_redirection extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_redirection';                // table name
    public $id;                              // int(11)  not_null primary_key group_by
    public $url;                             // varchar(255)  unique_key
    public $file_id;                         // int(11)  group_by
    public $redirections;                    // int(11)  group_by
    public $httpcode;                        // int(11)  group_by

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('File_redirection',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE



    function _commonCurl($url, $redirs) {
        $curlh = curl_init();
        curl_setopt($curlh, CURLOPT_URL, $url);
        curl_setopt($curlh, CURLOPT_AUTOREFERER, true); // # setup referer header when folowing redirects
        curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, 10); // # seconds to wait
        curl_setopt($curlh, CURLOPT_MAXREDIRS, $redirs); // # max number of http redirections to follow
        curl_setopt($curlh, CURLOPT_USERAGENT, USER_AGENT);
        curl_setopt($curlh, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlh, CURLOPT_FILETIME, true);
        curl_setopt($curlh, CURLOPT_HEADER, true); // Include header in output
        return $curlh;
    }

    function _redirectWhere_imp($short_url, $redirs = 10, $protected = false) {
        if ($redirs < 0) return false;

        // let's see if we know this...
        $a = File::staticGet('url', $short_url);
        if (empty($a->id)) {
            $b = File_redirection::staticGet('url', $short_url);
            if (empty($b->id)) {
                // we'll have to figure it out
            } else {
                // this is a redirect to $b->file_id
                $a = File::staticGet($b->file_id);
                $url = $a->url;
            }
        } else {
            // this is a direct link to $a->url
            $url = $a->url;
        }
        if (isset($url)) {
            return $url;
        }



        $curlh = File_redirection::_commonCurl($short_url, $redirs);
        // Don't include body in output
        curl_setopt($curlh, CURLOPT_NOBODY, true);
        curl_exec($curlh);
        $info = curl_getinfo($curlh);
        curl_close($curlh);

        if (405 == $info['http_code']) {
            $curlh = File_redirection::_commonCurl($short_url, $redirs);
            curl_exec($curlh);
            $info = curl_getinfo($curlh);
            curl_close($curlh);
        }

        if (!empty($info['redirect_count']) && File::isProtected($info['url'])) {
            return File_redirection::_redirectWhere_imp($short_url, $info['redirect_count'] - 1, true);
        }

        $ret = array('code' => $info['http_code']
                , 'redirects' => $info['redirect_count']
                , 'url' => $info['url']);

        if (!empty($info['content_type'])) $ret['type'] = $info['content_type'];
        if ($protected) $ret['protected'] = true;
        if (!empty($info['download_content_length'])) $ret['size'] = $info['download_content_length'];
        if (isset($info['filetime']) && ($info['filetime'] > 0)) $ret['time'] = $info['filetime'];
        return $ret;
    }

    function where($in_url) {
        $ret = File_redirection::_redirectWhere_imp($in_url);
        return $ret;
    }

    function makeShort($long_url) {
        $long_url = File_redirection::_canonUrl($long_url);
        // do we already know this long_url and have a short redirection for it?
        $file       = new File;
        $file_redir = new File_redirection;
        $file->url  = $long_url;
        $file->joinAdd($file_redir);
        $file->selectAdd('length(file_redirection.url) as len');
        $file->limit(1);
        $file->orderBy('len');
        $file->find(true);
        if (!empty($file->url) && (strlen($file->url) < strlen($long_url))) {
            return $file->url;
        }

        // if yet unknown, we must find a short url according to user settings
        $short_url = File_redirection::_userMakeShort($long_url, common_current_user());
        return $short_url;
    }

    function _userMakeShort($long_url, $user) {
        if (empty($user)) {
            // common current user does not find a user when called from the XMPP daemon
            // therefore we'll set one here fix, so that XMPP given URLs may be shortened
            $user->urlshorteningservice = 'ur1.ca';
        }
        $curlh = curl_init();
        curl_setopt($curlh, CURLOPT_CONNECTTIMEOUT, 20); // # seconds to wait
        curl_setopt($curlh, CURLOPT_USERAGENT, 'Laconica');
        curl_setopt($curlh, CURLOPT_RETURNTRANSFER, true);

        switch($user->urlshorteningservice) {
            case 'ur1.ca':
                require_once INSTALLDIR.'/lib/Shorturl_api.php';
                $short_url_service = new LilUrl;
                $short_url = $short_url_service->shorten($long_url);
                break;

            case '2tu.us':
                $short_url_service = new TightUrl;
                require_once INSTALLDIR.'/lib/Shorturl_api.php';
                $short_url = $short_url_service->shorten($long_url);
                break;

            case 'ptiturl.com':
                require_once INSTALLDIR.'/lib/Shorturl_api.php';
                $short_url_service = new PtitUrl;
                $short_url = $short_url_service->shorten($long_url);
                break;

            case 'bit.ly':
                curl_setopt($curlh, CURLOPT_URL, 'http://bit.ly/api?method=shorten&long_url='.urlencode($long_url));
                $short_url = current(json_decode(curl_exec($curlh))->results)->hashUrl;
                break;

            case 'is.gd':
                curl_setopt($curlh, CURLOPT_URL, 'http://is.gd/api.php?longurl='.urlencode($long_url));
                $short_url = curl_exec($curlh);
                break;
            case 'snipr.com':
                curl_setopt($curlh, CURLOPT_URL, 'http://snipr.com/site/snip?r=simple&link='.urlencode($long_url));
                $short_url = curl_exec($curlh);
                break;
            case 'metamark.net':
                curl_setopt($curlh, CURLOPT_URL, 'http://metamark.net/api/rest/simple?long_url='.urlencode($long_url));
                $short_url = curl_exec($curlh);
                break;
            case 'tinyurl.com':
                curl_setopt($curlh, CURLOPT_URL, 'http://tinyurl.com/api-create.php?url='.urlencode($long_url));
                $short_url = curl_exec($curlh);
                break;
            default:
                $short_url = false;
        }

        curl_close($curlh);

        if ($short_url) {
            $short_url = (string)$short_url;
            // store it
            $file = File::staticGet('url', $long_url);
            if (empty($file)) {
                $redir_data = File_redirection::where($long_url);
                $file = File::saveNew($redir_data, $long_url);
                $file_id = $file->id;
                if (!empty($redir_data['oembed']['json'])) {
                    File_oembed::saveNew($redir_data['oembed']['json'], $file_id);
                }
            } else {
                $file_id = $file->id;
            }
            $file_redir = File_redirection::staticGet('url', $short_url);
            if (empty($file_redir)) {
                $file_redir = new File_redirection;
                $file_redir->url = $short_url;
                $file_redir->file_id = $file_id;
                $file_redir->insert();
            }
            return $short_url;
        }
        return $long_url;
    }

    function _canonUrl($in_url, $default_scheme = 'http://') {
        if (empty($in_url)) return false;
        $out_url = $in_url;
        $p = parse_url($out_url);
        if (empty($p['host']) || empty($p['scheme'])) {
            list($scheme) = explode(':', $in_url, 2);
            switch ($scheme) {
            case 'fax':
            case 'tel':
                $out_url = str_replace('.-()', '', $out_url);
                break;

            case 'mailto':
            case 'aim':
            case 'jabber':
            case 'xmpp':
                // don't touch anything
                break;

            default:
                $out_url = $default_scheme . ltrim($out_url, '/');
                $p = parse_url($out_url);
                if (empty($p['scheme'])) return false;
                break;
            }
        }

        if (('ftp' == $p['scheme']) || ('http' == $p['scheme']) || ('https' == $p['scheme'])) {
            if (empty($p['host'])) return false;
            if (empty($p['path'])) {
                $out_url .= '/';
            }
        }

        return $out_url;
    }

    function saveNew($data, $file_id, $url) {
        $file_redir = new File_redirection;
        $file_redir->url = $url;
        $file_redir->file_id = $file_id;
        $file_redir->redirections = intval($data['redirects']);
        $file_redir->httpcode = intval($data['code']);
        $file_redir->insert();
    }
}

