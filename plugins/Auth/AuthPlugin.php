<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for plugins that do authentication and/or authorization
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
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Superclass for plugins that do authentication
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

abstract class AuthPlugin extends Plugin
{
    //is this plugin authoritative for authentication?
    protected $authn_authoritative = false;
    
    //should accounts be automatically created after a successful login attempt?
    protected $autoregistration = false;
    
    //------------Auth plugin should implement some (or all) of these methods------------\\
    /**
    * Check if a nickname/password combination is valid
    * @param nickname
    * @param password
    * @return boolean true if the credentials are valid, false if they are invalid.
    */
    function checkPassword($nickname, $password)
    {
        return false;
    }

    /**
    * Automatically register a user when they attempt to login with valid credentials.
    * User::register($data) is a very useful method for this implementation
    * @param nickname
    * @return boolean true if the user was created, false if autoregistration is not allowed, null if this plugin is not responsible for this nickname
    */
    function autoRegister($nickname)
    {
        return null;
    }

    /**
    * Change a user's password
    * The old password has been verified to be valid by this plugin before this call is made
    * @param nickname
    * @param oldpassword
    * @param newpassword
    * @return boolean true if the password was changed, false if password changing failed for some reason, null if this plugin is not responsible for this nickname
    */
    function changePassword($nickname,$oldpassword,$newpassword)
    {
        return null;
    }

    /**
    * Can a user change this field in his own profile?
    * @param nickname
    * @param field
    * @return boolean true if the field can be changed, false if not allowed to change it, null if this plugin is not responsible for this nickname
    */
    function canUserChangeField($nickname, $field)
    {
        return null;
    }

    //------------Below are the methods that connect StatusNet to the implementing Auth plugin------------\\
    function __construct()
    {
        parent::__construct();
    }
    
    function StartCheckPassword($nickname, $password, &$authenticatedUser){
        $authenticated = $this->checkPassword($nickname, $password);
        if($authenticated){
            $authenticatedUser = User::staticGet('nickname', $nickname);
            if(!$authenticatedUser && $this->autoregistration){
                if($this->autoregister($nickname)){
                    $authenticatedUser = User::staticGet('nickname', $nickname);
                }
            }
            return false;
        }else{
            if($this->authn_authoritative){
                return false;
            }
        }
        //we're not authoritative, so let other handlers try
    }

    function onStartChangePassword($nickname,$oldpassword,$newpassword)
    {
        $authenticated = $this->checkPassword($nickname, $oldpassword);
        if($authenticated){
            $result = $this->changePassword($nickname,$oldpassword,$newpassword);
            if($result){
                //stop handling of other handlers, because what was requested was done
                return false;
            }else{
                throw new Exception(_('Password changing failed'));
            }
        }else{
            if($this->authn_authoritative){
                //since we're authoritative, no other plugin could do this
                throw new Exception(_('Password changing failed'));
            }else{
                //let another handler try
                return null;
            }
        }
            
    }
}

