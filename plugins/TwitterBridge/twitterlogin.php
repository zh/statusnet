<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Settings for Twitter integration
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

require_once INSTALLDIR . '/plugins/TwitterBridge/twitter.php';

/**
 * Settings for Twitter integration
 *
 * @category Settings
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Julien Chaumond <chaumond@gmail.com>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */


class TwitterloginAction extends Action
{
    function handle($args)
    {
        parent::handle($args);

        if (common_is_real_login()) {
            $this->clientError(_('Already logged in.'));
        }

        $this->showPage();
    }

    function title()
    {
        return _('Twitter Login');
    }

    function getInstructions()
    {
        return _('Login with your Twitter Account');
    }

    function showPageNotice()
    {
        $instr = $this->getInstructions();
        $output = common_markup_to_html($instr);
        $this->elementStart('div', 'instructions');
        $this->raw($output);
        $this->elementEnd('div');
    }

    function showContent()
    {
        $this->elementStart('a', array('href' => common_local_url('twitterauthorization')));
        $this->element('img', array('src' => common_path('plugins/TwitterBridge/twitter_connect.gif'),
                                    'alt' => 'Connect my Twitter account'));
        $this->elementEnd('a');
    }

    function showLocalNav()
    {
        $nav = new LoginGroupNav($this);
        $nav->show();
    }
}
