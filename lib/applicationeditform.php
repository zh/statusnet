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
        $cur = common_current_user();

        if (!empty($this->application)) {
            return common_local_url('editapplication',
                array('id' => $this->application->id,
                      'nickname' => $cur->nickname)
            );
        } else {
            return common_local_url('newapplication',
                array('nickname' => $cur->nickname));
        }
    }

    /**
     * Name of the form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, _('Edit application'));
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
        $this->out->hidden('token', common_session_token());

        $this->out->input('name', _('Name'),
                          ($this->out->arg('name')) ? $this->out->arg('name') : $name);

        $this->out->elementEnd('li');

        $this->out->elementStart('li');

        $maxDesc = Oauth_application::maxDesc();
        if ($maxDesc > 0) {
            $descInstr = sprintf(_('Describe your application in %d chars'),
                                $maxDesc);
        } else {
            $descInstr = _('Describe your application');
        }
        $this->out->textarea('description', _('Description'),
                        ($this->out->arg('description')) ? $this->out->arg('description') : $description,
                        $descInstr);

        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('source_url', _('Source URL'),
                          ($this->out->arg('source_url')) ? $this->out->arg('source_url') : $source_url,
                          _('URL of the homepage of this application'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('organization', _('Organization'),
                          ($this->out->arg('organization')) ? $this->out->arg('organization') : $organization,
                          _('Organization responsible for this application'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('homepage', _('Homepage'),
                          ($this->out->arg('homepage')) ? $this->out->arg('homepage') : $homepage,
                          _('URL for the homepage of the organization'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');
        $this->out->input('callback_url', ('Callback URL'),
                          ($this->out->arg('callback_url')) ? $this->out->arg('callback_url') : $callback_url,
                          _('URL to redirect to after authentication'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');

        $attrs = array('name' => 'app_type',
                       'type' => 'radio',
                       'id' => 'app_type-browser',
                       'class' => 'radio',
                       'value' => Oauth_application::$browser);

        // Default to Browser

        if ($this->application->type == Oauth_application::$browser
            || empty($this->applicaiton->type)) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'app_type-browser',
                                      'class' => 'radio'),
                                      _('Browser'));

        $attrs = array('name' => 'app_type',
                       'type' => 'radio',
                       'id' => 'app_type-dekstop',
                       'class' => 'radio',
                       'value' => Oauth_application::$desktop);

        if ($this->application->type == Oauth_application::$desktop) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'app_type-desktop',
                                      'class' => 'radio'),
                                      _('Desktop'));
        $this->out->element('p', 'form_guide', _('Type of application, browser or desktop'));
        $this->out->elementEnd('li');

        $this->out->elementStart('li');

        $attrs = array('name' => 'default_access_type',
                       'type' => 'radio',
                       'id' => 'default_access_type-r',
                       'class' => 'radio',
                       'value' => 'r');

        // default to read-only access

        if ($this->application->access_type & Oauth_application::$readAccess
            || empty($this->application->access_type)) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'default_access_type-ro',
                                      'class' => 'radio'),
                                      _('Read-only'));

        $attrs = array('name' => 'default_access_type',
                       'type' => 'radio',
                       'id' => 'default_access_type-rw',
                       'class' => 'radio',
                       'value' => 'rw');

        if ($this->application->access_type & Oauth_application::$readAccess
            && $this->application->access_type & Oauth_application::$writeAccess
        ) {
            $attrs['checked'] = 'checked';
        }

        $this->out->element('input', $attrs);

        $this->out->element('label', array('for' => 'default_access_type-rw',
                                      'class' => 'radio'),
                                      _('Read-write'));
        $this->out->element('p', 'form_guide', _('Default access for this application: read-only, or read-write'));

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
        $this->out->submit('save', _('Save'));
        $this->out->submit('cancel', _('Cancel'));
    }
}
