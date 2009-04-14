<?php

/**
 * XRDS for OpenID
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

require_once INSTALLDIR.'/lib/omb.php';

/**
 * XRDS for OpenID
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class XrdsAction extends Action
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
     * @param array $args query arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        $nickname = $this->trimmed('nickname');
        $user     = User::staticGet('nickname', $nickname);
        if (!$user) {
            $this->clientError(_('No such user.'));
            return;
        }
        $this->showXrds($user);
    }

    /**
     * Show XRDS for a user.
     *
     * @param class $user XRDS for this user.
     *
     * @return void
     */
    function showXrds($user)
    {
        header('Content-Type: application/xrds+xml');
        $this->startXML();
        $this->elementStart('XRDS', array('xmlns' => 'xri://$xrds'));

        $this->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xml:id' => 'oauth',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $this->element('Type', null, 'xri://$xrds*simple');
        $this->showService(OAUTH_ENDPOINT_REQUEST,
                            common_local_url('requesttoken'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
                            array(OAUTH_HMAC_SHA1),
                            $user->uri);
        $this->showService(OAUTH_ENDPOINT_AUTHORIZE,
                            common_local_url('userauthorization'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
                            array(OAUTH_HMAC_SHA1));
        $this->showService(OAUTH_ENDPOINT_ACCESS,
                            common_local_url('accesstoken'),
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
                            array(OAUTH_HMAC_SHA1));
        $this->showService(OAUTH_ENDPOINT_RESOURCE,
                            null,
                            array(OAUTH_AUTH_HEADER, OAUTH_POST_BODY),
                            array(OAUTH_HMAC_SHA1));
        $this->elementEnd('XRD');

        // XXX: decide whether to include user's ID/nickname in postNotice URL
        $this->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'xml:id' => 'omb',
                                          'xmlns:simple' => 'http://xrds-simple.net/core/1.0',
                                          'version' => '2.0'));
        $this->element('Type', null, 'xri://$xrds*simple');
        $this->showService(OMB_ENDPOINT_POSTNOTICE,
                            common_local_url('postnotice'));
        $this->showService(OMB_ENDPOINT_UPDATEPROFILE,
                            common_local_url('updateprofile'));
        $this->elementEnd('XRD');
        $this->elementStart('XRD', array('xmlns' => 'xri://$xrd*($v*2.0)',
                                          'version' => '2.0'));
        $this->element('Type', null, 'xri://$xrds*simple');
        $this->showService(OAUTH_DISCOVERY,
                            '#oauth');
        $this->showService(OMB_NAMESPACE,
                            '#omb');
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

