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

abstract class AuthenticationPlugin extends Plugin
{
    //is this plugin authoritative for authentication?
    public $authoritative = false;
    
    //should accounts be automatically created after a successful login attempt?
    public $autoregistration = false;

    //can the user change their email address
    public $password_changeable=true;

    //unique name for this authentication provider
    public $provider_name;

    //------------Auth plugin should implement some (or all) of these methods------------\\
    /**
    * Check if a nickname/password combination is valid
    * @param username
    * @param password
    * @return boolean true if the credentials are valid, false if they are invalid.
    */
    function checkPassword($username, $password)
    {
        return false;
    }

    /**
    * Automatically register a user when they attempt to login with valid credentials.
    * User::register($data) is a very useful method for this implementation
    * @param username
    * @return mixed instance of User, or false (if user couldn't be created)
    */
    function autoRegister($username)
    {
        $registration_data = array();
        $registration_data['nickname'] = $username ;
        return User::register($registration_data);
    }

    /**
    * Change a user's password
    * The old password has been verified to be valid by this plugin before this call is made
    * @param username
    * @param oldpassword
    * @param newpassword
    * @return boolean true if the password was changed, false if password changing failed for some reason
    */
    function changePassword($username,$oldpassword,$newpassword)
    {
        return false;
    }

    //------------Below are the methods that connect StatusNet to the implementing Auth plugin------------\\
    function onInitializePlugin(){
        if(!isset($this->provider_name)){
            throw new Exception("must specify a provider_name for this authentication provider");
        }
    }

    function onStartCheckPassword($nickname, $password, &$authenticatedUser){
        //map the nickname to a username
        $user_username = new User_username();
        $user_username->username=$nickname;
        $user_username->provider_name=$this->provider_name;
        if($user_username->find() && $user_username->fetch()){
            $username = $user_username->username;
            $authenticated = $this->checkPassword($username, $password);
            if($authenticated){
                $authenticatedUser = User::staticGet('id', $user_username->user_id);
                return false;
            }
        }else{
            $user = User::staticGet('nickname', $nickname);
            if($user){
                //make sure a different provider isn't handling this nickname
                $user_username = new User_username();
                $user_username->username=$nickname;
                if(!$user_username->find()){
                    //no other provider claims this username, so it's safe for us to handle it
                    $authenticated = $this->checkPassword($nickname, $password);
                    if($authenticated){
                        $authenticatedUser = User::staticGet('nickname', $nickname);
                        User_username::register($authenticatedUser,$nickname,$this->provider_name);
                        return false;
                    }
                }
            }else{
                if($this->autoregistration){
                    $authenticated = $this->checkPassword($nickname, $password);
                    if($authenticated){
                        $user = $this->autoregister($nickname);
                        if($user){
                            $authenticatedUser = $user;
                            User_username::register($authenticatedUser,$nickname,$this->provider_name);
                            return false;
                        }
                    }
                }
            }
        }
        if($this->authoritative){
            return false;
        }else{
            //we're not authoritative, so let other handlers try
            return;
        }
    }

    function onStartChangePassword($user,$oldpassword,$newpassword)
    {
        if($this->password_changeable){
            $user_username = new User_username();
            $user_username->user_id=$user->id;
            $user_username->provider_name=$this->provider_name;
            if($user_username->find() && $user_username->fetch()){
                $authenticated = $this->checkPassword($user_username->username, $oldpassword);
                if($authenticated){
                    $result = $this->changePassword($user_username->username,$oldpassword,$newpassword);
                    if($result){
                        //stop handling of other handlers, because what was requested was done
                        return false;
                    }else{
                        throw new Exception(_('Password changing failed'));
                    }
                }else{
                    if($this->authoritative){
                        //since we're authoritative, no other plugin could do this
                        throw new Exception(_('Password changing failed'));
                    }else{
                        //let another handler try
                        return null;
                    }
                }
            }
        }else{
            if($this->authoritative){
                //since we're authoritative, no other plugin could do this
                throw new Exception(_('Password changing is not allowed'));
            }
        }
    }

    function onStartAccountSettingsPasswordMenuItem($widget)
    {
        if($this->authoritative && !$this->password_changeable){
            //since we're authoritative, no other plugin could change passwords, so do not render the menu item
            return false;
        }
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'User_username':
            require_once(INSTALLDIR.'/plugins/Authentication/User_username.php');
            return false;
         default:
            return true;
        }
    }

    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureDataObject(User_username);
        return true;
    }

    function onUserDeleteRelated($user, &$tables)
    {
        $tables[] = 'User_username';
        return true;
    }
}

