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

    function showStylesheets()
    {
        parent::showStylesheets();
        $this->cssLink('plugins/YammerImport/css/admin.css', null, 'screen, projection, tv');
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
        $runner = YammerRunner::init();

        switch($runner->state())
        {
            case 'init':
            case 'requesting-auth':
                $this->showAuthForm();
            default:
        }
        $this->showImportState($runner);
    }

    private function showAuthForm()
    {
        $this->out->element('p', array(), 'show an auth form');
    }

    private function showImportState(YammerRunner $runner)
    {
        $userCount = $runner->countUsers();
        $groupCount = $runner->countGroups();
        $fetchedCount = $runner->countFetchedNotices();
        $savedCount = $runner->countSavedNotices();

        $labels = array(
            'init' => array(
                'label' => _m("Initialize"),
                'progress' => _m('No import running'),
                'complete' => _m('Initiated Yammer server connection...'),
            ),
            'requesting-auth' => array(
                'label' => _m('Connect to Yammer'),
                'progress' => _m('Awaiting authorization...'),
                'complete' => _m('Connected.'),
            ),
            'import-users' => array(
                'label' => _m('Import user accounts'),
                'progress' => sprintf(_m("Importing %d user...", "Importing %d users...", $userCount), $userCount),
                'complete' => sprintf(_m("Imported %d user.", "Imported %d users.", $userCount), $userCount),
            ),
            'import-groups' => array(
                'label' => _m('Import user groups'),
                'progress' => sprintf(_m("Importing %d group...", "Importing %d groups...", $groupCount), $groupCount),
                'complete' => sprintf(_m("Imported %d group.", "Imported %d groups.", $groupCount), $groupCount),
            ),
            'fetch-messages' => array(
                'label' => _m('Prepare public notices for import'),
                'progress' => sprintf(_m("Preparing %d notice...", "Preparing %d notices...", $fetchedCount), $fetchedCount),
                'complete' => sprintf(_m("Prepared %d notice.", "Prepared %d notices.", $fetchedCount), $fetchedCount),
            ),
            'save-messages' => array(
                'label' => _m('Import public notices'),
                'progress' => sprintf(_m("Importing %d notice...", "Importing %d notices...", $savedCount), $savedCount),
                'complete' => sprintf(_m("Imported %d notice.", "Imported %d notices.", $savedCount), $savedCount),
            ),
            'done' => array(
                'label' => _m('Done'),
                'progress' => sprintf(_m("Import is complete!")),
                'complete' => sprintf(_m("Import is complete!")),
            )
        );
        $steps = array_keys($labels);
        $currentStep = array_search($runner->state(), $steps);

        $this->out->elementStart('fieldset', array('class' => 'yammer-import'));
        $this->out->element('legend', array(), _m('Import status'));
        foreach ($steps as $step => $state) {
            if ($step < $currentStep) {
                // This step is done
                $this->progressBar($state,
                                   'complete',
                                   $labels[$state]['label'],
                                   $labels[$state]['complete']);
            } else if ($step == $currentStep) {
                // This step is in progress
                $this->progressBar($state,
                                   'progress',
                                   $labels[$state]['label'],
                                   $labels[$state]['progress']);
            } else {
                // This step has not yet been done.
                $this->progressBar($state,
                                   'waiting',
                                   $labels[$state]['label'],
                                   _m("Waiting..."));
            }
        }
        $this->out->elementEnd('fieldset');
    }

    private function progressBar($state, $class, $label, $status)
    {
        // @fixme prettify ;)
        $this->out->elementStart('div', array('class' => "import-step import-step-$state $class"));
        $this->out->element('div', array('class' => 'import-label'), $label);
        $this->out->element('div', array('class' => 'import-status'), $status);
        $this->out->elementEnd('div');
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
