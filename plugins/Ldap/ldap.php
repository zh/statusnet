<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
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
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once 'Net/LDAP2.php';

function ldap_get_config(){
    static $config = null;
    if($config == null){
        $config = array();
        $keys = array('host','port','version','starttls','binddn','bindpw','basedn','options','scope');
        foreach($keys as $key){
            $value = common_config('ldap', $key);
            if($value!==false){
                $config[$key]=$value;
            }
        }
    }
    return $config;
}

function ldap_get_connection($config = null){
    if($config == null){
        $config = ldap_get_config();
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

function ldap_check_password($username, $password){
    $ldap = ldap_get_connection();
    if(!$ldap){
        return false;
    }
    $entry = ldap_get_user($username);
    if(!$entry){
        return false;
    }else{
        $config = ldap_get_config();
        $config['binddn']=$entry->dn();
        $config['bindpw']=$password;
        if(ldap_get_connection($config)){
            return true;
        }else{
            return false;
        }
    }
}

/**
 * get an LDAP entry for a user with a given username
 * 
 * @param string $username
 * $param array $attributes LDAP attributes to retrieve
 * @return string DN
 */
function ldap_get_user($username,$attributes=array()){
    $ldap = ldap_get_connection();
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

