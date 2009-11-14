<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for editing an application
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
 * @category  Form
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/form.php';

/**
 * Form for editing an application
 *
 * @category Form
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 */

class ApplicationEditForm extends Form
{
    /**
     * group for user to join
     */

    var $application = null;

    /**
     * Constructor
     *
     * @param Action     $out   output channel
     * @param User_group $group group to join
     */

    function __construct($out=null, $application=null)
    {
        parent::__construct($out);

        $this->application = $application;
    }

    /**
     * ID of the form
     *
     * @return string ID of the form
     */

    function id()
    {
        if ($this->application) {
            return 'form_application_edit-' . $this->application->id;
        } else {
            return 'form_application_add';
        }
    }

    /**
     * class of the form
     *
     * @return string of the form class
     */

    function formClass()
    {
        return 'form_settings';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        if ($this->application) {
            return common_local_url('editapplication',
                                    array('id' => $this->application->id));
        } else {
            return common_local_url('newapplication');
        }
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, _('Register a new application'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        if ($this->application) {
            $id                = $this->application->id;
            $name              = $this->application->name;
            $description       = $this->application->description; 
            $source_url        = $this->application->source_url;
            $organization      = $this->application->organization;
            $homepage          = $this->application->homepage;
            $callback_url      = $this->application->callback_url;
            $this->type        = $this->application->type;
            $this->access_type = $this->application->access_type;
        } else {
            $id                = '';
            $name              = '';
            $description       = '';
            $source_url        = '';
            $organization      = '';
            $homepage          = '';
            $callback_url      = '';
            $this->type        = '';
            $this->access_type = '';
        }

        $this->out->elementStart('ul', 'form_data');
        $this->out->elementStart('li');
        
        $this->out->hidden('application_id', $id);
        $this->out->input('name', _('Name'),
                          ($this->out->arg('name')) ? $this->out->arg('name') : $name);
                    
        $this->out->elementEnd('li');
        
        $this->out->elementStart('li');
        $this->out->input('description', _('Description'),
                          ($this->out->arg('Description')) ? $this->out->arg('discription') : $description);
        $this->out->elementEnd('li');
        
        $this->out->elementStart('li');
        $this->out->input('source_url', _('Source URL'),
                          ($this->out->arg('source_url')) ? $this->out->arg('source_url') : $source_url,
                          _('URL of the homepage of this application'));
        $this->out->elementEnd('li');        

        $this->out->elementStart('li');
        $this->out->input('Organization', _('Organization'),
                          ($this->out->arg('organization')) ? $this->out->arg('organization') : $orgranization,
                          _('Organization responsible for this application'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('homepage', _('Homepage'),
                          ($this->out->arg('homepage')) ? $this->out->arg('homepage') : $homepage,
                          _('URL of the homepage of the organization'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('callback_url', ('Callback URL'),
                          ($this->out->arg('callback_url')) ? $this->out->arg('callback_url') : $callback_url,
                          _('URL to redirect to after authentication'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('type', _('Application type'),
                          ($this->out->arg('type')) ? $this->out->arg('type') : $type,
                          _('Type of application, browser or desktop'));
        $this->out->elementEnd('li');
        
        $this->out->elementStart('li');
        $this->out->input('access_type', _('Default access'),
                          ($this->out->arg('access_type')) ? $this->out->arg('access_type') : $access_type,
                          _('Default access for this application: read-write, or read-only'));
        $this->out->elementEnd('li');
        
        $this->out->elementEnd('ul');
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', _('Save'));
    }
}
