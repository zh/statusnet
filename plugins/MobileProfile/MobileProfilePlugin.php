<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * XHTML Mobile Profile plugin that uses WAP 2.0 Plugin
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
 * @package   StatusNet
 * @author    Sarven Capadisli <csarven@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

define('PAGE_TYPE_PREFS',
       'application/vnd.wap.xhtml+xml, application/xhtml+xml, text/html;q=0.9');

require_once INSTALLDIR.'/plugins/Mobile/WAP20Plugin.php';


/**
 * Superclass for plugin to output XHTML Mobile Profile
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Sarven Capadisli <csarven@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class MobileProfilePlugin extends WAP20Plugin
{
    public $DTDversion      = null;
    public $serveMobile     = false;

    function __construct($DTD='http://www.wapforum.org/DTD/xhtml-mobile10.dtd')
    {
        $this->DTD       = $DTD;

        parent::__construct();
    }


    function onStartShowHTML($action)
    {
        if (!$type) {
            $httpaccept = isset($_SERVER['HTTP_ACCEPT']) ?
              $_SERVER['HTTP_ACCEPT'] : null;

            $cp = common_accept_to_prefs($httpaccept);
            $sp = common_accept_to_prefs(PAGE_TYPE_PREFS);

            $type = common_negotiate_type($cp, $sp);

            if (!$type) {
                throw new ClientException(_('This page is not available in a '.
                                            'media type you accept'), 406);
            }
        }

        // XXX: If user is on the mobile site e.g., m.siteserver.com 
        // or they really want it, serve the mobile version

        // FIXME: This is dirty and probably not accurate of doing it
        if ((common_config('site', 'mobileserver').'/'.common_config('site', 'path').'/' == 
            $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ||
            preg_match("/.*\/.*wap.*xml/", $type)) {

            $this->serveMobile = true;
        }
        else {
            $this->serveMobile = false;
            return true;
        }

        header('Content-Type: '.$type);

        $action->extraHeaders();

        $action->startXML('html',
                        '-//WAPFORUM//DTD XHTML Mobile 1.0//EN',
                        $this->DTD);

        $language = $action->getLanguage();

        $action->elementStart('html', array('xmlns' => 'http://www.w3.org/1999/xhtml',
                                            'xml:lang' => $language));

        return false;
    }



    function onStartShowAside($action)
    {

    }


    function onStartShowScripts($action)
    {

    }

}


?>
