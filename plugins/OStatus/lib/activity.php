<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * An activity
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  OStatus
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class ActivityNoun
{
    const ARTICLE   = 'http://activitystrea.ms/schema/1.0/article';
    const BLOGENTRY = 'http://activitystrea.ms/schema/1.0/blog-entry';
    const NOTE      = 'http://activitystrea.ms/schema/1.0/note';
    const STATUS    = 'http://activitystrea.ms/schema/1.0/status';
    const FILE      = 'http://activitystrea.ms/schema/1.0/file';
    const PHOTO     = 'http://activitystrea.ms/schema/1.0/photo';
    const ALBUM     = 'http://activitystrea.ms/schema/1.0/photo-album';
    const PLAYLIST  = 'http://activitystrea.ms/schema/1.0/playlist';
    const VIDEO     = 'http://activitystrea.ms/schema/1.0/video';
    const AUDIO     = 'http://activitystrea.ms/schema/1.0/audio';
    const BOOKMARK  = 'http://activitystrea.ms/schema/1.0/bookmark';
    const PERSON    = 'http://activitystrea.ms/schema/1.0/person';
    const GROUP     = 'http://activitystrea.ms/schema/1.0/group';
    const PLACE     = 'http://activitystrea.ms/schema/1.0/place';
    const COMMENT   = 'http://activitystrea.ms/schema/1.0/comment'; // tea

    public $type;
    public $id;
    public $title;
    public $summary;
    public $content;
}

class Activity
{
    const NAMESPACE = 'http://activitystrea.ms/schema/1.0/';

    const POST     = 'http://activitystrea.ms/schema/1.0/post';
    const SHARE    = 'http://activitystrea.ms/schema/1.0/share';
    const SAVE     = 'http://activitystrea.ms/schema/1.0/save';
    const FAVORITE = 'http://activitystrea.ms/schema/1.0/favorite';
    const PLAY     = 'http://activitystrea.ms/schema/1.0/play';
    const FOLLOW   = 'http://activitystrea.ms/schema/1.0/follow';
    const FRIEND   = 'http://activitystrea.ms/schema/1.0/make-friend';
    const JOIN     = 'http://activitystrea.ms/schema/1.0/join';
    const TAG      = 'http://activitystrea.ms/schema/1.0/tag';

    public $actor; // an ActivityNoun
    public $verb;  // a string (the URL)
    public $object; // an ActivityNoun
    public $target; // an ActivityNoun

    static function fromAtomEntry($domEntry)
    {
    }

    function toAtomEntry()
    {
    }
}
