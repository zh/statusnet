<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Plugin to enable Infinite Scrolling
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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @copyright 2009 Craig Andrews http://candrews.integralblue.com
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

class InfiniteScrollPlugin extends Plugin
{
    function __construct()
    {
        parent::__construct();
    }

    function onEndShowScripts($action)
    {
        $action->element('script',
            array('type' => 'text/javascript',
            'src'  => common_path('plugins/InfiniteScroll/jquery.infinitescroll.min.js')),
            '');

        $loading_image = common_path('plugins/InfiniteScroll/ajax-loader.gif');
        $js_string = <<<EOT
<script type="text/javascript">
jQuery(document).ready(function($){
  $('notices_primary').infinitescroll({
    nextSelector    : "li.nav_next a",
    loadingImg      : "$loading_image",
    text            : "<em>Loading the next set of posts...</em>",
    donetext        : "<em>Congratulations, you\'ve reached the end of the Internet.</em>",
    navSelector     : "div.pagination",
    contentSelector : "#notices_primary",
    itemSelector    : "ol.notices"
    });
});
</script>
EOT;
        $action->raw($js_string);
    }
}
