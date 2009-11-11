<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to enable LDAP Authentication and Authorization
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

require_once INSTALLDIR.'/plugins/Authentication/AuthenticationPlugin.php';
require_once 'Net/LDAP2.php';

class LdapAuthenticatonPlugin extends AuthenticationPlugin
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
    public $attributes=array();

    function __construct()
    {
        parent::__construct();
    }
    
    //---interface implementation---//

    function checkPassword($nickname, $password)
    {
        $ldap = $this->ldap_get_connection();
        if(!$ldap){
            return false;
        }
        $entry = $this->ldap_get_user($nickname);
        if(!$entry){
            return false;
        }else{
            $config = $this->ldap_get_config();
            $config['binddn']=$entry->dn();
            $config['bindpw']=$password;
            if($this->ldap_get_connection($config)){
                return true;
            }else{
                return false;
            }
        }
    }

    function autoRegister($nickname)
    {
        $attributes=array();
        $config_attributes = array('nickname','email','fullname','homepage','location');
        foreach($config_attributes as $config_attribute){
            $value = common_config('ldap', $config_attribute.'_attribute');
            if($value!==false){
                array_push($attributes,$value);
            }
        }
        $entry = $this->ldap_get_user($nickname,$attributes);
        if($entry){
            $registration_data = array();
            foreach($config_attributes as $config_attribute){
                $value = common_config('ldap', $config_attribute.'_attribute');
                if($value!==false){
                    if($config_attribute=='email'){
                        $registration_data[$config_attribute]=common_canonical_email($entry->getValue($value,'single'));
                    }else if($config_attribute=='nickname'){
                        $registration_data[$config_attribute]=common_canonical_nickname($entry->getValue($value,'single'));
                    }else{
                        $registration_data[$config_attribute]=$entry->getValue($value,'single');
                    }
                }
            }
            //set the database saved password to a random string.
            $registration_data['password']=common_good_rand(16);
            $user = User::register($registration_data);
            return true;
        }else{
            //user isn't in ldap, so we cannot register him
            return null;
        }
    }

    function changePassword($nickname,$oldpassword,$newpassword)
    {
        //TODO implement this
        throw new Exception(_('Sorry, changing LDAP passwords is not supported at this time'));

        return false;
    }

    function canUserChangeField($nickname, $field)
    {
        switch($field)
        {
            case 'password':
            case 'nickname':
            case 'email':
                return false;
        }
    }
    
    //---utility functions---//
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
    
    function ldap_get_connection($config = null){
        if($config == null){
            $config = $this->ldap_get_config();
        }
        
        //cannot use Net_LDAP2::connect() as StatusNet uses
        //PEAR::setErrorHandling(PEAR_ERROR_CALLBACK, 'handleError');
        //PEAR handling can be overridden on instance objects, so we do that.
        $ldap = new Net_LDAP2($config);
        $ldap->setErrorHandling(PEAR_ERROR_RETURN);
        $err=$ldap->bind();
        if (Net_LDAP2::isError($err)) {
            common_log(LOG_WARNING, 'Could not connect to LDAP server: '.$err->getMessage());
            return false;
        }
        return $ldap;
    }
    
    /**
     * get an LDAP entry for a user with a given username
     * 
     * @param string $username
     * $param array $attributes LDAP attributes to retrieve
     * @return string DN
     */
    function ldap_get_user($username,$attributes=array()){
        $ldap = $this->ldap_get_connection();
        $filter = Net_LDAP2_Filter::create(common_config('ldap','nickname_attribute'), 'equals',  $username);
        $options = array(
            'scope' => 'sub',
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
}
