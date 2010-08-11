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
    var $html;

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
        $action->style('span#notice_data-text_container, span#notice_data-text_parent { float: left }');
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

    /**
     * Sanitize HTML input and strip out potentially dangerous bits.
     *
     * @param string $raw HTML
     * @return string HTML
     */
    private function sanitizeHtml($raw)
    {
        require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

        $config = array('safe' => 1,
                        'deny_attribute' => 'id,style,on*');

        return htmLawed($raw, $config);
    }

    /**
     * Strip HTML to plaintext string
     *
     * @param string $html HTML
     * @return string plaintext, single line
     */
    private function stripHtml($html)
    {
        return str_replace("\n", " ", html_entity_decode(strip_tags($html)));
    }

    /**
     * Hook for new-notice form processing to take our HTML goodies;
     * won't affect API posting etc.
     * 
     * @param NewNoticeAction $action
     * @param User $user
     * @param string $content
     * @param array $options
     * @return boolean hook return
     */
    function onStartSaveNewNoticeWeb($action, $user, &$content, &$options)
    {
        $html = $this->sanitizeHtml($action->arg('status_textarea'));
        $options['rendered'] = $html;
        $content = $this->stripHtml($html);
        return true;
    }

    function _inlineScript()
    {
        $path = common_path('plugins/TinyMCE/js/tiny_mce.js');

        // Note: the normal on-submit triggering to save data from
        // the HTML editor into the textarea doesn't play well with
        // our AJAX form submission. Manually moving it to trigger
        // on our send button click.
        $scr = <<<END_OF_SCRIPT
        $().ready(function() {
            $('textarea#notice_data-text').tinymce({
                script_url : '{$path}',
                // General options
                theme : "advanced",
                plugins : "paste,fullscreen,autoresize,inlinepopups,tabfocus,linkautodetect",
                theme_advanced_buttons1 : "bold,italic,strikethrough,|,undo,redo,|,link,unlink,image,|,fullscreen",
                theme_advanced_buttons2 : "",
                theme_advanced_buttons3 : "",
                add_form_submit_trigger : false,
                theme_advanced_resizing : true,
                tabfocus_elements: ":prev,:next"
            });
            $('#notice_action-submit').click(function() {
                tinymce.triggerSave();
            });
        });
END_OF_SCRIPT;

        return $scr;
    }
}

