<?php

/**
 * Request token action class.
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

/**
 * Request token action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class RequesttokenAction extends Action
{
     /**
     * Is read only?
     *
     * @return boolean false
     */
    function isReadOnly()
    {
        return false;
    }

    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);
        try {
            $srv = new OMB_Service_Provider(null, omb_oauth_datastore(),
                                            omb_oauth_server());
            $srv->writeRequestToken();
        } catch (Exception $e) {
            $this->serverError($e->getMessage());
        }
    }
}
?>
