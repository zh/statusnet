<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show an OAuth application
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
 * @category  Application
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Show an OAuth application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ShowApplicationAction extends OwnerDesignAction
{
    /**
     * Application to show
     */

    var $application = null;

    /**
     * User who owns the app
     */

    var $owner = null;

    var $msg = null;

    var $success = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        $id = (int)$this->arg('id');

        $this->application  = Oauth_application::staticGet($id);
        $this->owner        = User::staticGet($this->application->owner);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to view an application.'));
            return false;
        }

        if (empty($this->application)) {
            $this->clientError(_('No such application.'), 404);
            return false;
        }

        $cur = common_current_user();

        if ($cur->id != $this->owner->id) {
            $this->clientError(_('You are not the owner of this application.'), 401);
        }

        return true;
    }

    /**
     * Handle the request
     *
     * Shows info about the app
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            // CSRF protection
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->clientError(_('There was a problem with your session token.'));
                return;
            }

            if ($this->arg('reset')) {
                $this->resetKey();
            }
        } else {
            $this->showPage();
        }
    }

    /**
     * Title of the page
     *
     * @return string title of the page
     */

    function title()
    {
        if (!empty($this->application->name)) {
            return 'Application: ' . $this->application->name;
        }
    }

    function showPageNotice()
    {
        if (!empty($this->msg)) {
            $this->element('div', ($this->success) ? 'success' : 'error', $this->msg);
        }
    }

    function showContent()
    {

        $cur = common_current_user();

        $this->elementStart('div', 'entity_actions');

        $this->element('a',
            array('href' =>
                common_local_url(
                    'editapplication',
                    array(
                        'nickname' => $this->owner->nickname,
                        'id' => $this->application->id
                    )
                )
            ), 'Edit application');

        $this->elementStart('form', array(
            'id' => 'forma_reset_key',
            'class' => 'form_reset_key',
            'method' => 'POST',
            'action' => common_local_url('showapplication',
                array('nickname' => $cur->nickname,
                      'id' => $this->application->id))));

        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->submit('reset', _('Reset Consumer key/secret'));
        $this->elementEnd('fieldset');
        $this->elementEnd('form');

        $this->elementEnd('div');

        $consumer = $this->application->getConsumer();

        $this->elementStart('div', 'entity-application');

        $this->elementStart('ul', 'entity_application_details');

	$this->elementStart('li', 'entity_application-icon');

	if (!empty($this->application->icon)) {
	    $this->element('img', array('src' => $this->application->icon));
	}

	$this->elementEnd('li');

        $this->elementStart('li', 'entity_application_name');
        $this->element('span', array('class' => 'big'), $this->application->name);
        $this->raw(sprintf(_(' by %1$s'), $this->application->organization));
        $this->elementEnd('li');

        $this->element('li', 'entity_application_description', $this->application->description);

        $this->elementStart('li', 'entity_application_statistics');

        $defaultAccess = ($this->application->access_type & Oauth_application::$writeAccess)
            ? 'read-write' : 'read-only';
        $profile = Profile::staticGet($this->application->owner);
        $userCnt = 0; // XXX: count how many users use the app

        $this->raw(sprintf(
            _('Created by %1$s - %2$s access by default - %3$d users.'),
              $profile->getBestName(),
              $defaultAccess,
              $userCnt
            ));

        $this->elementEnd('li');

        $this->elementEnd('ul');

        $this->elementStart('dl', 'entity_consumer_key');
        $this->element('dt', null, _('Consumer key'));
        $this->element('dd', 'label', $consumer->consumer_key);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_consumer_secret');
        $this->element('dt', null, _('Consumer secret'));
        $this->element('dd', 'label', $consumer->consumer_secret);
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_request_token_url');
        $this->element('dt', null, _('Request token URL'));
        $this->element('dd', 'label', common_local_url('apioauthrequesttoken'));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_access_token_url');
        $this->element('dt', null, _('Access token URL'));
        $this->element('dd', 'label', common_local_url('apioauthaccesstoken'));
        $this->elementEnd('dl');

        $this->elementStart('dl', 'entity_authorize_url');
        $this->element('dt', null, _('Authorize URL'));
        $this->element('dd', 'label', common_local_url('apioauthauthorize'));
        $this->elementEnd('dl');

        $this->element('p', 'oauth-signature-note',
            '*We support hmac-sha1 signatures. We do not support the plaintext signature method.');

        $this->elementEnd('div');

        $this->elementStart('p', array('id' => 'application_action'));
        $this->element('a',
            array(
                'href' => common_local_url(
                    'apps',
                    array('nickname' => $this->owner->nickname)),
                'class' => 'more'
            ),
            'View your applications');
        $this->elementEnd('p');
    }

    function resetKey()
    {
        $this->application->query('BEGIN');

        $consumer = $this->application->getConsumer();
        $result = $consumer->delete();

        if (!$result) {
            common_log_db_error($consumer, 'DELETE', __FILE__);
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $consumer = Consumer::generateNew();

        $result = $consumer->insert();

        if (!$result) {
            common_log_db_error($consumer, 'INSERT', __FILE__);
            $this->application->query('ROLLBACK');
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $orig = clone($this->application);
        $this->application->consumer_key = $consumer->consumer_key;
        $result = $this->application->update($orig);

        if (!$result) {
            common_log_db_error($application, 'UPDATE', __FILE__);
            $this->application->query('ROLLBACK');
            $this->success = false;
            $this->msg = ('Unable to reset consumer key and secret.');
            $this->showPage();
            return;
        }

        $this->application->query('COMMIT');

        $this->success = true;
        $this->msg = ('Consumer key and secret reset.');
        $this->showPage();
    }

}

