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
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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
require_once INSTALLDIR.'/extlib/libomb/service_provider.php';
require_once INSTALLDIR.'/extlib/libomb/xrds_mapper.php';

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
    function isReadOnly()
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
        $srv = new OMB_Service_Provider(profile_to_omb_profile($user->uri,
                                        $user->getProfile()));
        /* Use libomb’s default XRDS Writer. */
        $xrds_writer = null;
        $srv->writeXRDS(new Laconica_XRDS_Mapper(), $xrds_writer);
    }
}

class Laconica_XRDS_Mapper implements OMB_XRDS_Mapper
{
    protected $urls;

    public function __construct()
    {
        $this->urls = array(
            OAUTH_ENDPOINT_REQUEST => 'requesttoken',
            OAUTH_ENDPOINT_AUTHORIZE => 'userauthorization',
            OAUTH_ENDPOINT_ACCESS => 'accesstoken',
            OMB_ENDPOINT_POSTNOTICE => 'postnotice',
            OMB_ENDPOINT_UPDATEPROFILE => 'updateprofile');
    }

    public function getURL($action)
    {
        return common_local_url($this->urls[$action]);
    }
}
?>
