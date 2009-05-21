<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Menu for login group of actions
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
 * @category  Menu
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @author    Zach Copley <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/widget.php';

/**
 * A widget for showing the connect group local nav menu
 *
 * @category Output
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Widget
 */

class FBCSettingsNav extends Widget
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

        $this->action->elementStart('dl', array('id' => 'site_nav_local_views'));
        $this->action->element('dt', null, _('Local views'));
        $this->action->elementStart('dd');

        # action => array('prompt', 'title')
        $menu =
          array('imsettings' =>
                array(_('IM'),
                      _('Updates by instant messenger (IM)')),
                'smssettings' =>
                array(_('SMS'),
                      _('Updates by SMS')),
                'twittersettings' =>
                array(_('Twitter'),
                      _('Twitter integration options')),
                'FBConnectSettings' =>
                array(_('Facebook'),
                      _('Facebook Connect settings')));

        $action_name = $this->action->trimmed('action');
        $this->action->elementStart('ul', array('class' => 'nav'));

        foreach ($menu as $menuaction => $menudesc) {
            if ($menuaction == 'imsettings' &&
                !common_config('xmpp', 'enabled')) {
                continue;
            }
            $this->action->menuItem(common_local_url($menuaction),
				    $menudesc[0],
				    $menudesc[1],
				    $action_name === $menuaction);
        }

        $this->action->elementEnd('ul');

        $this->action->elementEnd('dd');
        $this->action->elementEnd('dl');
    }
}
