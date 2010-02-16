<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Edit an OAuth Application
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
 * @category  Applications
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
 * Edit the details of an OAuth application
 *
 * This is the form for editing an application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class EditApplicationAction extends OwnerDesignAction
{
    var $msg   = null;
    var $owner = null;
    var $app   = null;

    function title()
    {
        return _('Edit Application');
    }

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to edit an application.'));
            return false;
        }

        $id = (int)$this->arg('id');

        $this->app   = Oauth_application::staticGet($id);
        $this->owner = User::staticGet($this->app->owner);
        $cur         = common_current_user();

        if ($cur->id != $this->owner->id) {
            $this->clientError(_('You are not the owner of this application.'), 401);
        }

        if (!$this->app) {
            $this->clientError(_('No such application.'));
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the app.
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost($args);
        } else {
            $this->showForm();
        }
    }

    function handlePost($args)
    {
        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
            ) {
            $msg = _('The server was unable to handle that much POST ' .
                     'data (%s bytes) due to its current configuration.');
            $this->clientException(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->clientError(_('There was a problem with your session token.'));
            return;
        }

        $cur = common_current_user();

        if ($this->arg('cancel')) {
            common_redirect(common_local_url('showapplication',
                                             array('id' => $this->app->id)), 303);
        } elseif ($this->arg('save')) {
            $this->trySave();
        } else {
            $this->clientError(_('Unexpected form submission.'));
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showContent()
    {
        $form = new ApplicationEditForm($this, $this->app);
        $form->show();
    }

    function showPageNotice()
    {
        if (!empty($this->msg)) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           _('Use this form to edit your application.'));
        }
    }

    function trySave()
    {
        $name         = $this->trimmed('name');
        $description  = $this->trimmed('description');
        $source_url   = $this->trimmed('source_url');
        $organization = $this->trimmed('organization');
        $homepage     = $this->trimmed('homepage');
        $callback_url = $this->trimmed('callback_url');
        $type         = $this->arg('app_type');
        $access_type  = $this->arg('default_access_type');

        if (empty($name)) {
            $this->showForm(_('Name is required.'));
            return;
        } elseif (mb_strlen($name) > 255) {
            $this->showForm(_('Name is too long (max 255 chars).'));
            return;
        } else if ($this->nameExists($name)) {
            $this->showForm(_('Name already in use. Try another one.'));
            return;
        } elseif (empty($description)) {
            $this->showForm(_('Description is required.'));
            return;
        } elseif (Oauth_application::descriptionTooLong($description)) {
            $this->showForm(sprintf(
                _('Description is too long (max %d chars).'),
                                    Oauth_application::maxDescription()));
            return;
        } elseif (mb_strlen($source_url) > 255) {
            $this->showForm(_('Source URL is too long.'));
            return;
        } elseif ((mb_strlen($source_url) > 0)
                  && !Validate::uri($source_url,
                                    array('allowed_schemes' => array('http', 'https'))))
            {
                $this->showForm(_('Source URL is not valid.'));
                return;
        } elseif (empty($organization)) {
            $this->showForm(_('Organization is required.'));
            return;
        } elseif (mb_strlen($organization) > 255) {
            $this->showForm(_('Organization is too long (max 255 chars).'));
            return;
        } elseif (empty($homepage)) {
            $this->showForm(_('Organization homepage is required.'));
            return;
        } elseif ((mb_strlen($homepage) > 0)
                  && !Validate::uri($homepage,
                                    array('allowed_schemes' => array('http', 'https'))))
            {
                $this->showForm(_('Homepage is not a valid URL.'));
                return;
            } elseif (mb_strlen($callback_url) > 255) {
                $this->showForm(_('Callback is too long.'));
                return;
            } elseif (mb_strlen($callback_url) > 0
                      && !Validate::uri($source_url,
                                        array('allowed_schemes' => array('http', 'https'))
                                        ))
                {
                    $this->showForm(_('Callback URL is not valid.'));
                    return;
                }

        $cur = common_current_user();

        // Checked in prepare() above

        assert(!is_null($cur));
        assert(!is_null($this->app));

        $orig = clone($this->app);

        $this->app->name         = $name;
        $this->app->description  = $description;
        $this->app->source_url   = $source_url;
        $this->app->organization = $organization;
        $this->app->homepage     = $homepage;
        $this->app->callback_url = $callback_url;
        $this->app->type         = $type;

        common_debug("access_type = $access_type");

        if ($access_type == 'r') {
            $this->app->access_type = 1;
        } else {
            $this->app->access_type = 3;
        }

        $result = $this->app->update($orig);

        if (!$result) {
            common_log_db_error($this->app, 'UPDATE', __FILE__);
            $this->serverError(_('Could not update application.'));
        }

        $this->app->uploadLogo();

        common_redirect(common_local_url('oauthappssettings'), 303);
    }

    /**
     * Does the app name already exist?
     *
     * Checks the DB to see someone has already registered an app
     * with the same name.
     *
     * @param string $name app name to check
     *
     * @return boolean true if the name already exists
     */

    function nameExists($name)
    {
        $newapp = Oauth_application::staticGet('name', $name);
        if (empty($newapp)) {
            return false;
        } else {
            return $newapp->id != $this->app->id;
        }
    }

}

