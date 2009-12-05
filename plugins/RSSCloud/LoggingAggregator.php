<?php

/**
 * This test class pretends to be an RSS aggregator. It logs notifications
 * from the cloud.
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

if (!defined('STATUSNET')) {
    exit(1);
}

class LoggingAggregatorAction extends Action
{

    var $challenge = null;
    var $url       = null;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */
    function prepare($args)
    {
        parent::prepare($args);

        $this->url       = $this->arg('url');
        $this->challenge = $this->arg('challenge');

        common_debug("args = " . var_export($this->args, true));
        common_debug('url = ' . $this->url . ' challenge = ' . $this->challenge);

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (empty($this->url)) {
            $this->showError('Hey, you have to provide a url parameter.');
            return;
        }

        if (!empty($this->challenge)) {

            // must be a GET

            if ($_SERVER['REQUEST_METHOD'] != 'GET') {
                $this->showError('This resource requires an HTTP GET.');
                return;
            }

            header('Content-Type: text/xml');
            echo "<notifyResult success='true' msg='Thanks for the update.' challenge='" .
              $this->challenge . "' />\n";

        } else {

            // must be a POST

            if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                $this->showError('This resource requires an HTTP POST.');
                return;
            }

            header('Content-Type: text/xml');
            echo '<notifyResult success=\'true\' msg=\'Thanks for the update.\' />' . "\n";

        }

        $this->ip = $_SERVER['REMOTE_ADDR'];

        common_log(LOG_INFO, 'RSSCloud Logging Aggregator - ' . $this->ip . ' claims the feed at ' .
                   $this->url . ' has been updated.');
    }

    function showError($msg)
    {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/xml');
        echo "<?xml version='1.0'?>\n";
        echo "<notifyResult success='false' msg='$msg' />\n";
    }

}