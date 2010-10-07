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

require_once INSTALLDIR . '/lib/info.php';

/**
 * Class for displaying an OAuth verifier pin
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthPinAction extends InfoAction
{

    function __construct($verifier)
    {
        $this->verifier = $verifier;
        $title = _('Authorization succeeded.');
        parent::__construct($title, $title);
    }

    // TODO: Check for logged in state!

    /**
     * Display content.
     *
     * @return nothing
     */
    function showContent()
    {
        // XXX: make this much nicer
        $this->element('div', array('class' => 'info'), $this->verifier);
    }

}
