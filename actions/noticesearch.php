<?php
/**
 * Notice search action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/searchaction.php';

/**
 * Notice search action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 * @todo     common parent for people and content search?
 */
class NoticesearchAction extends SearchAction
{

    function prepare($args)
    {
        parent::prepare($args);

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Get instructions
     *
     * @return string instruction text
     */
    function getInstructions()
    {
        return _('Search for notices on %%site.name%% by their contents. Separate search terms by spaces; they must be 3 characters or more.');
    }

    /**
     * Get title
     *
     * @return string title
     */
    function title()
    {
        return _('Text search');
    }

    function getFeeds()
    {
        $q = $this->trimmed('q');

        if (!$q) {
            return null;
        }

        return array(new Feed(Feed::RSS1, common_local_url('noticesearchrss',
                                                           array('q' => $q)),
                              sprintf(_('Search results for "%s" on %s'),
                                      $q, common_config('site', 'name'))));
    }

    /**
     * Show results
     *
     * @param string  $q    search query
     * @param integer $page page number
     *
     * @return void
     */
    function showResults($q, $page)
    {
        $notice        = new Notice();

        $search_engine = $notice->getSearchEngine('identica_notices');
        $search_engine->set_sort_mode('chron');
        // Ask for an extra to see if there's more.
        $search_engine->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        } else {
            $cnt = $notice->find();
        }
        if ($cnt === 0) {
            $this->element('p', 'error', _('No results.'));

            $this->searchSuggestions($q);
            if (common_logged_in()) {
                $message = sprintf(_('Be the first to [post on this topic](%%%%action.newnotice%%%%?status_textarea=%s)!'), urlencode($q));
            }
            else {
                $message = sprintf(_('Why not [register an account](%%%%action.register%%%%) and be the first to  [post on this topic](%%%%action.newnotice%%%%?status_textarea=%s)!'), urlencode($q));
            }

            $this->elementStart('div', 'guide');
            $this->raw(common_markup_to_html($message));
            $this->elementEnd('div');
            return;
        }
        $terms = preg_split('/[\s,]+/', $q);
        $nl = new SearchNoticeList($notice, $this, $terms);
        $cnt = $nl->show();
        $this->pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'noticesearch', array('q' => $q));
    }
}

class SearchNoticeList extends NoticeList {
    function __construct($notice, $out=null, $terms)
    {
        parent::__construct($notice, $out);
        $this->terms = $terms;
    }

    function newListItem($notice)
    {
        return new SearchNoticeListItem($notice, $this->out, $this->terms);
    }
}

class SearchNoticeListItem extends NoticeListItem {
    function __construct($notice, $out=null, $terms)
    {
        parent::__construct($notice, $out);
        $this->terms = $terms;
    }

    function showContent()
    {
        // FIXME: URL, image, video, audio
        $this->out->elementStart('p', array('class' => 'entry-content'));
        if ($this->notice->rendered) {
            $this->out->raw($this->highlight($this->notice->rendered, $this->terms));
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->out->raw($this->highlight(common_render_content($this->notice->content, $this->notice), $this->terms));
        }
        $this->out->elementEnd('p');

    }

    /**
     * Highlist query terms
     *
     * @param string $text  notice text
     * @param array  $terms terms to highlight
     *
     * @return void
     */
    function highlight($text, $terms)
    {
        /* Highligh search terms */
        $options = implode('|', array_map('preg_quote', array_map('htmlspecialchars', $terms),
                                                            array_fill(0, sizeof($terms), '/')));
        $pattern = "/($options)/i";
        $result  = preg_replace($pattern, '<strong>\\1</strong>', $text);

        /* Remove highlighting from inside links, loop incase multiple highlights in links */
        $pattern = '/(href="[^"]*)<strong>('.$options.')<\/strong>([^"]*")/iU';
        do {
            $result = preg_replace($pattern, '\\1\\2\\3', $result, -1, $count);
        } while ($count);
        return $result;
    }
}

