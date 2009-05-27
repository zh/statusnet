<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Settings for Twitter integration
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
 * @category  Settings
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/connectsettingsaction.php';
require_once INSTALLDIR.'/lib/twitter.php';

define('SUBSCRIPTIONS', 80);

/**
 * Settings for Twitter integration
 *
 * @category Settings
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      SettingsAction
 */

class TwittersettingsAction extends ConnectSettingsAction
{
    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('Twitter settings');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Add your Twitter account to automatically send '.
                 ' your notices to Twitter, ' .
                 'and subscribe to Twitter friends already here.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for associating a Twitter account with this
     * Laconica account. Also lets the user set preferences.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $fuser = null;

        $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

        if ($flink) {
            $fuser = $flink->getForeignUser();
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_twitter',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('twittersettings')));
        $this->elementStart('fieldset', array('id' => 'settings_twitter_account'));
        $this->element('legend', null, _('Twitter Account'));
        $this->hidden('token', common_session_token());
        if ($fuser) {
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li', array('id' => 'settings_twitter_remove'));
            $this->element('span', 'twitter_user', $fuser->nickname);
            $this->element('a', array('href' => $fuser->uri), $fuser->uri);
            $this->element('p', 'form_note',
                           _('Current verified Twitter account.'));
            $this->hidden('flink_foreign_id', $flink->foreign_id);
            $this->elementEnd('li');
            $this->elementEnd('ul');
            $this->submit('remove', _('Remove'));
        } else {
            $this->elementStart('ul', 'form_data');
            $this->elementStart('li', array('id' => 'settings_twitter_login'));
            $this->input('twitter_username', _('Twitter user name'),
                         ($this->arg('twitter_username')) ?
                         $this->arg('twitter_username') :
                         $profile->nickname,
                         _('No spaces, please.')); // hey, it's what Twitter says
            $this->elementEnd('li');
            $this->elementStart('li');
            $this->password('twitter_password', _('Twitter password'));
            $this->elementend('li');
            $this->elementEnd('ul');
        }
        $this->elementEnd('fieldset');

        $this->elementStart('fieldset',
                            array('id' => 'settings_twitter_preferences'));
        $this->element('legend', null, _('Preferences'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->checkbox('noticesync',
                        _('Automatically send my notices to Twitter.'),
                        ($flink) ?
                        ($flink->noticesync & FOREIGN_NOTICE_SEND) :
                        true);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('replysync',
                        _('Send local "@" replies to Twitter.'),
                        ($flink) ?
                        ($flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) :
                        true);
        $this->elementEnd('li');
        $this->elementStart('li');
        $this->checkbox('friendsync',
                        _('Subscribe to my Twitter friends here.'),
                        ($flink) ?
                        ($flink->friendsync & FOREIGN_FRIEND_RECV) :
                        false);
        $this->elementEnd('li');
        $this->elementEnd('ul');

        if ($flink) {
            $this->submit('save', _('Save'));
        } else {
            $this->submit('add', _('Add'));
        }
        $this->elementEnd('fieldset');

        $this->showTwitterSubscriptions();

        $this->elementEnd('form');
    }

    /**
     * Gets some of the user's Twitter friends
     *
     * Gets the number of Twitter friends that are on this
     * instance of Laconica.
     *
     * @return array array of User objects
     */

    function subscribedTwitterUsers()
    {

        $current_user = common_current_user();

        $qry = 'SELECT "user".* ' .
          'FROM subscription ' .
          'JOIN "user" ON subscription.subscribed = "user".id ' .
          'JOIN foreign_link ON foreign_link.user_id = "user".id ' .
          'WHERE subscriber = %d ' .
          'ORDER BY "user".nickname';

        $user = new User();

        $user->query(sprintf($qry, $current_user->id));

        $users = array();

        while ($user->fetch()) {

            // Don't include the user's own self-subscription
            if ($user->id != $current_user->id) {
                $users[] = clone($user);
            }
        }

        return $users;
    }

    /**
     * Show user's Twitter friends
     *
     * Gets the number of Twitter friends that are on this
     * instance of Laconica, and shows their mini-avatars.
     *
     * @return void
     */

    function showTwitterSubscriptions()
    {

        $friends = $this->subscribedTwitterUsers();

        $friends_count = count($friends);

        if ($friends_count > 0) {
            $this->elementStart('div', array('id' => 'entity_subscriptions',
                                             'class' => 'section'));
            $this->element('h2', null, _('Twitter Friends'));
            $this->elementStart('ul', 'entities users xoxo');

            for ($i = 0; $i < min($friends_count, SUBSCRIPTIONS); $i++) {

                $other = Profile::staticGet($friends[$i]->id);

                if (!$other) {
                    common_log_db_error($subs, 'SELECT', __FILE__);
                    continue;
                }

                $this->elementStart('li', 'vcard');
                $this->elementStart('a', array('title' => ($other->fullname) ?
                                               $other->fullname :
                                               $other->nickname,
                                               'href' => $other->profileurl,
                                               'class' => 'url'));

                $avatar = $other->getAvatar(AVATAR_MINI_SIZE);

                $avatar_url = ($avatar) ?
                  $avatar->displayUrl() :
                  Avatar::defaultImage(AVATAR_MINI_SIZE);

                $this->element('img', array('src' => $avatar_url,
                                            'width' => AVATAR_MINI_SIZE,
                                            'height' => AVATAR_MINI_SIZE,
                                            'class' => 'avatar photo',
                                            'alt' =>  ($other->fullname) ?
                                            $other->fullname :
                                            $other->nickname));

                $this->element('span', 'fn nickname', $other->nickname);
                $this->elementEnd('a');
                $this->elementEnd('li');

            }

            $this->elementEnd('ul');
            $this->elementEnd('div');

        }
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
     *
     * @return void
     */

    function handlePost()
    {

        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->savePreferences();
        } else if ($this->arg('add')) {
            $this->addTwitterAccount();
        } else if ($this->arg('remove')) {
            $this->removeTwitterAccount();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Associate a Twitter account with the user's account
     *
     * Validates post input; verifies it against Twitter; and if
     * successful stores in the database.
     *
     * @return void
     */

    function addTwitterAccount()
    {
        $screen_name = $this->trimmed('twitter_username');
        $password    = $this->trimmed('twitter_password');
        $noticesync  = $this->boolean('noticesync');
        $replysync   = $this->boolean('replysync');
        $friendsync  = $this->boolean('friendsync');

        if (!Validate::string($screen_name,
                              array('min_length' => 1,
                                    'max_length' => 15,
                                    'format' => VALIDATE_NUM.VALIDATE_ALPHA.'_'))) {
            $this->showForm(_('Username must have only numbers, '.
                              'upper- and lowercase letters, '.
                              'and underscore (_). 15 chars max.'));
            return;
        }

        if (!$this->verifyCredentials($screen_name, $password)) {
            $this->showForm(_('Could not verify your Twitter credentials!'));
            return;
        }

        $twit_user = twitter_user_info($screen_name, $password);

        if (!$twit_user) {
            $this->showForm(sprintf(_('Unable to retrieve account information '.
                                      'For "%s" from Twitter.'),
                                    $screen_name));
            return;
        }

        if (!save_twitter_user($twit_user->id, $screen_name)) {
            $this->showForm(_('Unable to save your Twitter settings!'));
            return;
        }

        $user = common_current_user();

        $flink = new Foreign_link();

        $flink->user_id     = $user->id;
        $flink->foreign_id  = $twit_user->id;
        $flink->service     = TWITTER_SERVICE;
        $flink->credentials = $password;
        $flink->created     = common_sql_now();

        $flink->set_flags($noticesync, $replysync, $friendsync);

        $flink_id = $flink->insert();

        if (!$flink_id) {
            common_log_db_error($flink, 'INSERT', __FILE__);
            $this->showForm(_('Unable to save your Twitter settings!'));
            return;
        }

        if ($friendsync) {
            save_twitter_friends($user, $twit_user->id, $screen_name, $password);
            $flink->last_friendsync = common_sql_now();
            $flink->update();
        }

        $this->showForm(_('Twitter settings saved.'), true);
    }

    /**
     * Disassociate an existing Twitter account from this account
     *
     * @return void
     */

    function removeTwitterAccount()
    {
        $user = common_current_user();

        $flink = Foreign_link::getByUserID($user->id, 1);

        $flink_foreign_id = $this->arg('flink_foreign_id');

        // Maybe an old tab open...?
        if ($flink->foreign_id != $flink_foreign_id) {
            $this->showForm(_('That is not your Twitter account.'));
            return;
        }

        $result = $flink->delete();

        if (!$result) {
            common_log_db_error($flink, 'DELETE', __FILE__);
            $this->serverError(_('Couldn\'t remove Twitter user.'));
            return;
        }

        $this->showForm(_('Twitter account removed.'), true);
    }

    /**
     * Save user's Twitter-bridging preferences
     *
     * @return void
     */

    function savePreferences()
    {
        $noticesync = $this->boolean('noticesync');
        $friendsync = $this->boolean('friendsync');
        $replysync  = $this->boolean('replysync');

        $user = common_current_user();

        $flink = Foreign_link::getByUserID($user->id, 1);

        if (!$flink) {
            common_log_db_error($flink, 'SELECT', __FILE__);
            $this->showForm(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        $twitter_id = $flink->foreign_id;
        $password   = $flink->credentials;

        $fuser = $flink->getForeignUser();

        if (!$fuser) {
            common_log_db_error($fuser, 'SELECT', __FILE__);
            $this->showForm(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        $screen_name = $fuser->nickname;

        $original = clone($flink);

        $flink->set_flags($noticesync, $replysync, $friendsync);

        $result = $flink->update($original);

        if ($result === false) {
            common_log_db_error($flink, 'UPDATE', __FILE__);
            $this->showForm(_('Couldn\'t save Twitter preferences.'));
            return;
        }

        if ($friendsync) {
            save_twitter_friends($user, $flink->foreign_id, $screen_name, $password);
        }

        $this->showForm(_('Twitter preferences saved.'), true);
    }

    /**
     * Verifies a username and password against Twitter's API
     *
     * @param string $screen_name Twitter user name
     * @param string $password    Twitter password
     *
     * @return boolean success flag
     */

    function verifyCredentials($screen_name, $password)
    {
        $uri = 'http://twitter.com/account/verify_credentials.json';

        $data = get_twitter_data($uri, $screen_name, $password);

        if (!$data) {
            return false;
        }

        $user = json_decode($data);

        if (!$user) {
            return false;
        }

        $twitter_id = $user->id;

        if ($twitter_id) {
            return $twitter_id;
        }

        return false;
    }

}
