<?php
/*
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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapihelpAction extends TwitterapiAction
{

    /* Returns the string "ok" in the requested format with a 200 OK HTTP status code.
     * URL:http://identi.ca/api/help/test.format
     * Formats: xml, json
     */
    function test($args, $apidata)
    {
        parent::handle($args);

        if ($apidata['content-type'] == 'xml') {
            $this->init_document('xml');
            $this->element('ok', null, 'true');
            $this->end_document('xml');
        } elseif ($apidata['content-type'] == 'json') {
            $this->init_document('json');
            print '"ok"';
            $this->end_document('json');
        } else {
            $this->clientError(_('API method not found!'), $code=404);
        }

    }

    function downtime_schedule($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), $code=501);
    }

}