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

require_once INSTALLDIR.'/plugins/Ldap/ldap.php';

class LdapPlugin extends Plugin
{
    private $config = array();

    function __construct()
    {
        parent::__construct();
    }

    function onCheckPassword($nickname, $password, &$authenticated)
    {
        if(ldap_check_password($nickname, $password)){
            $authenticated = true;
            //stop handling of other events, because we have an answer
            return false;
        }
        if(common_config('ldap','authoritative')){
            //a false return stops handler processing
            return false;
        }
    }

    function onAutoRegister($nickname)
    {
        $user = User::staticGet('nickname', $nickname);
        if (! is_null($user) && $user !== false) {
            common_log(LOG_WARNING, "An attempt was made to autoregister an existing user with nickname: $nickname");
            return;
        }

        $attributes=array();
        $config_attributes = array('nickname','email','fullname','homepage','location');
        foreach($config_attributes as $config_attribute){
            $value = common_config('ldap', $config_attribute.'_attribute');
            if($value!==false){
                array_push($attributes,$value);
            }
        }
        $entry = ldap_get_user($nickname,$attributes);
        if($entry){
            $registration_data = array();
            foreach($config_attributes as $config_attribute){
                $value = common_config('ldap', $config_attribute.'_attribute');
                if($value!==false){
                    $registration_data[$config_attribute]=$entry->getValue($value,'single');
                }
            }
            //error_log(print_r($registration_data,1));
            $user = User::register($registration_data);
            //prevent other handlers from running, as we have registered the user
            return false;
        }
    }
}
