<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Yammer import administration panel
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

class YammeradminpanelAction extends AdminPanelAction
{
    /**
     * Returns the page title
     *
     * @return string page title
     */
    function title()
    {
        return _m('Yammer Import');
    }

    /**
     * Instructions for using this form.
     *
     * @return string instructions
     */
    function getInstructions()
    {
        return _m('Yammer import tool');
    }

    /**
     * Show the Yammer admin panel form
     *
     * @return void
     */
    function showForm()
    {
        $form = new YammerAdminPanelForm($this);
        $form->show();
        return;
    }
}

class YammerAdminPanelForm extends AdminForm
{
    /**
     * ID of the form
     *
     * @return string ID of the form
     */
    function id()
    {
        return 'yammeradminpanel';
    }

    /**
     * class of the form
     *
     * @return string class of the form
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
        return common_local_url('yammeradminpanel');
    }

    /**
     * Data elements of the form
     *
     * @return void
     */
    function formData()
    {
        $this->out->element('p', array(), 'yammer import IN DA HOUSE');
        
        /*
        Possible states of the yammer import process:
        - null (not doing any sort of import)
        - requesting-auth
        - authenticated
        - import-users
        - import-groups
        - fetch-messages
        - import-messages
        - done
        */
        $yammerState = Yammer_state::staticGet('id', 1);
        $state = $yammerState ? $yammerState->state || null;
        
        switch($state)
        {
            case null:
                $this->out->element('p', array(), 'Time to start auth:');
                $this->showAuthForm();
                break;
            case 'requesting-auth':
                $this->out->element('p', array(), 'Need to finish auth!');
                $this->showAuthForm();
                break;
            case 'import-users':
            case 'import-groups':
            case 'import-messages':
            case 'save-messages':
                $this->showImportState();
                break;
            
        }
    }

    /**
     * Action elements
     *
     * @return void
     */
    function formActions()
    {
        // No submit buttons needed at bottom
    }
}
