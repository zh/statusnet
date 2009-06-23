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

/**
 * Table Definition for file_oembed
 */

class File_oembed extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'file_oembed';                     // table name
    public $file_id;                         // int(4)  primary_key not_null
    public $version;                         // varchar(20)
    public $type;                            // varchar(20)
    public $provider;                        // varchar(50)
    public $provider_url;                    // varchar(255)
    public $width;                           // int(4)
    public $height;                          // int(4)
    public $html;                            // text()
    public $title;                           // varchar(255)
    public $author_name;                     // varchar(50)
    public $author_url;                      // varchar(255)
    public $url;                             // varchar(255)
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('File_oembed',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function sequenceKey()
    {
        return array(false, false, false);
    }

    function _getOembed($url, $maxwidth = 500, $maxheight = 400, $format = 'json') {
        $cmd = common_config('oohembed', 'endpoint') . '?url=' . urlencode($url);
        if (is_int($maxwidth)) $cmd .= "&maxwidth=$maxwidth";
        if (is_int($maxheight)) $cmd .= "&maxheight=$maxheight";
        if (is_string($format)) $cmd .= "&format=$format";
        $oe = @file_get_contents($cmd);
        if (false === $oe) return false;
        return array($format => (('json' === $format) ? json_decode($oe, true) : $oe));
    }

    function saveNew($data, $file_id) {
        $file_oembed = new File_oembed;
        $file_oembed->file_id = $file_id;
        $file_oembed->version = $data['version'];
        $file_oembed->type = $data['type'];
        if (!empty($data['provider_name'])) $file_oembed->provider = $data['provider_name'];
        if (!isset($file_oembed->provider) && !empty($data['provide'])) $file_oembed->provider = $data['provider'];
        if (!empty($data['provide_url'])) $file_oembed->provider_url = $data['provider_url'];
        if (!empty($data['width'])) $file_oembed->width = intval($data['width']);
        if (!empty($data['height'])) $file_oembed->height = intval($data['height']);
        if (!empty($data['html'])) $file_oembed->html = $data['html'];
        if (!empty($data['title'])) $file_oembed->title = $data['title'];
        if (!empty($data['author_name'])) $file_oembed->author_name = $data['author_name'];
        if (!empty($data['author_url'])) $file_oembed->author_url = $data['author_url'];
        if (!empty($data['url'])) $file_oembed->url = $data['url'];
        $file_oembed->insert();
        if (!empty($data['thumbnail_url'])) {
            File_thumbnail::saveNew($data, $file_id);
        }
    }
}

