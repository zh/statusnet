<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * A snapshot of site stats that can report itself to headquarters
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
 * @category  Stats
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * A snapshot of site stats that can report itself to headquarters
 *
 * This class will collect statistics on the site and report them to
 * a statistics server of the admin's choice. (Default is the big one
 * at laconi.ca.)
 *
 * It can either be called from a cron job, or run occasionally by the
 * Web site.
 *
 * @category Stats
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 */

class Snapshot {

    function __construct()
    {
    }

    function take()
    {
    }

    function report()
    {
    }

    static function check()
    {
        switch (common_config('snapshot', 'run')) {
         case 'web':
            // skip if we're not running on the Web.
            if (!isset($_SERVER) || !array_key_exists('REQUEST_METHOD', $_SERVER)) {
                break;
            }
            // Run once every frequency hits
            // XXX: do frequency by time (once a week, etc.) rather than
            // hits
            if (rand() % common_config('snapshot', 'frequency') == 0) {
                $snapshot = new Snapshot();
                if ($snapshot->take()) {
                    $snapshot->report();
                }
            }
            break;
         case 'cron':
            // skip if we're running on the Web
            if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
                break;
            }
            // We're running from the command line; assume
            $snapshot = new Snapshot();
            if ($snapshot->take()) {
                $snapshot->report();
            }
            break;
         case 'never':
            break;
         default:
            common_log(LOG_WARNING, "Unrecognized value for snapshot run config.");
        }
    }
}
