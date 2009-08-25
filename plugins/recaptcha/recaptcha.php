<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to show reCaptcha when a user registers 
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
 * @author    Eric Helgeson <erichelgeson@gmail.com>
 * @copyright 2009
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('RECAPTCHA', '0.2');

class recaptcha extends Plugin
{
    var $private_key;
    var $public_key;
    var $display_errors;
    var $failed;
    var $ssl;

    function __construct($public_key, $private_key, $display_errors=false)
    {
        parent::__construct();
        require_once(INSTALLDIR.'/plugins/recaptcha/recaptchalib.php');
        $this->public_key = $public_key;
        $this->private_key = $private_key; 
        $this->display_errors = $display_errors;
    }

    function checkssl(){
        if(common_config('site', 'ssl') === 'sometimes' || common_config('site', 'ssl') === 'always') {
            return true;
        }
        return false;
    }

    function onStartShowHTML($action)
    {
        //XXX: Horrible hack to make Safari, FF2, and Chrome work with
        //reChapcha. reChapcha beaks xhtml strict
        header('Content-Type: text/html');

        $action->extraHeaders();

        $action->startXML('html');

        $action->raw('<style type="text/css">#recaptcha_area{float:left;}</style>');
        return false;
    }

    function onEndRegistrationFormData($action)
    {
        $action->elementStart('li');
        $action->raw('<label for="recaptcha_area">Captcha</label>');
        if($this->checkssl() === true){
            $action->raw(recaptcha_get_html($this->public_key), null, true);
        } else { 
            $action->raw(recaptcha_get_html($this->public_key));
        }
        $action->elementEnd('li');
        return true;
    }

    function onStartRegistrationTry($action)
    {
        $resp = recaptcha_check_answer ($this->private_key,
                                        $_SERVER["REMOTE_ADDR"],
                                        $action->trimmed('recaptcha_challenge_field'),
                                        $action->trimmed('recaptcha_response_field'));

        if (!$resp->is_valid) 
        {
            if($this->display_errors)
            { 
                $action->showForm ("(reCAPTCHA said: " . $resp->error . ")");
            }
            $action->showForm("Captcha does not match!");
            return false;
        }
    }
}
