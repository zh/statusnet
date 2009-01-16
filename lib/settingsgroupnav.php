<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Navigation widget for the settings group
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
 * @category  Widget
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/widget.php';

/**
 * A widget for showing the settings group local nav menu
 *
 * @category Widget
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      HTMLOutputter
 */

class SettingsGroupNav extends Widget
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
        # action => array('prompt', 'title')
        $menu =
          array('profilesettings' =>
                array(_('Profile'),
                      _('Change your profile settings')),
                'emailsettings' =>
                array(_('Email'),
                      _('Change email handling')),
                'openidsettings' =>
                array(_('OpenID'),
                      _('Add or remove OpenIDs')),
                'smssettings' =>
                array(_('SMS'),
                      _('Updates by SMS')),
                'imsettings' =>
                array(_('IM'),
                      _('Updates by instant messenger (IM)')),
                'twittersettings' =>
                array(_('Twitter'),
                      _('Twitter integration options')),
                'othersettings' =>
                array(_('Other'),
                      _('Other options')));
        
        $action_name = $this->action->trimmed('action');
        $this->action->elementStart('ul', array('id' => 'nav_views'));
	
        foreach ($menu as $menuaction => $menudesc) {
            if ($menuaction == 'imsettings' &&
                !common_config('xmpp', 'enabled')) {
                continue;
            }
            $this->action->menuItem(common_local_url($menuaction),
				    $menudesc[0],
				    $menudesc[1],
				    $action_name == $menuaction);
        }
	
        $this->action->elementEnd('ul');
    }
}
