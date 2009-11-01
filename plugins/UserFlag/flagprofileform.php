<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Form for flagging a profile
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

require_once INSTALLDIR.'/lib/form.php';

/**
 * Form for flagging a profile
 *
 * A form for flagging a profile
 *
 * @category Form
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class FlagProfileForm extends Form
{
    /**
     * Profile of profile to flag
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
     * @param Profile       $profile profile of user to flag
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
        return 'flagprofile-' . $this->profile->id;
    }

    /**
     * class of the form
     *
     * @return string class of the form
     */

    function formClass()
    {
        return 'form_profile_flag';
    }

    /**
     * Action of the form
     *
     * @return string URL of the action
     */

    function action()
    {
        return common_local_url('flagprofile');
    }

    /**
     * Legend of the Form
     *
     * @return void
     */
    function formLegend()
    {
        $this->out->element('legend', null, _('Flag this profile'));
    }

    /**
     * Data elements of the form
     *
     * @return void
     */

    function formData()
    {
        // TODO: let the user choose a flag

        $this->out->hidden('flagprofileto-' . $this->profile->id,
                           $this->profile->id,
                           'flagprofileto');
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
        $this->out->submit('submit', _('Flag'), 'submit', null, _('Flag this profile'));
    }
}
