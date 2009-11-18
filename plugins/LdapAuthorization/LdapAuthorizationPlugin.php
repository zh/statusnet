<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable LDAP Authorization
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
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/plugins/Authorization/AuthorizationPlugin.php';
require_once 'Net/LDAP2.php';

class LdapAuthorizationPlugin extends AuthorizationPlugin
{
    public $host=null;
    public $port=null;
    public $version=null;
    public $starttls=null;
    public $binddn=null;
    public $bindpw=null;
    public $basedn=null;
    public $options=null;
    public $filter=null;
    public $scope=null;
    public $provider_name = null;
    public $uniqueMember_attribute = null;
    public $roles_to_groups = null;

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->host)){
            throw new Exception("must specify a host");
        }
        if(!isset($this->basedn)){
            throw new Exception("must specify a basedn");
        }
        if(!isset($this->provider_name)){
            throw new Exception("provider_name must be set. Use the provider_name from the LDAP Authentication plugin.");
        }
        if(!isset($this->uniqueMember_attribute)){
            throw new Exception("uniqueMember_attribute must be set.");
        }
        if(!isset($this->roles_to_groups)){
            throw new Exception("roles_to_groups must be set.");
        }
    }

    //---interface implementation---//
    function loginAllowed($user) {
        $user_username = new User_username();
        $user_username->user_id=$user->id;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $entry = $this->ldap_get_user($user_username->username);
            if($entry){
                //if a user exists, we can assume he's allowed to login
                return true;
            }else{
                return null;
            }
        }else{
            return null;
        }
    }

    function hasRole($profile, $name) {
        $user_username = new User_username();
        $user_username->user_id=$profile->id;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $entry = $this->ldap_get_user($user_username->username);
            if($entry){
                if(isset($this->roles_to_groups[$name])){
                    if(is_array($this->roles_to_groups[$name])){
                        foreach($this->roles_to_groups[$name] as $group){
                            if($this->isMemberOfGroup($entry->dn(),$group)){
                                return true;
                            }
                        }
                    }else{
                        if($this->isMemberOfGroup($entry->dn(),$this->roles_to_groups[$name])){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    function isMemberOfGroup($userDn, $groupDn)
    {
        $ldap = ldap_get_connection();
        $link = $ldap->getLink();
        $r = ldap_compare($link, $groupDn, $this->uniqueMember_attribute, $userDn);
        if ($r === true){
            return true;
        }else if($r === false){
            return false;
        }else{
            common_log(LOG_ERR, ldap_error($r));
            return false;
        }
    }
}
