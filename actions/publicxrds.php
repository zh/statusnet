<?php

/**
 * Public XRDS for OpenID
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/openid.php';

/**
 * Public XRDS for OpenID
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
 * @todo factor out similarities with XrdsAction
 */
class PublicxrdsAction extends Action
{
    /**
     * Is read only?
     *
     * @return boolean true
     */
    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);
        header('Content-Type: application/xrds+xml');
        $this->startXML();
        $this->elementStart('XRDS', array('xmlns' => 'xri://$xrds'));
        $this->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $this->element('Type', null, 'xri://$xrds*simple');
        foreach (array('finishopenidlogin', 'finishaddopenid') as $finish) {
            $this->showService(Auth_OpenID_RP_RETURN_TO_URL_TYPE,
                                common_local_url($finish));
        }
        $this->elementEnd('XRD');
        $this->elementEnd('XRDS');
        $this->endXML();
    }

    /**
     * Show service.
     *
     * @param string $type    XRDS type
     * @param string $uri     URI
     * @param array  $params  type parameters, null by default
     * @param array  $sigs    type signatures, null by default
     * @param string $localId local ID, null by default
     *
     * @return void
     */
    function showService($type, $uri, $params=null, $sigs=null, $localId=null)
    {
        $this->elementStart('Service');
        if ($uri) {
            $this->element('URI', null, $uri);
        }
        $this->element('Type', null, $type);
        if ($params) {
            foreach ($params as $param) {
                $this->element('Type', null, $param);
            }
        }
        if ($sigs) {
            foreach ($sigs as $sig) {
                $this->element('Type', null, $sig);
            }
        }
        if ($localId) {
            $this->element('LocalID', null, $localId);
        }
        $this->elementEnd('Service');
    }
}

