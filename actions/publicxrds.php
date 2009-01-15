<?php
/*
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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/openid.php');

# XXX: factor out similarities with XrdsAction

class PublicxrdsAction extends Action
{

    function is_readonly()
    {
        return true;
    }

    function handle($args)
    {

        parent::handle($args);

        header('Content-Type: application/xrds+xml');

        common_start_xml();
        $this->elementStart('XRDS', array('xmlns' => 'xri://$xrds'));

        $this->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));

        $this->element('Type', null, 'xri://$xrds*simple');

        foreach (array('finishopenidlogin', 'finishaddopenid', 'finishimmediate') as $finish) {
            $this->show_service(Auth_OpenID_RP_RETURN_TO_URL_TYPE,
                                common_local_url($finish));
        }

        $this->elementEnd('XRD');

        $this->elementEnd('XRDS');
        common_end_xml();
    }

    function show_service($type, $uri, $params=null, $sigs=null, $localId=null)
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