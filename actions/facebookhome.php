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

require_once INSTALLDIR.'/lib/facebookaction.php';

class FacebookhomeAction extends FacebookAction
{

    function handle($args)
    {
        parent::handle($args);

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        // Check to see whether there's already a Facebook link for this user
        $flink = Foreign_link::getByForeignID($fbuid, FACEBOOK_SERVICE);

        // If the user has opted not to initially allow the app to have
        // Facebook status update permission, store that preference. Only
        // promt the user the first time she uses the app
        if ($this->arg('skip')) {
            $facebook->api_client->data_setUserPreference(
                FACEBOOK_PROMPTED_UPDATE_PREF, 'true');
        }

        if ($flink) {

            if ($_POST['submit'] == 'Send') {
                $this->saveNewNotice($flink);
                return;
            }

            $user = $flink->getUser();
            common_set_user($user);

            // If this is the first time the user has started the app
            // prompt for Facebook status update permission
            if (!$facebook->api_client->users_hasAppPermission('status_update')) {

                if ($facebook->api_client->data_getUserPreference(
                        FACEBOOK_PROMPTED_UPDATE_PREF) != 'true') {
                    $this->getUpdatePermission();
                    return;
                }
            }

            // Use is authenticated and has already been prompted once for
            // Facebook status update permission? Then show the main page
            // of the app
            $this->showHome($flink, null);

        } else {

            // User hasn't authenticated yet, prompt for creds
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
                //$this->showHome($flink, _('You can now use Identi.ca from Facebook!'));

                $this->getUpdatePermission();
                return;

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
        $facebook->api_client->data_setUserPreference(
            FACEBOOK_NOTICE_PREFIX, 'dented: ');
        $facebook->api_client->data_setUserPreference(
            FACEBOOK_PROMPTED_UPDATE_PREF, 'false');
    }

    function showHome($flink, $msg)
    {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        $user = $flink->getUser();

        $notice = $user->getCurrentNotice();
        update_profile_box($facebook, $fbuid, $user, $notice);

        $this->showHeader($msg);
        $this->showNoticeForm($user);
        $this->showNav('Home');

        echo $this->showNotices($user);

        $this->showFooter();
    }

    function showNotices($user)
    {

        $page = $this->trimmed('page');
        if (!$page) {
            $page = 1;
        }

        $notice = $user->noticesWithFriends(($page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        $cnt = $this->showNoticeList($notice);

        facebookPagination($page > 1, $cnt > NOTICES_PER_PAGE,
            $page, 'all', array('nickname' => $user->nickname));
    }

    function showNoticeList($notice)
    {
        $nl = new FacebookNoticeList($notice);
        return $nl->show();
    }

    function getUpdatePermission() {

        $facebook = get_facebook();
        $fbuid = $facebook->require_login();

        startFBML();

        $this->showStylesheets();
        $this->showScripts();

        $this->showLogo();

        common_element_start('div', array('class' => 'content'));

        // Figure what the URL of our app is.
        $app_props = $facebook->api_client->Admin_getAppProperties(
                array('canvas_name', 'application_name'));
        $app_url = 'http://apps.facebook.com/' . $app_props['canvas_name'] . '/index.php';
        $app_name = $app_props['application_name'];

        $instructions = sprintf(_('If you would like the %s app to automatically update ' .
            'your Facebook status with your latest notice, you need ' .
            'to give it permission.'), $app_name);

        common_element_start('p');
        common_element('span', array('id' => 'permissions_notice'), $instructions);
        common_element_end('p');

        common_element_start('form', array('method' => 'post',
                                           'action' => $app_url,
                                           'id' => 'facebook-skip-permissions'));

        common_element_start('ul', array('id' => 'fb-permissions-list'));
        common_element_start('li', array('id' => 'fb-permissions-item'));
        common_element_start('fb:prompt-permission', array('perms' => 'status_update',
            'next_fbjs' => 'document.setLocation(\'' . $app_url . '\')'));
        common_element('span', array('class' => 'facebook-button'),
            _('Allow Identi.ca to update my Facebook status'));
        common_element_end('fb:prompt-permission');
        common_element_end('li');

        common_element_start('li', array('id' => 'fb-permissions-item'));
        common_submit('skip', _('Skip'));
        common_element_end('li');
        common_element_end('ul');

        common_element_end('form');
        common_element_end('div');

        common_end_xml();

    }

    function saveNewNotice($flink)
    {

        $user = $flink->getUser();

        $content = $_POST['status_textarea'];

        if (!$content) {
            $this->showHome($flink, _('No content!'));
            return;
        } else {
            $content_shortened = common_shorten_links($content);

            if (mb_strlen($content_shortened) > 140) {
                common_debug("Content = '$content_shortened'", __FILE__);
                common_debug("mb_strlen(\$content) = " . mb_strlen($content_shortened), __FILE__);
                $this->showHome($flink, _('That\'s too long. Max notice size is 140 chars.'));
                return;
            }
        }

        $inter = new CommandInterpreter();

        $cmd = $inter->handle_command($user, $content_shortened);

        if ($cmd) {
            $cmd->execute(new WebChannel());
            return;
        }

        $replyto = $this->trimmed('inreplyto');

        $notice = Notice::saveNew($user->id, $content,
            'Facebook', 1, ($replyto == 'false') ? null : $replyto);

        if (is_string($notice)) {
            $this->showHome($flink, 'Error!');
            return;
        }

        common_broadcast_notice($notice);
        $this->showHome($flink, 'Success!');
    }

}
