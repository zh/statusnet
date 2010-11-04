<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for Facebook
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
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Settings for Facebook
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class FacebooksettingsAction extends ConnectSettingsAction
{
    private $facebook;
    private $flink;
    private $user;
    
    function prepare($args)
    {
        parent::prepare($args);

        $this->facebook = new Facebook(
            array(
                'appId'  => common_config('facebook', 'appid'),
                'secret' => common_config('facebook', 'secret'),
                'cookie' => true,
            )
        );

        $this->user = common_current_user();
        $this->flink = Foreign_link::getByUserID($this->user->id, FACEBOOK_SERVICE);

        return true;
    }

    function handlePost($args)
    {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(
                _m('There was a problem with your session token. Try again, please.')
            );
            return;
        }

        if ($this->arg('save')) {
            $this->saveSettings();
        } else if ($this->arg('disconnect')) {
            $this->disconnect();
        }
    }

    function title()
    {
        // TRANS: Page title for Facebook settings.
        return _m('Facebook settings');
    }
    
    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('Facebook settings');
    }

    function showContent()
    {

        if (empty($this->flink)) {

            $this->element(
                'p',
                'instructions',
                _m('There is no Facebook user connected to this account.')
            );

            $attrs = array(
                'show-faces' => 'true',
                'perms' => 'user_location,user_website,offline_access,publish_stream'
            );

            $this->element('fb:login-button', $attrs);
            

        } else {

            $this->elementStart(
                'form',
                array(
                    'method' => 'post',
                    'id'     => 'form_settings_facebook',
                    'class'  => 'form_settings',
                    'action' => common_local_url('facebooksettings')
                )
            );

            $this->hidden('token', common_session_token());

            $this->element('p', 'form_note', _m('Connected Facebook user'));

            $this->elementStart('p', array('class' => 'facebook-user-display'));

            $this->elementStart(
                'fb:profile-pic',
                array('uid' => $this->flink->foreign_id,
                      'size' => 'small',
                      'linked' => 'true',
                      'facebook-logo' => 'true')
            );
            $this->elementEnd('fb:profile-pic');

            $this->elementStart(
                'fb:name',
                array('uid' => $this->flink->foreign_id, 'useyou' => 'false')
            );

            $this->elementEnd('fb:name');

            $this->elementEnd('p');

            $this->elementStart('ul', 'form_data');

            $this->elementStart('li');

            $this->checkbox(
                'noticesync',
                _m('Publish my notices to Facebook.'),
                ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND) : true
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            $this->checkbox(
                'replysync',
                _m('Send "@" replies to Facebook.'),
                ($this->flink) ? ($this->flink->noticesync & FOREIGN_NOTICE_SEND_REPLY) : true
            );

            $this->elementEnd('li');

            $this->elementStart('li');

            // TRANS: Submit button to save synchronisation settings.
            $this->submit('save', _m('BUTTON','Save'));

            $this->elementEnd('li');
        
            $this->elementEnd('ul');

            $this->elementStart('fieldset');

            // TRANS: Legend.
            $this->element('legend', null, _m('Disconnect my account from Facebook'));

            if (empty($this->user->password)) {

                $this->elementStart('p', array('class' => 'form_guide'));
                // @todo FIXME: Bad i18n. Patchwork message in three parts.
                // TRANS: Followed by a link containing text "set a password".
                $this->text(_m('Disconnecting your Faceboook ' .
                               'would make it impossible to log in! Please '));
                $this->element('a',
                    array('href' => common_local_url('passwordsettings')),
                        // TRANS: Preceded by "Please " and followed by " first."
                        _m('set a password'));
                // TRANS: Preceded by "Please set a password".
                $this->text(_m(' first.'));
                $this->elementEnd('p');
            } else {

                $note = 'Keep your %s account but disconnect from Facebook. ' .
                    'You\'ll use your %s password to log in.';

                $site = common_config('site', 'name');

                $this->element('p', 'instructions',
                    sprintf($note, $site, $site));

                // TRANS: Submit button.
                $this->submit('disconnect', _m('BUTTON','Disconnect'));
            }

            $this->elementEnd('fieldset');

            $this->elementEnd('form');
        }
    }

    function saveSettings()
    {

        $noticesync = $this->boolean('noticesync');
        $replysync  = $this->boolean('replysync');

        $original = clone($this->flink);
        $this->flink->set_flags($noticesync, false, $replysync, false);
        $result = $this->flink->update($original);

        if ($result === false) {
            $this->showForm(_m('There was a problem saving your sync preferences.'));
        } else {
            // TRANS: Confirmation that synchronisation settings have been saved into the system.
            $this->showForm(_m('Sync preferences saved.'), true);
        }
    }

    function disconnect()
    {
        $flink = Foreign_link::getByUserID($this->user->id, FACEBOOK_SERVICE);
        $result = $flink->delete();

        if ($result === false) {
            common_log_db_error($user, 'DELETE', __FILE__);
            $this->serverError(_m('Couldn\'t delete link to Facebook.'));
            return;
        }

        $this->showForm(_m('You have disconnected from Facebook.'), true);

    }
}

