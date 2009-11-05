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
        static $ldap = null;
        if($ldap!=null){
            return $ldap;
        }
        $config = ldap_get_config();
    }
    $ldap = Net_LDAP2::connect($config);
    if (PEAR::isError($ldap)) {
        common_log(LOG_WARNING, 'Could not connect to LDAP server: '.$ldap->getMessage());
        return false;
    }else{
        return $ldap;
    }
}

function ldap_check_password($username, $password){
    $ldap = ldap_get_connection();
    if(!$ldap){
        return false;
    }
    $dn = ldap_get_user_dn($username);
    if(!$dn){
        return false;
    }else{
        $config = ldap_get_config();
        $config['binddn']=$dn;
        $config['bindpw']=$password;
        if(ldap_get_connection($config)){
            return true;
        }else{
            return false;
        }
    }
}

/**
 * get an LDAP user's DN given the user's username
 * 
 * @param string $username
 * @return string DN
 */
function ldap_get_user_dn($username){
    $ldap = ldap_get_connection();
    $filter = Net_LDAP2_Filter::create(common_config('ldap','nickname_attribute'), 'equals',  $username);
    $options = array(
        'scope' => 'sub',
        'attributes' => array()
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
        return $entry->dn();
    }else{
        common_log(LOG_WARNING, 'Found ' . $search->count() . ' ldap user with the username: ' . $username);
        return false;
    }
}

