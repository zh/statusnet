<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for forms that operate on a profile
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Superclass for forms that operate on a profile
 *
 * Certain forms (block, silence, userflag, sandbox, delete) work on
 * a single profile and work almost the same. So, this form extracts
 * a lot of the common code to simplify those forms.
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ProfileActionForm extends Form
{
    /**
     * Profile of user to act on
     */

    var $profile = null;

    /**
     * Return-to args
     */

    var $args = null;

    /**
     * Constructor
     *
     * @param HTMLOutputter $out     output channel
     * @param Profile       $profile profile of user to act on
     * @param array         $args    return-to args
     */

    function __construct($out=null, $profile=null, $args=null)
    {
        parent::__construct($out);

        $this->profile = $profile;
        $this->args    = $args;
    }

    /**
     * ID of the form
     *
     * @return int ID of the form
     */

    function id()
    {
        return $this->target() . '-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_user_'.$this->target();
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url($this->target());
    }

    /**
     * Legend of the Form
     *
     * @return void
     */

    function formLegend()
    {
        $this->out->element('legend', null, $this->description());
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        $action = $this->target();

        $this->out->hidden($action.'to-' . $this->profile->id,
                           $this->profile->id,
                           'profileid');

        if ($this->args) {
            foreach ($this->args as $k => $v) {
                $this->out->hidden('returnto-' . $k, $v);
            }
        }
    }

    /**
     * Action elements
     *
     * @return void
     */

    function formActions()
    {
        $this->out->submit('submit', $this->title(), 'submit',
                           null, $this->description());
    }

    /**
     * Action this form targets
     *
     * @return string Name of the action, lowercased.
     */

    function target()
    {
        return null;
    }

    /**
     * Title of the form
     *
     * @return string Title of the form, internationalized
     */

    function title()
    {
        return null;
    }

    /**
     * Description of the form
     *
     * @return string description of the form, internationalized
     */

    function description()
    {
        return null;
    }
}
