<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * An action for logging in with Facebook
 *
 * PHP version 5
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
 *
 * @category  Pugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class FacebookloginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            
            $this->clientError(_m('Already logged in.'));

        } else {

            $facebook = new Facebook(
                array(
                    'appId'  => common_config('facebook', 'appid'),
                    'secret' => common_config('facebook', 'secret'),
                    'cookie' => true,
                )
            );

            $session = $facebook->getSession();
            $me      = null;

            if ($session) {
                try {
                    $fbuid = $facebook->getUser();
                    $fbuser  = $facebook->api('/me');
                } catch (FacebookApiException $e) {
                    common_log(LOG_ERROR, $e);
                }
            }

            if (!empty($fbuser)) {
                common_debug("Found a valid Facebook user", __FILE__);

                // Check to see if we have a foreign link already
                $flink = Foreign_link::getByForeignId($fbuid, FACEBOOK_SERVICE);

                if (empty($flink)) {

                    // See if the user would like to register a new local
                    // account
                    common_redirect(
                        common_local_url('facebookregister'),
                        303
                    );

                } else {

                    // Log our user in!
                    $user = $flink->getUser();

                    if (!empty($user)) {

                        common_debug(
                            sprintf(
                                'Logged in Facebook user $s as user %d (%s)',
                                $this->fbuid,
                                $user->id,
                                $user->nickname
                            ),
                            __FILE__
                        );

                        common_set_user($user);
                        common_real_login(true);
                        $this->goHome($user->nickname);
                    }
                }

            }
        }

        $this->showPage();
    }

    function goHome($nickname)
    {
        $url = common_get_returnto();
        if ($url) {
            // We don't have to return to it again
            common_set_returnto(null);
        } else {
            $url = common_local_url(
                'all',
                array('nickname' => $nickname)
            );
        }

        common_redirect($url, 303);
    }

    function getInstructions()
    {
        // TRANS: Instructions.
        return _m('Login with your Facebook Account');
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function title()
    {
        // TRANS: Page title.
        return _m('Login with Facebook');
    }

    function showContent() {

        $this->elementStart('fieldset');

        $attrs = array(
            'show-faces' => 'true',
            'width'      => '100',
            'max-rows'   => '2',
            'perms'      => 'user_location,user_website,offline_access,publish_stream'
        );

        $this->element('fb:login-button', $attrs);
        $this->elementEnd('fieldset');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}

