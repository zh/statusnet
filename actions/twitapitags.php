<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Laconica extensions to the Twitter-like API for groups
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
 * @category  Twitter
 * @package   Laconica
 * @author    Craig Andrews
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/twitterapi.php';

/**
 * Group-specific API methods
 *
 * This class handles Laconica group API methods.
 *
 * @category  Twitter
 * @package   Laconica
 * @author    Craig Andrews
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

 class TwitapitagsAction extends TwitterapiAction
 {

     function timeline($args, $apidata)
     {
         parent::handle($args);

         common_debug("in tags api action");

         $this->auth_user = $apidata['user'];
         $tag = $apidata['api_arg'];

         if (empty($tag)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return;
         }

         $sitename   = common_config('site', 'name');
         $title      = sprintf(_("Notices tagged with %s"), $tag);
         $taguribase = common_config('integration', 'taguri');
         $id         = "tag:$taguribase:TagTimeline:".$tag;
         $link       = common_local_url('tag',
             array('tag' => $tag));
         $subtitle   = sprintf(_('Updates tagged with %1$s on %2$s!'),
             $tag, $sitename);

         $page     = (int)$this->arg('page', 1);
         $count    = (int)$this->arg('count', 20);
         $max_id   = (int)$this->arg('max_id', 0);
         $since_id = (int)$this->arg('since_id', 0);
         $since    = $this->arg('since');

         # XXX: support max_id, since_id, and since arguments
         $notice = Notice_tag::getStream($tag, ($page-1)*$count, $count + 1);

         switch($apidata['content-type']) {
          case 'xml':
             $this->show_xml_timeline($notice);
             break;
          case 'rss':
             $this->show_rss_timeline($notice, $title, $link,
                 $subtitle, $suplink);
             break;
          case 'atom':
             if (isset($apidata['api_arg'])) {
                 $selfuri = common_root_url() .
                     'api/laconica/tags/timeline/' .
                         $apidata['api_arg'] . '.atom';
             } else {
                 $selfuri = common_root_url() .
                  'api/laconica/tags/timeline.atom';
             }
             $this->show_atom_timeline($notice, $title, $id, $link,
                 $subtitle, $suplink, $selfuri);
             break;
          case 'json':
             $this->show_json_timeline($notice);
             break;
          default:
             $this->clientError(_('API method not found!'), $code = 404);
         }
     }

}
