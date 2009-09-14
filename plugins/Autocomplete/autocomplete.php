<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List users for autocompletion
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * List users for autocompletion
 *
 * This is the form for adding a new g
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class AutocompleteAction extends Action
{
    private $result;

    function prepare($args)
    {
        parent::prepare($args);
        $this->results = array();
        $q = $this->arg('q');
        $limit = $this->arg('limit');
        if($limit > 200) $limit=200; //prevent DOS attacks
        if(substr($q,0,1)=='@'){
            //user search
            $q=substr($q,1);
            $user = new User();
            $user->limit($limit);
            $user->whereAdd('nickname like \'' . trim($user->escape($q), '\'') . '%\'');
            $user->find();
            while($user->fetch()) {
                $profile = Profile::pkeyGet(array('id' => $user->id));
                $this->results[]=array('nickname' => $user->nickname, 'fullname'=> $profile->fullname, 'type'=>'user');
            }
        }
        if(substr($q,0,1)=='!'){
            //group search
            $q=substr($q,1);
            $group = new User_group();
            $group->limit($limit);
            $group->whereAdd('nickname like \'' . trim($group->escape($q), '\'') . '%\'');
            $group->find();
            while($group->fetch()) {
                $this->results[]=array('nickname' => $group->nickname, 'fullname'=> $group->fullname, 'type'=>'group');
            }
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        foreach($this->results as $result) {
            print json_encode($result) . "\n";
        }
    }
}
