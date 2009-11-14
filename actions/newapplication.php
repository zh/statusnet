<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Register a new OAuth Application
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
 * Add a new application
 *
 * This is the form for adding a new application
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class NewApplicationAction extends Action
{
    var $msg;

    function title()
    {
        return _('New Application');
    }

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('You must be logged in to create a group.'));
            return false;
        }

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the group.
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->trySave();
        } else {
            $this->showForm();
        }
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showContent()
    {
        $form = new ApplicationEditForm($this);
        $form->show();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else {
            $this->element('p', 'instructions',
                           _('Use this form to register a new application.'));
        }
    }

    function trySave()
    {
        $name              = $this->trimmed('name');
        $description       = $this->trimmed('description'); 
        $source_url        = $this->trimmed('source_url');
        $organization      = $this->trimmed('organization');
        $homepage          = $this->trimmed('application');
        $callback_url      = $this->trimmed('callback_url');
        $this->type        = $this->trimmed('type');
        $this->access_type = $this->trimmed('access_type');
         
        if (!is_null($name) && mb_strlen($name) > 255) {
            $this->showForm(_('Name is too long (max 255 chars).'));
            return;
        } else if (User_group::descriptionTooLong($description)) {
            $this->showForm(sprintf(
                _('description is too long (max %d chars).'), 
                Oauth_application::maxDescription()));
            return;
        } elseif (!is_null($source_url) 
            && (strlen($source_url) > 0) 
            && !Validate::uri(
                $source_url,
                array('allowed_schemes' => array('http', 'https'))
                )
            ) 
        {
            $this->showForm(_('Source URL is not valid.'));
            return;
        } elseif (!is_null($homepage) 
            && (strlen($homepage) > 0) 
            && !Validate::uri(
                $homepage,
                array('allowed_schemes' => array('http', 'https'))
                )
            ) 
        {
            $this->showForm(_('Homepage is not a valid URL.'));
            return; 
        } elseif (!is_null($callback_url) 
            && (strlen($callback_url) > 0) 
            && !Validate::uri(
                $source_url,
                array('allowed_schemes' => array('http', 'https'))
                )
            ) 
        {
            $this->showForm(_('Callback URL is not valid.'));
            return;
        }
        
        $cur = common_current_user();

        // Checked in prepare() above

        assert(!is_null($cur));

        $app = new Oauth_application();

        $app->query('BEGIN');

        $app->name    = $name;
        $app->owner  = $cur->id;
        $app->description = $description;
        $app->source_url = $souce_url;
        $app->organization = $organization;
        $app->homepage = $homepage;
        $app->callback_url = $callback_url;
        $app->type = $type;
        $app->access_type = $access_type;
        
        // generate consumer key and secret
   
        $app->created     = common_sql_now();

        $result = $app->insert();

        if (!$result) {
            common_log_db_error($group, 'INSERT', __FILE__);
            $this->serverError(_('Could not create application.'));
        }
       
        $group->query('COMMIT');

        common_redirect($group->homeUrl(), 303);
        
    }

}

