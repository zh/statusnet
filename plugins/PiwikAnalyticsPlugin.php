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
 * @author    Tobias Diekershoff <tobias.diekershoff@gmx.net>
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
 * This plugin will spoot out the correct JavaScript spell to invoke
 * Piwik Analytics on a page.
 *
 * To use this plugin please add the following three lines to your config.php
 *
 *     require_once('plugins/PiwikAnalyticsPlugin.php');
 *     $pa = new PiwikAnalyticsPlugin("example.com/piwik/","id");
 *
 * exchange example.com/piwik/ with the url to your piwik installation and
 * make sure you don't forget the final /
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
    /** the base of your Piwik installation */
    var $piwikroot = null;
    /** the Piwik Id of your laconica installation */
    var $piwikId   = null;

    /**
     * constructor
     *
     * @param string $root Piwik root URL
     * @param string $id   Piwik ID of this app
     */

    function __construct($root=null, $id=null)
    {
        $this->piwikroot = $root;
        $this->piwikid   = $id;
        parent::__construct();
    }

    /**
     * Called when all scripts have been shown
     *
     * @param Action $action Current action
     *
     * @return boolean ignored
     */

    function onEndShowScripts($action)
    {
        $piwikCode = <<<ENDOFPIWIK

<!-- Piwik -->
<script type="text/javascript">
var pkBaseURL = (("https:" == document.location.protocol) ? "https://{$this->piwikroot}" : "http://{$this->piwikroot}");
document.write(unescape("%3Cscript src='" + pkBaseURL + "piwik.js' type='text/javascript'%3E%3C/script%3E"));
</script>
<script type="text/javascript">
try {
    var piwikTracker = Piwik.getTracker(pkBaseURL + "piwik.php", 4);
    piwikTracker.trackPageView();
    piwikTracker.enableLinkTracking();
} catch( err ) {}
</script>
<!-- End Piwik Tag -->

ENDOFPIWIK;

        $action->raw($piwikCode);
        return true;
    }
}