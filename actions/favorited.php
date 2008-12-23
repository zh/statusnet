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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/lib/stream.php');

class FavoritedAction extends StreamAction {

    function handle($args) {
        parent::handle($args);

        $page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        common_show_header(_('Popular notices'),
                           array($this, 'show_header'), null,
                           array($this, 'show_top'));

        $this->show_notices($page);

        common_show_footer();
    }

    function show_top() {
        $instr = $this->get_instructions();
        $output = common_markup_to_html($instr);
        common_element_start('div', 'instructions');
        common_raw($output);
        common_element_end('div');
        $this->public_views_menu();
    }

    function show_header() {
        return;
    }

    function get_instructions() {
        return _('Showing recently popular notices');
    }

    function show_notices($page) {

        $qry = 'SELECT notice.*, sum(exp(-(now() - fave.modified) / %s)) as weight ' .
                'FROM notice JOIN fave ON notice.id = fave.notice_id ' .
                'GROUP BY fave.notice_id ' .
                'ORDER BY weight DESC';

        $offset = ($page - 1) * NOTICES_PER_PAGE;
        $limit = NOTICES_PER_PAGE + 1;

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        # Figure out how to cache this query

        $notice = new Notice;
        $notice->query(sprintf($qry, common_config('popular', 'dropoff')));

        common_element_start('ul', array('id' => 'notices'));

        $cnt = 0;

        while ($notice->fetch() && $cnt <= NOTICES_PER_PAGE) {
            $cnt++;

            if ($cnt > NOTICES_PER_PAGE) {
                break;
            }

            $item = new NoticeListItem($notice);
            $item->show();
        }

        common_element_end('ul');

        common_pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'favorited');
    }

}
