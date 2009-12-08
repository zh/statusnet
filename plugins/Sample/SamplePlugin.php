<?php
/*
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

/**
 * @package SamplePlugin
 * @maintainer Your Name <you@example.com>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

class SamplePlugin extends Plugin
{
    function onInitializePlugin()
    {
        // Event handlers normally return true to indicate that all is well.
        //
        // Returning false will cancel further processing of any other
        // plugins or core code hooking the same event.
        return true;
    }

    /**
     * Hook for RouterInitialized event.
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     * @return boolean hook return
     */

    function onRouterInitialized($m)
    {
        $m->connect(':nickname/samples',
                    array('action' => 'showsamples'),
                    array('feed' => '[A-Za-z0-9_-]+'));
        $m->connect('settings/sample',
                    array('action' => 'samplesettings'));
        return true;
    }
}

