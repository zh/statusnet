<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Action for displaying an OAuth verifier pin
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
 * @category  Action
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Class for displaying an OAuth verifier pin
 *
 * XXX: I'm pretty sure we don't need to check the logged in state here. -- Zach
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthPinAction extends InfoAction
{
    function __construct($title, $message, $verifier, $desktopMode = false)
    {
        $this->verifier    = $verifier;
        $this->title       = $title;
        $this->desktopMode = $desktopMode;
        parent::__construct($title, $message);
    }

    /**
     * Show body - override to add a special CSS class for the pin pages's
     * "desktop mode" (minimal display)
     *
     * Calls template methods
     *
     * @return nothing
     */
    function showBody()
    {
        $bodyClasses = array();

        if ($this->desktopMode) {
            $bodyClasses[] = 'oauth-desktop-mode';
        }

        if (common_current_user()) {
            $bodyClasses[] = 'user_in';
        }

        $attrs = array('id' => strtolower($this->trimmed('action')));

        if (!empty($bodyClasses)) {
            $attrs['class'] = implode(' ', $bodyClasses);
        }

        $this->elementStart('body', $attrs);

        $this->elementStart('div', array('id' => 'wrap'));
        if (Event::handle('StartShowHeader', array($this))) {
            $this->showHeader();
            Event::handle('EndShowHeader', array($this));
        }
        $this->showCore();
        if (Event::handle('StartShowFooter', array($this))) {
            $this->showFooter();
            Event::handle('EndShowFooter', array($this));
        }
        $this->elementEnd('div');
        $this->showScripts();
        $this->elementEnd('body');
    }

    /**
     * Display content.
     *
     * @return nothing
     */
    function showContent()
    {
        $this->element('div', array('class' => 'info'), $this->message);
        $this->element('div', array('id' => 'oauth_pin'), $this->verifier);
    }
}
