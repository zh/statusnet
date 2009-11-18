<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to provide map visualization of location data
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
 * @category  Action
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Plugin to provide map visualization of location data
 *
 * This plugin uses the Mapstraction JavaScript library to
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @seeAlso  Location
 */

class MapstractionPlugin extends Plugin
{
    /** provider name, one of:
     'cloudmade', 'google', 'microsoft', 'openlayers', 'yahoo' */
    public $provider = 'openlayers';
    /** provider API key (or 'appid'), if required ('google' and 'yahoo' only) */
    public $apikey = null;

    /**
     * Hook for new URLs
     *
     * The way to register new actions from a plugin.
     *
     * @param Router &$m reference to router
     *
     * @return boolean event handler return
     */

    function onRouterInitialized(&$m)
    {
        $m->connect(':nickname/all/map',
                    array('action' => 'allmap'),
                    array('nickname' => '['.NICKNAME_FMT.']{1,64}'));
        $m->connect(':nickname/map',
                    array('action' => 'usermap'),
                    array('nickname' => '['.NICKNAME_FMT.']{1,64}'));
        return true;
    }

    /**
     * Hook for autoloading classes
     *
     * This makes sure our classes get autoloaded from our directory
     *
     * @param string $cls name of class being used
     *
     * @return boolean event handler return
     */

    function onAutoload($cls)
    {
        switch ($cls)
        {
        case 'AllmapAction':
        case 'UsermapAction':
            include_once INSTALLDIR.'/plugins/Mapstraction/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        default:
            return true;
        }
    }

    /**
     * Hook for adding extra JavaScript
     *
     * This makes sure our scripts get loaded for map-related pages
     *
     * @param Action $action Action object for the page
     *
     * @return boolean event handler return
     */

    function onEndShowScripts($action)
    {
        // These are the ones that have maps on 'em
        if (!in_array($action->trimmed('action'),
                      array('showstream', 'all', 'allmap', 'usermap'))) {
            return true;
        }

        switch ($this->provider)
        {
        case 'cloudmade':
            $action->script('http://tile.cloudmade.com/wml/0.2/web-maps-lite.js');
            break;
        case 'google':
            $action->script(sprintf('http://maps.google.com/maps?file=api&amp;v=2&amp;sensor=false&amp;key=%s',
                                    $this->apikey));
            break;
        case 'microsoft':
            $action->script('http://dev.virtualearth.net/mapcontrol/mapcontrol.ashx?v=6');
            break;
        case 'openlayers':
            // XXX: is this not nice...?
            $action->script('http://openlayers.org/api/OpenLayers.js');
            break;
        case 'yahoo':
            $action->script(sprintf('http://api.maps.yahoo.com/ajaxymap?v=3.8&appid=%s',
                                    $this->apikey));
            break;
        case 'geocommons': // don't support this yet
        default:
            return true;
        }

        $action->script(sprintf('%s?(%s)',
                                common_path('plugins/Mapstraction/js/mxn.js'),
                                $this->provider));

        $action->element('script', array('type' => 'text/javascript'),
                         sprintf('var _provider = "%s";', $this->provider));

        return true;
    }
}
