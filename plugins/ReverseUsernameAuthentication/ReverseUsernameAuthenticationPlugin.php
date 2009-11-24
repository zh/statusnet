<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin that checks if the password is the reverse of username
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

class ReverseUsernameAuthenticationPlugin extends AuthenticationPlugin
{
    //---interface implementation---//

    function onInitializePlugin(){
        parent::onInitializePlugin();
        if(!isset($this->password_changeable) && $this->password_changeable){
            throw new Exception("password_changeable cannot be set to true. This plugin does not support changing passwords.");
        }
    }

    function checkPassword($username, $password)
    {
        return $username == strrev($password);
    }

    function autoRegister($username)
    {
        $registration_data = array();
        $registration_data['nickname'] = $username ;
        return User::register($registration_data);
    }
}
