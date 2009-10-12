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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
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
 * @author    Craig Andrews <candrews@integralblue.com>
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
             $selfuri = common_root_url() . 'api/statusnet/groups/list/' . $user->id . '.atom';
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
             $selfuri = common_root_url() . 'api/statusnet/groups/list_all.atom';
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
                     'api/statusnet/groups/timeline/' .
                         $apidata['api_arg'] . '.atom';
             } else {
                 $selfuri = common_root_url() .
                  'api/statusnet/groups/timeline.atom';
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

     function membership($args, $apidata)
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
         $title      = sprintf(_("Members of %s group"), $group->nickname);
         $taguribase = common_config('integration', 'taguri');
         $id         = "tag:$taguribase:GroupMembership:".$group->id;
         $link       = common_local_url('showgroup',
             array('nickname' => $group->nickname));
         $subtitle   = sprintf(_('Members of %1$s on %2$s'),
             $group->nickname, $sitename);

         $page     = (int)$this->arg('page', 1);
         $count    = (int)$this->arg('count', 20);
         $max_id   = (int)$this->arg('max_id', 0);
         $since_id = (int)$this->arg('since_id', 0);
         $since    = $this->arg('since');

         $member = $group->getMembers(($page-1)*$count,
             $count, $since_id, $max_id, $since);

         switch($apidata['content-type']) {
          case 'xml':
             $this->show_twitter_xml_users($member);
             break;
          //TODO implement the RSS and ATOM content types
          /*case 'rss':
             $this->show_rss_users($member, $title, $link, $subtitle);
             break;*/
          /*case 'atom':
             if (isset($apidata['api_arg'])) {
                 $selfuri = common_root_url() .
                     'api/statusnet/groups/membership/' .
                         $apidata['api_arg'] . '.atom';
             } else {
                 $selfuri = common_root_url() .
                  'api/statusnet/groups/membership.atom';
             }
             $this->show_atom_users($member, $title, $id, $link,
                 $subtitle, null, $selfuri);
             break;*/
          case 'json':
             $this->show_json_users($member);
             break;
          default:
             $this->clientError(_('API method not found!'), $code = 404);
         }
     }

     function join($args, $apidata)
     {
         parent::handle($args);

         common_debug("in groups api action");

         $this->auth_user = $apidata['user'];
         $group = $this->get_group($apidata['api_arg'], $apidata);

         if (empty($group)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return false;
         }

         if($this->auth_user->isMember($group)){
            $this->clientError(_('You are already a member of that group'), $code = 403);
            return false;
         }

         if (Group_block::isBlocked($group, $this->auth_user->getProfile())) {
            $this->clientError(_('You have been blocked from that group by the admin.'), 403);
            return false;
         }

         $member = new Group_member();

         $member->group_id   = $group->id;
         $member->profile_id = $this->auth_user->id;
         $member->created    = common_sql_now();

         $result = $member->insert();

         if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            $this->serverError(sprintf(_('Could not join user %s to group %s'),
                                       $this->auth_user->nickname, $group->nickname));
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

     function leave($args, $apidata)
     {
         parent::handle($args);

         common_debug("in groups api action");

         $this->auth_user = $apidata['user'];
         $group = $this->get_group($apidata['api_arg'], $apidata);

         if (empty($group)) {
             $this->clientError('Not Found', 404, $apidata['content-type']);
             return false;
         }

         if(! $this->auth_user->isMember($group)){
            $this->clientError(_('You are not a member of that group'), $code = 403);
            return false;
         }

         $member = new Group_member();

         $member->group_id   = $group->id;
         $member->profile_id = $this->auth_user->id;

         if (!$member->find(true)) {
            $this->serverError(_('Could not find membership record.'));
            return;
         }

         $result = $member->delete();

         if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            $this->serverError(sprintf(_('Could not remove user %s to group %s'),
                                       $this->auth_user->nickname, $group->nickname));
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

     function is_member($args, $apidata)
     {
         parent::handle($args);

         common_debug("in groups api action");

         $this->auth_user = $apidata['user'];
         $group = User_group::staticGet($args['group_id']);
         if(! $group){
            $this->clientError(_('Group not found'), $code = 500);
         }
         $user = User::staticGet('id', $args['user_id']);
         if(! $user){
            $this->clientError(_('User not found'), $code = 500);
         }
         
         $is_member=$user->isMember($group);

         switch($apidata['content-type']) {
          case 'xml':
             $this->init_document('xml');
             $this->element('is_member', null, $is_member);
             $this->end_document('xml');
             break;
          case 'json':
             $this->init_document('json');
             $this->show_json_objects(array('is_member'=>$is_member));
             $this->end_document('json');
             break;
          default:
             $this->clientError(_('API method not found!'), $code = 404);
         }
     }

     function create($args, $apidata)
     {
        parent::handle($args);

        common_debug("in groups api action");
        if (!common_config('inboxes','enabled')) {
           $this->serverError(_('Inboxes must be enabled for groups to work'));
           return false;
        }

        $this->auth_user = $apidata['user'];

        $nickname    = $args['nickname'];
        $fullname    = $args['full_name'];
        $homepage    = $args['homepage'];
        $description = $args['description'];
        $location    = $args['location'];
        $aliasstring = $args['aliases'];

        if (!Validate::string($nickname, array('min_length' => 1,
                                               'max_length' => 64,
                                               'format' => NICKNAME_FMT))) {
            $this->clientError(_('Nickname must have only lowercase letters '.
                              'and numbers and no spaces.'), $code=403);
            return;
        } else if ($this->groupNicknameExists($nickname)) {
            $this->clientError(_('Nickname already in use. Try another one.'), $code=403);
            return;
        } else if (!User_group::allowedNickname($nickname)) {
            $this->clientError(_('Not a valid nickname.'), $code=403);
            return;
        } else if (!is_null($homepage) && (strlen($homepage) > 0) &&
                   !Validate::uri($homepage,
                                  array('allowed_schemes' =>
                                        array('http', 'https')))) {
            $this->clientError(_('Homepage is not a valid URL.'), $code=403);
            return;
        } else if (!is_null($fullname) && mb_strlen($fullname) > 255) {
            $this->clientError(_('Full name is too long (max 255 chars).'), $code=403);
            return;
        } else if (User_group::descriptionTooLong($description)) {
            $this->clientError(sprintf(_('description is too long (max %d chars).'), User_group::maxDescription()), $code=403);
            return;
        } else if (!is_null($location) && mb_strlen($location) > 255) {
            $this->clientError(_('Location is too long (max 255 chars).'), $code=403);
            return;
        }

        if (!empty($aliasstring)) {
            $aliases = array_map('common_canonical_nickname', array_unique(preg_split('/[\s,]+/', $aliasstring)));
        } else {
            $aliases = array();
        }

        if (count($aliases) > common_config('group', 'maxaliases')) {
            $this->clientError(sprintf(_('Too many aliases! Maximum %d.'),
                                    common_config('group', 'maxaliases')), $code=403);
            return;
        }

        foreach ($aliases as $alias) {
            if (!Validate::string($alias, array('min_length' => 1,
                                                'max_length' => 64,
                                                'format' => NICKNAME_FMT))) {
                $this->clientError(sprintf(_('Invalid alias: "%s"'), $alias), $code=403);
                return;
            }
            if ($this->groupNicknameExists($alias)) {
                $this->clientError(sprintf(_('Alias "%s" already in use. Try another one.'),
                                        $alias), $code=403);
                return;
            }
            // XXX assumes alphanum nicknames
            if (strcmp($alias, $nickname) == 0) {
                $this->clientError(_('Alias can\'t be the same as nickname.'), $code=403);
                return;
            }
        }

        $group = new User_group();

        $group->query('BEGIN');

        $group->nickname    = $nickname;
        $group->fullname    = $fullname;
        $group->homepage    = $homepage;
        $group->description = $description;
        $group->location    = $location;
        $group->created     = common_sql_now();

        $result = $group->insert();

        if (!$result) {
            common_log_db_error($group, 'INSERT', __FILE__);
            $this->serverError(_('Could not create group.'));
        }

        $result = $group->setAliases($aliases);

        if (!$result) {
            $this->serverError(_('Could not create aliases.'));
        }

        $member = new Group_member();

        $member->group_id   = $group->id;
        $member->profile_id = $this->auth_user->id;
        $member->is_admin   = 1;
        $member->created    = $group->created;

        $result = $member->insert();

        if (!$result) {
            common_log_db_error($member, 'INSERT', __FILE__);
            $this->serverError(_('Could not set group membership.'));
        }

        $group->query('COMMIT');

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

     function update($args, $apidata)
     {
        die("todo");
     }

     function update_group_logo($args, $apidata)
     {
        die("todo");
     }

     function destroy($args, $apidata)
     {
        die("todo");
     }

     function tag($args, $apidata)
     {
        die("todo");
     }

     function groupNicknameExists($nickname)
     {
        $group = User_group::staticGet('nickname', $nickname);

        if (!empty($group)) {
            return true;
        }

        $alias = Group_alias::staticGet('alias', $nickname);

        if (!empty($alias)) {
            return true;
        }

        return false;
     }
}
