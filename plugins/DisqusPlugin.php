<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to add Disqus commenting to notice pages
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
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 *
 * This plugin adds Disqus commenting to your notices. Enabling this
 * plugin will make each notice page display the Disqus widget, and
 * notice lists will display the number of commments each notice has.
 *
 * To use this plugin, you need to first register your site with Disqus
 * and get a Discus 'shortname' for it.
 *
 *    http://disqus.com
 *
 * To enable the plugin, put the following in you config.php:
 *
 * addPlugin(
 *   'Disqus', array(
 *       'shortname' => 'YOURSHORTNAME',
 *      'div_style' => 'width:675px; padding-top:10px; position:relative; float:left;'
 *   )
 * );
 *
 * NOTE: the 'div_style' in an optional parameter that passes in some
 * inline CSS when creating the Disqus widget. It's a shortcut to make
 * the widget look OK with the default StatusNet theme. If you leave
 * it out you'll have to edit your theme CSS files to make the widget
 * look good.  You can also control the way the widget looks by
 * adding style rules to your theme.
 *
 * See: http://help.disqus.com/entries/100878-css-customization
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      Event
 */

class DisqusPlugin extends Plugin
{
    function onEndShowContentBlock($action)
    {
        if (get_class($action) == 'ShownoticeAction') {

            $attrs = array();
            $attrs['id'] = 'disqus_thread';

            if ($this->div_style) {
                $attrs['style'] = $this->div_style;
            }

            $action->element('div', $attrs, null);

            $script = <<<ENDOFSCRIPT
var disqus_identifier = %d;
  (function() {
   var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
   dsq.src = 'http://%s.disqus.com/embed.js';
   (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
  })();
ENDOFSCRIPT;

            $action->inlineScript(sprintf($script, $action->notice->id, $this->shortname));

            $attrs = array();

            $attrs['id'] = 'disqus_thread_footer';

            if ($this->div_style) {
                $attrs['style'] = $this->div_style;
            }

            $action->elementStart('div', $attrs);
            $action->elementStart('noscript');

            $action->raw('Please enable JavaScript to view the ');
            $noscriptUrl = 'http://disqus.com/?ref_noscript=' . $this->shortname;
            $action->element('a', array('href' => $noscriptUrl), 'comments powered by Disqus.');
            $action->elementEnd('noscript');

            $action->elementStart('a', array('href' => 'http://disqus.com', 'class' => 'dsq-brlink'));
            $action->raw('blog comments powered by ');
            $action->element('span', array('class' => 'logo-disqus'), 'Disqus');
            $action->elementEnd('a');
            $action->elementEnd('div');
        }
    }

    function onEndShowScripts($action)
    {
        // fugly
        $script = <<<ENDOFSCRIPT
var disqus_shortname = '%s';
(function () {
  var s = document.createElement('script'); s.async = true;
  s.src = 'http://disqus.com/forums/%s/count.js';
  (document.getElementsByTagName('HEAD')[0] || document.getElementsByTagName('BODY')[0]).appendChild(s);
}());
ENDOFSCRIPT;
        $action->inlineScript(sprintf($script, $this->shortname, $this->shortname));

        return true;
    }

    function onStartShowNoticeItem($noticeListItem)
    {
        if (empty($noticeListItem->notice->is_local)) {
            return true;
        }

        $noticeListItem->showNotice();
        $noticeListItem->showNoticeInfo();

        $noticeUrl = $noticeListItem->notice->bestUrl();
        $noticeUrl .= '#disqus_thread';

        $noticeListItem->out->element(
            'a', array('href' => $noticeUrl, 'class' => 'disqus_count'), 'Comments'
        );

        $noticeListItem->showNoticeOptions();
        Event::handle('EndShowNoticeItem', array($noticeListItem));

        return false;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Disqus',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Zach Copley',
                            'homepage' => 'http://status.net/wiki/Plugin:Disqus',
                            'rawdescription' =>
                            _m('Use <a href="http://disqus.com/">Disqus</a>'.
                               ' to add commenting to notice pages.'));
        return true;
    }
}
