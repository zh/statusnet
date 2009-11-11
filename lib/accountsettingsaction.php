<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Base class for account settings actions
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
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/settingsaction.php';

/**
 * Base class for account settings actions
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Widget
 */

class AccountSettingsAction extends SettingsAction
{
    /**
     * Show the local navigation menu
     *
     * This is the same for all settings, so we show it here.
     *
     * @return void
     */

    function showLocalNav()
    {
        $menu = new AccountSettingsNav($this);
        $menu->show();
    }
}

/**
 * A widget for showing the settings group local nav menu
 *
 * @category Widget
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      HTMLOutputter
 */

class AccountSettingsNav extends Widget
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

        if (Event::handle('StartAccountSettingsNav', array(&$this->action))) {
            $user = common_current_user();

            if(Event::handle('StartAccountSettingsProfileMenuItem', array($this, &$menu))){
                $this->showMenuItem('profilesettings',_('Profile'),_('Change your profile settings'));
                Event::handle('EndAccountSettingsProfileMenuItem', array($this, &$menu));
            }
            if(Event::handle('StartAccountSettingsAvatarMenuItem', array($this, &$menu))){
                $this->showMenuItem('avatarsettings',_('Avatar'),_('Upload an avatar'));
                Event::handle('EndAccountSettingsAvatarMenuItem', array($this, &$menu));
            }
            if(Event::handle('StartAccountSettingsPasswordMenuItem', array($this, &$menu))){
                $this->showMenuItem('passwordsettings',_('Password'),_('Change your password'));
                Event::handle('EndAccountSettingsPasswordMenuItem', array($this, &$menu));
            }
            if(Event::handle('StartAccountSettingsEmailMenuItem', array($this, &$menu))){
                $this->showMenuItem('emailsettings',_('Email'),_('Change email handling'));
                Event::handle('EndAccountSettingsEmailMenuItem', array($this, &$menu));
            }
            if(Event::handle('StartAccountSettingsDesignMenuItem', array($this, &$menu))){
                $this->showMenuItem('userdesignsettings',_('Design'),_('Design your profile'));
                Event::handle('EndAccountSettingsDesignMenuItem', array($this, &$menu));
            }
            if(Event::handle('StartAccountSettingsOtherMenuItem', array($this, &$menu))){
                $this->showMenuItem('othersettings',_('Other'),_('Other options'));
                Event::handle('EndAccountSettingsOtherMenuItem', array($this, &$menu));
            }

            Event::handle('EndAccountSettingsNav', array(&$this->action));
        }

        $this->action->elementEnd('ul');
    }

    function showMenuItem($menuaction, $desc1, $desc2)
    {
        $action_name = $this->action->trimmed('action');
        $this->action->menuItem(common_local_url($menuaction),
            $desc1,
            $desc2,
            $action_name === $menuaction);
    }
}
