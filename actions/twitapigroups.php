<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * StatusNet extensions to the Twitter-like API for groups
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
 * @package   StatusNet
 * @author    Craig Andrews
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/twitterapi.php';

/**
 * Group-specific API methods
 *
 * This class handles StatusNet group API methods.
 *
 * @category  Twitter
 * @package   StatusNet
 * @author    Craig Andrews
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

 class TwitapigroupsAction extends TwitterapiAction
 {

     function list_groups($args, $apidata)
     {
         parent::handle($args);
         
         common_debug("in groups api action");
         
         $this->auth_user = $apidata['user'];
         $user = $this->get_user($apidata['api_arg'], $apidata);

         if (empty($user)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return;
         }

         $page     = (int)$this->arg('page', 1);
         $count    = (int)$this->arg('count', 20);
         $max_id   = (int)$this->arg('max_id', 0);
         $since_id = (int)$this->arg('since_id', 0);
         $since    = $this->arg('since');
         $group = $user->getGroups(($page-1)*$count,
             $count, $since_id, $max_id, $since);

         $sitename   = common_config('site', 'name');
         $title      = sprintf(_("%s's groups"), $user->nickname);
         $taguribase = common_config('integration', 'taguri');
         $id         = "tag:$taguribase:Groups";
         $link       = common_root_url();
         $subtitle   = sprintf(_("groups %s is a member of on %s"), $user->nickname, $sitename);

         switch($apidata['content-type']) {
         case 'xml':
             $this->show_xml_groups($group);
             break;
         case 'rss':
             $this->show_rss_groups($group, $title, $link, $subtitle);
             break;
         case 'atom':
             $selfuri = common_root_url() . 'api/laconica/groups/list/' . $user->id . '.atom';
             $this->show_atom_groups($group, $title, $id, $link,
                 $subtitle, $selfuri);
             break;
         case 'json':
             $this->show_json_groups($group);
             break;
         default:
             $this->clientError(_('API method not found!'), $code = 404);
             break;
         }
     }

     function list_all($args, $apidata)
     {
         parent::handle($args);
         
         common_debug("in groups api action");
         
         $page     = (int)$this->arg('page', 1);
         $count    = (int)$this->arg('count', 20);
         $max_id   = (int)$this->arg('max_id', 0);
         $since_id = (int)$this->arg('since_id', 0);
         $since    = $this->arg('since');

         /*	 TODO:
         Use the $page, $count, $max_id, $since_id, and $since parameters
         */
         $group = new User_group();
         $group->orderBy('created DESC');
         $group->find();

         $sitename   = common_config('site', 'name');
         $title      = sprintf(_("%s groups"), $sitename);
         $taguribase = common_config('integration', 'taguri');
         $id         = "tag:$taguribase:Groups";
         $link       = common_root_url();
         $subtitle   = sprintf(_("groups on %s"), $sitename);

         switch($apidata['content-type']) {
         case 'xml':
             $this->show_xml_groups($group);
             break;
         case 'rss':
             $this->show_rss_groups($group, $title, $link, $subtitle);
             break;
         case 'atom':
             $selfuri = common_root_url() . 'api/laconica/groups/list_all.atom';
             $this->show_atom_groups($group, $title, $id, $link,
                 $subtitle, $selfuri);
             break;
         case 'json':
             $this->show_json_groups($group);
             break;
         default:
             $this->clientError(_('API method not found!'), $code = 404);
             break;
         }
     }

     function show($args, $apidata)
     {
         parent::handle($args);

         common_debug("in groups api action");

         $this->auth_user = $apidata['user'];
         $group = $this->get_group($apidata['api_arg'], $apidata);

         if (empty($group)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return;
         }

         switch($apidata['content-type']) {
          case 'xml':
             $this->show_single_xml_group($group);
             break;
          case 'json':
             $this->show_single_json_group($group);
             break;
          default:
             $this->clientError(_('API method not found!'), $code = 404);
         }
     }

     function timeline($args, $apidata)
     {
         parent::handle($args);

         common_debug("in groups api action");

         $this->auth_user = $apidata['user'];
         $group = $this->get_group($apidata['api_arg'], $apidata);

         if (empty($group)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return;
         }

         $sitename   = common_config('site', 'name');
         $title      = sprintf(_("%s timeline"), $group->nickname);
         $taguribase = common_config('integration', 'taguri');
         $id         = "tag:$taguribase:GroupTimeline:".$group->id;
         $link       = common_local_url('showgroup',
             array('nickname' => $group->nickname));
         $subtitle   = sprintf(_('Updates from %1$s on %2$s!'),
             $group->nickname, $sitename);

         $page     = (int)$this->arg('page', 1);
         $count    = (int)$this->arg('count', 20);
         $max_id   = (int)$this->arg('max_id', 0);
         $since_id = (int)$this->arg('since_id', 0);
         $since    = $this->arg('since');

         $notice = $group->getNotices(($page-1)*$count,
             $count, $since_id, $max_id, $since);

         switch($apidata['content-type']) {
          case 'xml':
             $this->show_xml_timeline($notice);
             break;
          case 'rss':
             $this->show_rss_timeline($notice, $title, $link, $subtitle);
             break;
          case 'atom':
             if (isset($apidata['api_arg'])) {
                 $selfuri = common_root_url() .
                     'api/laconica/groups/timeline/' .
                         $apidata['api_arg'] . '.atom';
             } else {
                 $selfuri = common_root_url() .
                  'api/laconica/groups/timeline.atom';
             }
             $this->show_atom_timeline($notice, $title, $id, $link,
                 $subtitle, null, $selfuri);
             break;
          case 'json':
             $this->show_json_timeline($notice);
             break;
          default:
             $this->clientError(_('API method not found!'), $code = 404);
         }
     }

}
