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
    public $url;                             // varchar(255)  primary_key not_null
    public $file_id;                         // int(4)
    public $redirections;                    // int(4)
    public $httpcode;                        // int(4)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('File_redirection',$k,$v); }

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

        if (!empty($a)) {
            // this is a direct link to $a->url
            return $a->url;
        } else {
            $b = File_redirection::staticGet('url', $short_url);
            if (!empty($b)) {
                // this is a redirect to $b->file_id
                $a = File::staticGet('id', $b->file_id);
                return $a->url;
            }
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

        $canon = File_redirection::_canonUrl($long_url);

        $short_url = File_redirection::_userMakeShort($canon);

        // Did we get one? Is it shorter?
        if (!empty($short_url) && mb_strlen($short_url) < mb_strlen($long_url)) {
            return $short_url;
        } else {
            return $long_url;
        }
    }

    function _userMakeShort($long_url) {
        $short_url = common_shorten_url($long_url);
        if (!empty($short_url) && $short_url != $long_url) {
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
        return null;
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

