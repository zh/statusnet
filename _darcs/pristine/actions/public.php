<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/stream.php');

class PublicAction extends StreamAction {

    function handle($args)
    {
        parent::handle($args);

        $page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        header('X-XRDS-Location: '. common_local_url('publicxrds'));

        common_show_header(_('Public timeline'),
                           array($this, 'show_header'), null,
                           array($this, 'show_top'));

        # XXX: Public sidebar here?

        $this->show_notices($page);

        common_show_footer();
    }

    function show_top()
    {
        if (common_logged_in()) {
            common_notice_form('public');
        } else {
            $instr = $this->get_instructions();
            $output = common_markup_to_html($instr);
            common_element_start('div', 'instructions');
            common_raw($output);
            common_element_end('div');
        }

        $this->public_views_menu();

        $this->show_feeds_list(array(0=>array('href'=>common_local_url('publicrss'),
                                              'type' => 'rss',
                                              'version' => 'RSS 1.0',
                                              'item' => 'publicrss'),
                                     1=>array('href'=>common_local_url('publicatom'),
                                              'type' => 'atom',
                                              'version' => 'Atom 1.0',
                                              'item' => 'publicatom')));
    }

    function get_instructions()
    {
        return _('This is %%site.name%%, a [micro-blogging](http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                 'based on the Free Software [Laconica](http://laconi.ca/) tool. ' .
                 '[Join now](%%action.register%%) to share notices about yourself with friends, family, and colleagues! ([Read more](%%doc.help%%))');
    }

    function show_header()
    {
        common_element('link', array('rel' => 'alternate',
                                     'href' => common_local_url('publicrss'),
                                     'type' => 'application/rss+xml',
                                     'title' => _('Public Stream Feed')));
        # for client side of OpenID authentication
        common_element('meta', array('http-equiv' => 'X-XRDS-Location',
                                     'content' => common_local_url('publicxrds')));
    }

    function show_notices($page)
    {

        $cnt = 0;
        $notice = Notice::publicStream(($page-1)*NOTICES_PER_PAGE,
                                       NOTICES_PER_PAGE + 1);

        if (!$notice) {
            $this->server_error(_('Could not retrieve public stream.'));
            return;
        }

        $cnt = $this->show_notice_list($notice);

        common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'public');
    }
}
