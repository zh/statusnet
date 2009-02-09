<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Utility class for plugins
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
 * @category  Plugin
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Base class for plugins
 *
 * A base class for Laconica plugins. Mostly a light wrapper around
 * the Event framework.
 *
 * Subclasses of Plugin will automatically handle an event if they define
 * a method called "onEventName". (Well, OK -- only if they call parent::__construct()
 * in their constructors.)
 *
 * They will also automatically handle the InitializePlugin and CleanupPlugin with the
 * initialize() and cleanup() methods, respectively.
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Event
 */

class Plugin
{
    function __construct()
    {
        Event::addHandler('InitializePlugin', array($this, 'initialize'));
        Event::addHandler('CleanupPlugin', array($this, 'cleanup'));

        foreach (get_class_methods($this) as $method) {
            if (mb_substr($method, 0, 2) == 'on') {
                Event::addHandler(mb_substr($method, 2), array($this, $method));
            }
        }
    }

    function initialize()
    {
        return true;
    }

    function cleanup()
    {
        return true;
    }
}
