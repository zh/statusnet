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
        if (common_logged_in ()) {
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
        require_once INSTALLDIR . '/extlib/htmLawed/htmLawed.php';

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
        if ($action->arg('richedit')) {
            $html = $this->sanitizeHtml($content);
            $options['rendered'] = $html;
            $content = $this->stripHtml($html);
        }
        return true;
    }

    /**
     * Hook for new-notice form processing to process file upload appending...
     *
     * @param NewNoticeAction $action
     * @param MediaFile $media
     * @param string $content
     * @param array $options
     * @return boolean hook return
     */
    function onStartSaveNewNoticeAppendAttachment($action, $media, &$content, &$options)
    {
        if ($action->arg('richedit')) {
            // See if we've got a placeholder inline image; if so, fill it!
            $dom = new DOMDocument();
            common_log(LOG_INFO, 'QQQQQQQQQQQQQQQQQQQQQQQQ');
            if ($dom->loadHTML($options['rendered'])) {
                $imgs = $dom->getElementsByTagName('img');
                foreach ($imgs as $img) {
                    common_log(LOG_INFO, 'img: ' . var_export($img, true));
                    if (preg_match('/(^| )placeholder( |$)/', $img->getAttribute('class'))) {
                        common_log(LOG_INFO, 'QQQQQQ: img src: ' . $media->fileRecord->url);
                        $img->setAttribute('src', $media->fileRecord->url);
                        $holderWidth = intval($img->getAttribute('width'));
                        $holderHeight = intval($img->getAttribute('height'));
                        $holderAspect = $holderWidth / $holderHeight;

                        $path = File::path($media->filename);
                        $imgInfo = getimagesize($path);
                        common_log(LOG_INFO, 'QQQQQQ: ' . $path . ' : ' . var_export($imgInfo, true));

                        $origWidth = $imgInfo[0];
                        $origHeight = $imgInfo[1];
                        $origAspect = $origWidth / $origHeight;
                        if ($origAspect >= 1.0) {
                            // wide image
                            if ($origWidth > $holderWidth) {
                                $width = $holderWidth;
                                $height = intval($holderWidth / $origAspect);
                            } else {
                                $width = $origWidth;
                                $height = $origHeight;
                            }
                        } else {
                            if ($origHeight > $holderHeight) {
                                $height = $holderHeight;
                                $width = ($holderWidth * $origAspect);
                            } else {
                                $width = $origWidth;
                                $height = $origHeight;
                            }
                        }

                        $img->setAttribute('width', $width);
                        $img->setAttribute('height', $height);

                        common_log(LOG_INFO, 'QQQQQ: ' . $width . ' ' . $height);
                    }
                }
                $html = $dom->saveHTML();
                common_log(LOG_INFO, 'QQQQQQ: out: ' . $html);
                $options['rendered'] = $html;
            }

            // The regular code will append the short URL to the plaintext content.
            // Carry on and let it through...
        }
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
            $('#form_notice').append('<input type="hidden" name="richedit" value="1">');
            $('#notice_action-submit').click(function() {
                if (typeof tinymce != "undefined") {
                    tinymce.triggerSave();
                }
            });
            $('#'+SN.C.S.NoticeDataAttach).change(function() {
                /*
                S = '<div id="'+SN.C.S.NoticeDataAttachSelected+'" class="'+SN.C.S.Success+'"><code>'+$(this).val()+'</code> <button class="close">&#215;</button></div>';
                NDAS = $('#'+SN.C.S.NoticeDataAttachSelected);
                if (NDAS.length > 0) {
                    NDAS.replaceWith(S);
                }
                */
                //alert('yay');
                var img = '<img src="about:blank?placeholder" class="placeholder" width="320" height="240">';
                var html = tinyMCE.activeEditor.getContent();
                tinyMCE.activeEditor.setContent(html + img);
            });
        });
END_OF_SCRIPT;

        return $scr;
    }

}

