<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/facebookaction.php');

class FacebookhomeAction extends FacebookAction
{

    function handle($args)
    {
        parent::handle($args);

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        // Check to see whether there's already a Facebook link for this user
        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        if ($flink) {
            $this->showHome($flink, null);
        } else {
            $this->login($fbuid);
        }

    }

    function login($fbuid)
    {
        $nickname = common_canonical_nickname($this->trimmed('nickname'));
        $password = $this->arg('password');

        $msg = null;

        if ($nickname) {

            if (common_check_user($nickname, $password)) {

                $user = User::staticGet('nickname', $nickname);

                if (!$user) {
                    $this->showLoginForm(_("Server error - couldn't get user!"));
                }

                $flink = DB_DataObject::factory('foreign_link');
                $flink->user_id = $user->id;
                $flink->foreign_id = $fbuid;
                $flink->service = FACEBOOK_SERVICE;
                $flink->created = common_sql_now();
                $flink->set_flags(true, false, false);

                $flink_id = $flink->insert();

                // XXX: Do some error handling here

                $this->setDefaults();

                $this->showHome($flink, _('You can now use Identi.ca from Facebook!'));

            } else {
                $msg = _('Incorrect username or password.');
            }
        }

        $this->showLoginForm($msg);
    }

    function setDefaults()
    {
        $facebook = get_facebook();

        // A default prefix string for notices
        $facebook->api_client->data_setUserPreference(1, 'dented: ');
    }

    function showHome($flink, $msg)
    {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $user = $flink->getUser();

        $notice = $user->getCurrentNotice();
        update_profile_box($facebook, $fbuid, $user, $notice);


        $this->show_header('Home');

        if ($msg) {
            $this->element('fb:success', array('message' => $msg));
        }

        echo $this->show_notices($user);

        $this->show_footer();
    }

    function show_notices($user)
    {

        $page = $this->trimmed('page');
        if (!$page) {
            $page = 1;
        }

        $notice = $user->noticesWithFriends(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $cnt = $this->show_notice_list($notice);

        common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'all', array('nickname' => $user->nickname));
    }

    function show_notice_list($notice)
    {
        $nl = new NoticeList($notice);
        return $nl->show();
    }

}
