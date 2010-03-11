<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Use TinyMCE library to allow rich text editing in the browser
 *
 * PHP version 5
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
 *
 * @category  WYSIWYG
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Use TinyMCE library to allow rich text editing in the browser
 *
 * Converts the notice form in browser to a rich-text editor.
 *
 * @category  WYSIWYG
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class TinyMCEPlugin extends Plugin
{
    function onEndShowScripts($action)
    {
        if (common_logged_in()) {
            $action->script(common_path('plugins/TinyMCE/js/jquery.tinymce.js'));
            $action->inlineScript($this->_inlineScript());
        }

        return true;
    }

    function onEndShowStyles($action)
    {
        $action->style('span#notice_data-text_container { float: left }');
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'TinyMCE',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:TinyMCE',
                            'rawdescription' =>
                            _m('Use TinyMCE library to allow rich text editing in the browser'));
        return true;
    }

    function _inlineScript()
    {
        $path = common_path('plugins/TinyMCE/js/tiny_mce.js');

        $scr = <<<END_OF_SCRIPT
        $().ready(function() {
            $('textarea#notice_data-text').tinymce({
                script_url : '{$path}',
                // General options
                theme : "simple",
            });
        });
END_OF_SCRIPT;

        return $scr;
    }
}

