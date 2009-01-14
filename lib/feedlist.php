<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Widget for showing a list of feeds
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
 * @category  Widget
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Sarven Capadisli <csarven@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Widget for showing a list of feeds
 *
 * Typically used for Action::showExportList()
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Action::showExportList()
 */

class FeedList
{
    var $out = null;

    function __construct($out=null)
    {
        $this->out = $out;
    }

    function show($feeds)
    {
        $this->out->elementStart('div', array('class' => 'feeds'));
        $this->out->element('p', null, 'Feeds:');
        $this->out->elementStart('ul', array('class' => 'xoxo'));

        foreach ($feeds as $key => $value) {
            $this->feedItem($feeds[$key]);
        }

        $this->out->elementEnd('ul');
        $this->out->elementEnd('div');
    }

    function feedItem($feed)
    {
        $nickname = $this->trimmed('nickname');

        switch($feed['item']) {
         case 'notices': default:
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "$nickname's ".$feed['version']." notice feed";
            $feed['textContent'] = "RSS";
            break;

         case 'allrss':
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = $feed['version']." feed for $nickname and friends";
            $feed['textContent'] = "RSS";
            break;

         case 'repliesrss':
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = $feed['version']." feed for replies to $nickname";
            $feed['textContent'] = "RSS";
            break;

         case 'publicrss':
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "Public timeline ".$feed['version']." feed";
            $feed['textContent'] = "RSS";
            break;

         case 'publicatom':
            $feed_classname = "atom";
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "Public timeline ".$feed['version']." feed";
            $feed['textContent'] = "Atom";
            break;

         case 'tagrss':
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = $feed['version']." feed for this tag";
            $feed['textContent'] = "RSS";
            break;

         case 'favoritedrss':
            $feed_classname = $feed['type'];
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "Favorited ".$feed['version']." feed";
            $feed['textContent'] = "RSS";
            break;

         case 'foaf':
            $feed_classname = "foaf";
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "$nickname's FOAF file";
            $feed['textContent'] = "FOAF";
            break;

         case 'favoritesrss':
            $feed_classname = "favorites";
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "Feed for favorites of $nickname";
            $feed['textContent'] = "RSS";
            break;

         case 'usertimeline':
            $feed_classname = "atom";
            $feed_mimetype = "application/".$feed['type']."+xml";
            $feed_title = "$nickname's ".$feed['version']." notice feed";
            $feed['textContent'] = "Atom";
            break;
        }
        $this->out->elementStart('li');
        $this->out->element('a', array('href' => $feed['href'],
                                  'class' => $feed_classname,
                                  'type' => $feed_mimetype,
                                  'title' => $feed_title),
                       $feed['textContent']);
        $this->out->elementEnd('li');
    }
}
