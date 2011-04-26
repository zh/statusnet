<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * One status_network per email domain
 * 
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
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
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Tools to map one status_network to one email domain in a multi-site
 * installation.
 *
 * @category  DomainStatusNetwork
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class DomainStatusNetworkPlugin extends Plugin
{
    function initialize()
    {
        $nickname = StatusNet::currentSite();

        if (empty($nickname)) {
            $this->log(LOG_WARNING, "No current site");
            return;
        }

        $sn = Status_network::staticGet('nickname', $nickname);

        if (empty($sn)) {
            $this->log(LOG_ERR, "No site for nickname $nickname");
            return;
        }

        $tags = $sn->getTags();

        foreach ($tags as $tag) {
            if (strncmp($tag, 'domain=', 7) == 0) {
                common_config_append('email', 'whitelist', substr($tag, 7));
            }
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'DomainStatusNetwork',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:DomainStatusNetwork',
                            'rawdescription' =>
                            _m('A plugin that maps a single status_network to an email domain.'));
        return true;
    }
}
