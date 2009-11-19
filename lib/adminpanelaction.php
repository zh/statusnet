<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Superclass for admin panel actions
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
 * @category  UI
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
 * superclass for admin panel actions
 *
 * Common code for all admin panel actions.
 *
 * @category UI
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @todo Find some commonalities with SettingsAction and combine
 */

class AdminPanelAction extends Action
{
    var $success = true;
    var $msg     = null;

    /**
     * Prepare for the action
     *
     * We check to see that the user is logged in, has
     * authenticated in this session, and has the right
     * to configure the site.
     *
     * @param array $args Array of arguments from Web driver
     *
     * @return boolean success flag
     */

    function prepare($args)
    {
        parent::prepare($args);

        // User must be logged in.

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return;
        }

        $user = common_current_user();

        // ...because they're logged in

        assert(!empty($user));

        // It must be a "real" login, not saved cookie login

        if (!common_is_real_login()) {
            // Cookie theft is too easy; we require automatic
            // logins to re-authenticate before admining the site
            common_set_returnto($this->selfUrl());
            if (Event::handle('RedirectToLogin', array($this, $user))) {
                common_redirect(common_local_url('login'), 303);
            }
        }

        // User must have the right to change admin settings

        if (!$user->hasRight(Right::CONFIGURESITE)) {
            $this->clientError(_('You cannot make changes to this site.'));
            return;
        }

        return true;
    }

    /**
     * handle the action
     *
     * Check session token and try to save the settings if this is a
     * POST. Otherwise, show the form.
     *
     * @param array $args unused.
     *
     * @return void
     */

    function handle($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->checkSessionToken();
            try {
                $this->saveSettings();

                // Reload settings

                Config::loadSettings();

                $this->success = true;
                $this->msg     = _('Settings saved.');
            } catch (Exception $e) {
                $this->success = false;
                $this->msg     = $e->getMessage();
            }
        }
        $this->showPage();
    }

    /**
     * Show tabset for this page
     *
     * Uses the AdminPanelNav widget
     *
     * @return void
     * @see AdminPanelNav
     */

    function showLocalNav()
    {
        $nav = new AdminPanelNav($this);
        $nav->show();
    }

    /**
     * Show the content section of the page
     *
     * Here, we show the admin panel's form.
     *
     * @return void.
     */

    function showContent()
    {
        $this->showForm();
    }

    /**
     * show human-readable instructions for the page, or
     * a success/failure on save.
     *
     * @return void
     */

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('div', ($this->success) ? 'success' : 'error',
                           $this->msg);
        } else {
            $inst   = $this->getInstructions();
            $output = common_markup_to_html($inst);

            $this->elementStart('div', 'instructions');
            $this->raw($output);
            $this->elementEnd('div');
        }
    }

    /**
     * Show the admin panel form
     *
     * Sub-classes should overload this.
     *
     * @return void
     */

    function showForm()
    {
        $this->clientError(_('showForm() not implemented.'));
        return;
    }

    /**
     * Instructions for using this form.
     *
     * String with instructions for using the form.
     *
     * Subclasses should overload this.
     *
     * @return void
     */

    function getInstructions()
    {
        return '';
    }

    /**
     * Save settings from the form
     *
     * Validate and save the settings from the user.
     *
     * @return void
     */

    function saveSettings()
    {
        $this->clientError(_('saveSettings() not implemented.'));
        return;
    }
    
    /**
     * Delete a design setting
     *
     * // XXX: Maybe this should go in Design? --Z
     *
     * @return mixed $result false if something didn't work
     */

    function deleteSetting($section, $setting)
    {
        $config = new Config();

        $config->section = $section;
        $config->setting = $setting;

        if ($config->find(true)) {
            $result = $config->delete();
            if (!$result) {
                common_log_db_error($config, 'DELETE', __FILE__);
                $this->clientError(_("Unable to delete design setting."));
                return null;
            }
        }

        return $result;
    }
}

/**
 * Menu for public group of actions
 *
 * @category Output
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */

class AdminPanelNav extends Widget
{
    var $action = null;

    /**
     * Construction
     *
     * @param Action $action current action, used for output
     */

    function __construct($action=null)
    {
        parent::__construct($action);
        $this->action = $action;
    }

    /**
     * Show the menu
     *
     * @return void
     */

    function show()
    {
        $action_name = $this->action->trimmed('action');

        $this->action->elementStart('ul', array('class' => 'nav'));

        if (Event::handle('StartAdminPanelNav', array($this))) {

            $this->out->menuItem(common_local_url('siteadminpanel'), _('Site'),
                _('Basic site configuration'), $action_name == 'siteadminpanel', 'nav_site_admin_panel');

            $this->out->menuItem(common_local_url('designadminpanel'), _('Design'),
                _('Design configuration'), $action_name == 'designadminpanel', 'nav_design_admin_panel');

            Event::handle('EndAdminPanelNav', array($this));
        }
        $this->action->elementEnd('ul');
    }
}
