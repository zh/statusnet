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
    public $roles_to_groups = array();
    public $login_group = null;
    public $attributes = array();

    function onInitializePlugin(){
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
        if(!isset($this->attributes['username'])){
            throw new Exception("username attribute must be set.");
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
                if(isset($this->login_group)){
                    if(is_array($this->login_group)){
                        foreach($this->login_group as $group){
                            if($this->ldap_is_dn_member_of_group($entry->dn(),$group)){
                                return true;
                            }
                        }
                    }else{
                        if($this->ldap_is_dn_member_of_group($entry->dn(),$this->login_group)){
                            return true;
                        }
                    }
                    return null;
                }else{
                    //if a user exists, we can assume he's allowed to login
                    return true;
                }
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
                            if($this->ldap_is_dn_member_of_group($entry->dn(),$group)){
                                return true;
                            }
                        }
                    }else{
                        if($this->ldap_is_dn_member_of_group($entry->dn(),$this->roles_to_groups[$name])){
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    function ldap_is_dn_member_of_group($userDn, $groupDn)
    {
        $ldap = $this->ldap_get_connection();
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

    function ldap_get_config(){
        $config = array();
        $keys = array('host','port','version','starttls','binddn','bindpw','basedn','options','filter','scope');
        foreach($keys as $key){
            $value = $this->$key;
            if($value!==null){
                $config[$key]=$value;
            }
        }
        return $config;
    }

    //-----the below function were copied from LDAPAuthenticationPlugin. They will be moved to a utility class soon.----\\
    function ldap_get_connection($config = null){
        if($config == null && isset($this->default_ldap)){
            return $this->default_ldap;
        }
        
        //cannot use Net_LDAP2::connect() as StatusNet uses
        //PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handleError');
        //PEAR handling can be overridden on instance objects, so we do that.
        $ldap = new Net_LDAP2(isset($config)?$config:$this->ldap_get_config());
        $ldap->setErrorHandling(PEAR_ERROR_RETURN);
        $err=$ldap->bind();
        if (Net_LDAP2::isError($err)) {
            throw new Exception('Could not connect to LDAP server: '.$err->getMessage());
            return false;
        }
        if($config == null) $this->default_ldap=$ldap;
        return $ldap;
    }
    
    /**
     * get an LDAP entry for a user with a given username
     * 
     * @param string $username
     * $param array $attributes LDAP attributes to retrieve
     * @return string DN
     */
    function ldap_get_user($username,$attributes=array(),$ldap=null){
        if($ldap==null) {
            $ldap = $this->ldap_get_connection();
        }
        if(! $ldap) {
            throw new Exception("Could not connect to LDAP");
        }
        $filter = Net_LDAP2_Filter::create($this->attributes['username'], 'equals',  $username);
        $options = array(
            'attributes' => $attributes
        );
        $search = $ldap->search(null,$filter,$options);
        
        if (PEAR::isError($search)) {
            common_log(LOG_WARNING, 'Error while getting DN for user: '.$search->getMessage());
            return false;
        }

        if($search->count()==0){
            return false;
        }else if($search->count()==1){
            $entry = $search->shiftEntry();
            return $entry;
        }else{
            common_log(LOG_WARNING, 'Found ' . $search->count() . ' ldap user with the username: ' . $username);
            return false;
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'LDAP Authorization',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:LdapAuthorization',
                            'rawdescription' =>
                            _m('The LDAP Authorization plugin allows for StatusNet to handle authorization through LDAP.'));
        return true;
    }
}
