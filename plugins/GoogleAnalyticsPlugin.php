<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to use Google Analytics
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
 * Plugin to use Google Analytics
 *
 * This plugin will spoot out the correct JavaScript spell to invoke Google Analytics on a page.
 *
 * Note that Google Analytics is not compatible with the Franklin Street Statement; consider using
 * Piwik (http://www.piwik.org/) instead!
 *
 * @category Plugin
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 *
 * @see      Event
 */

class GoogleAnalyticsPlugin extends Plugin
{
    var $code = null;

    function __construct($code=null)
    {
        $this->code = $code;
        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $js1 = 'var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");'.
          'document.write(unescape("%3Cscript src=\'" + gaJsHost + "google-analytics.com/ga.js\' type=\'text/javascript\'%3E%3C/script%3E"));';
        $js2 = sprintf('try{'.
                       'var pageTracker = _gat._getTracker("%s");'.
                       'pageTracker._trackPageview();'.
                       '} catch(err) {}',
                       $this->code);
        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw($js1);
        $action->elementEnd('script');
        $action->elementStart('script', array('type' => 'text/javascript'));
        $action->raw($js2);
        $action->elementEnd('script');
    }
}
