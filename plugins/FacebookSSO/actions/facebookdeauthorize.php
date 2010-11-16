<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * An action that handles deauthorize callbacks from Facebook
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/*
 * Action class for handling deauthorize callbacks from Facebook. If the user
 * doesn't have a password let her know she'll need to contact the site
 * admin to get back into her account (if possible).
 */
class FacebookdeauthorizeAction extends Action
{
    private $facebook;

    /**
     * For initializing members of the class.
     *
     * @param array $args misc. arguments
     *
     * @return boolean true
     */
    function prepare($args)
    {
        $this->facebook = Facebookclient::getFacebook();

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     */
    function handle($args)
    {
        parent::handle($args);

        $data = $this->facebook->getSignedRequest();

        if (isset($data['user_id'])) {

            $fbuid = $data['user_id'];

            $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);
            $user = $flink->getUser();

            // Remove the link to Facebook
            $result = $flink->delete();

            if (!$result) {
                common_log_db_error($flink, 'DELETE', __FILE__);
                common_log(
                    LOG_WARNING,
                    sprintf(
                        'Unable to delete Facebook foreign link '
                            . 'for %s (%d), fbuid %s',
                        $user->nickname,
                        $user->id,
                        $fbuid
                    ),
                    __FILE__
                );
                return;
            }

            common_log(
                LOG_INFO,
                sprintf(
                    'Facebook callback: %s (%d), fbuid %s has deauthorized '
                        . 'the Facebook application.',
                    $user->nickname,
                    $user->id,
                    $fbuid
                ),
                __FILE__
            );

            // Warn the user about being locked out of their account
            // if we can.
            if (empty($user->password) && !empty($user->email)) {
                $this->emailWarn($user);
            } else {
                common_log(
                    LOG_WARNING,
                    sprintf(
                        '%s (%d), fbuid %d has deauthorized his/her Facebook '
                        . 'connection but hasn\'t set a password so s/he '
                        . 'is locked out.',
                        $user->nickname,
                        $user->id,
                        $fbuid
                    ),
                    __FILE__
                );
            }

        } else {
            if (!empty($data)) {
                common_log(
                    LOG_WARNING,
                    sprintf(
                        'Facebook called the deauthorize callback '
                        . ' but didn\'t provide a user ID.'
                    ),
                    __FILE__
                );
            } else {
                // It probably wasn't Facebook that hit this action,
                // so redirect to the public timeline
                common_redirect(common_local_url('public'), 303);
            }
        }
    }

    /*
     * Send the user an email warning that their account has been
     * disconnected and he/she has no way to login and must contact
     * the site administrator for help.
     *
     * @param User $user the deauthorizing user
     *
     */
    function emailWarn($user)
    {
        $profile = $user->getProfile();

        $siteName  = common_config('site', 'name');
        $siteEmail = common_config('site', 'email');

        if (empty($siteEmail)) {
            common_log(
                LOG_WARNING,
                    "No site email address configured. Please set one."
            );
        }

        common_switch_locale($user->language);

        $subject = _m('Contact the %s administrator to retrieve your account');

        $msg = <<<BODY
Hi %1$s,

We've noticed you have deauthorized the Facebook connection for your
%2$s account.  You have not set a password for your %2$s account yet, so
you will not be able to login. If you wish to continue using your %2$s
account, please contact the site administrator (%3$s) to set a password.

Sincerely,

%2$s
BODY;
        $body = sprintf(
            _m($msg),
            $user->nickname,
            $siteName,
            $siteEmail
        );

        common_switch_locale();

        if (mail_to_user($user, $subject, $body)) {
            common_log(
                LOG_INFO,
                sprintf(
                    'Sent account lockout warning to %s (%d)',
                    $user->nickname,
                    $user->id
                ),
                __FILE__
            );
        } else {
            common_log(
                LOG_WARNING,
                sprintf(
                    'Unable to send account lockout warning to %s (%d)',
                    $user->nickname,
                    $user->id
                ),
                __FILE__
            );
        }
    }

}