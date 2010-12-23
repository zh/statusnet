<?php
/**
 * Data class to mark notices as bookmarks
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * For storing the fact that a notice is a bookmark
 *
 * @category Bookmark
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Notice_bookmark extends Memcached_DataObject
{
    public $__table = 'notice_bookmark'; // table name
    public $notice_id;                   // int(4)  primary_key not_null
    public $title;                       // varchar(255)
    public $description;                 // text

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return User_greeting_count object found, or null for no hits
     *
     */

    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Notice_bookmark', $k, $v);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('notice_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'title' => DB_DATAOBJECT_STR,
                     'description' => DB_DATAOBJECT_STR);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * @return array list of key field names
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * @return array associative array of key definitions
     */

    function keyTypes()
    {
        return array('notice_id' => 'K');
    }

    /**
     * Magic formula for non-autoincrementing integer primary keys
     *
     * @return array magic three-false array that stops auto-incrementing.
     */

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Get the bookmark that a user made for an URL
     *
     * @param Profile $profile Profile to check for
     * @param string  $url     URL to check for
     *
     * @return Notice_bookmark bookmark found or null
     */
     
    static function getByURL($profile, $url)
    {
        $file = File::staticGet('url', $url);
        if (!empty($file)) {
            $f2p = new File_to_post();

            $f2p->file_id = $file->id;
            if ($f2p->find()) {
                while ($f2p->fetch()) {
                    $n = Notice::staticGet('id', $f2p->post_id);
                    if (!empty($n)) {
                        if ($n->profile_id == $profile->id) {
                            $nb = Notice_bookmark::staticGet('notice_id', $n->id);
                            if (!empty($nb)) {
                                return $nb;
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Save a new notice bookmark
     *
     * @param Profile $profile     To save the bookmark for
     * @param string  $title       Title of the bookmark
     * @param string  $url         URL of the bookmark
     * @param mixed   $rawtags     array of tags or string
     * @param string  $description Description of the bookmark
     * @param array   $options     Options for the Notice::saveNew()
     *
     * @return Notice saved notice
     */

    static function saveNew($profile, $title, $url, $rawtags, $description,
                            $options=null)
    {
        $nb = self::getByURL($profile, $url);

        if (!empty($nb)) {
            throw new ClientException(_('Bookmark already exists.'));
        }

        if (empty($options)) {
            $options = array();
        }

        if (is_string($rawtags)) {
            $rawtags = preg_split('/[\s,]+/', $rawtags);
        }

        $tags    = array();
        $replies = array();

        // filter "for:nickname" tags

        foreach ($rawtags as $tag) {
            if (strtolower(mb_substr($tag, 0, 4)) == 'for:') {
                $nickname = mb_substr($tag, 4);
                $other    = common_relative_profile($profile,
                                                    $nickname);
                if (!empty($other)) {
                    $replies[] = $other->getUri();
                }
            } else {
                $tags[] = common_canonical_tag($tag);
            }
        }

        $hashtags = array();
        $taglinks = array();

        foreach ($tags as $tag) {
            $hashtags[] = '#'.$tag;
            $attrs      = array('href' => Notice_tag::url($tag),
                                'rel'  => $tag,
                                'class' => 'tag');
            $taglinks[] = XMLStringer::estring('a', $attrs, $tag);
        }

        // Use user's preferences for short URLs, if possible

        $user = User::staticGet('id', $profile->id);

        $shortUrl = File_redirection::makeShort($url, 
                                                empty($user) ? null : $user);

        $content = sprintf(_('"%s" %s %s %s'),
                           $title,
                           $shortUrl,
                           $description,
                           implode(' ', $hashtags));

        $rendered = sprintf(_('<span class="xfolkentry">'.
                              '<a class="taggedlink" href="%s">%s</a> '.
                              '<span class="description">%s</span> '.
                              '<span class="meta">%s</span>'.
                              '</span>'),
                            htmlspecialchars($url),
                            htmlspecialchars($title),
                            htmlspecialchars($description),
                            implode(' ', $taglinks));

        $options = array_merge($options, array('urls' => array($url),
                                               'rendered' => $rendered,
                                               'tags' => $tags,
                                               'replies' => $replies));

        $saved = Notice::saveNew($profile->id,
                                 $content,
                                 array_key_exists('source', $options) ?
                                 $options['source'] : 'web',
                                 $options);

        if (!empty($saved)) {
            $nb = new Notice_bookmark();

            $nb->notice_id   = $saved->id;
            $nb->title       = $title;
            $nb->description = $description;
            $nb->insert();
        }

        return $saved;
    }
}
