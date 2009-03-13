<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to use Piwik Analytics
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
 * Plugin to use Piwik Analytics (based on the Google Analytics plugin by Evan)
 *
 * This plugin will spoot out the correct JavaScript spell to invoke Piwik Analytics on a page.
 *
 * To use this plugin please add the following three lines to your config.php
#Add Piwik Analytics
require_once('plugins/PiwikAnalyticsPlugin.php');
$pa = new PiwikAnalyticsPlugin("example.com/piwik/","id");
 *
 * exchange example.com/piwik/ with the url (without http:// or https:// !) to your
 *          piwik installation and make sure you don't forget the final /
 * exchange id with the ID your laconica installation has in your Piwik analytics
 *
 * @category Plugin
 * @package  Laconica
 * @author   Tobias Diekershoff <tobias.diekershoff@gmx.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Event
 */

class PiwikAnalyticsPlugin extends Plugin
{
    // the base of your Piwik installation
    var $piwikroot = null;
    // the Piwik Id of your laconica installation
    var $piwikId   = null;

    function __construct($root=null, $id=null)
    {
        $this->piwikroot = $root;
        $this->piwikid = $id;
        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $js1 = 'var pkBaseURL = (("https:" == document.location.protocol) ? "https://'.
                $this->piwikroot.'" : "http://'.$this->piwikroot.
                '"); document.write(unescape("%3Cscript src=\'" + pkBaseURL + "piwik.js\''.
                ' type=\'text/javascript\'%3E%3C/script%3E"));';
        $js2 = 'piwik_action_name = ""; piwik_idsite = '.$this->piwikid.
               '; piwik_url = pkBaseURL + "piwik.php"; piwik_log(piwik_action_name, piwik_idsite, piwik_url);';
        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw($js1);
        $action->elementEnd('script');
        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw($js2);
        $action->elementEnd('script');
    }
}