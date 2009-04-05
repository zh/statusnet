<?php
/**
 * People search action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
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
require_once INSTALLDIR.'/lib/profilelist.php';

/**
 * People search action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class PeoplesearchAction extends SearchAction
{
    function getInstructions()
    {
        return _('Search for people on %%site.name%% by their name, location, or interests. ' .
                  'Separate the terms by spaces; they must be 3 characters or more.');
    }

    function title()
    {
        return _('People search');
    }

    function showResults($q, $page)
    {
        $profile = new Profile();
        $search_engine = $profile->getSearchEngine('identica_people');
        $search_engine->set_sort_mode('chron');
        // Ask for an extra to see if there's more.
        $search_engine->limit((($page-1)*PROFILES_PER_PAGE), PROFILES_PER_PAGE + 1);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        }
        else {
            $cnt = $profile->find();
        }
        if ($cnt > 0) {
            $terms = preg_split('/[\s,]+/', $q);
            $results = new PeopleSearchResults($profile, $terms, $this);
            $results->show();
            $profile->free();
            $this->pagination($page > 1, $cnt > PROFILES_PER_PAGE,
                          $page, 'peoplesearch', array('q' => $q));

        } else {
            $this->element('p', 'error', _('No results.'));

            $qe = urlencode($q);
            $message = _(<<<E_O_T
* Make sure all words are spelled correctly.
* Try different keywords.
* Try more general keywords.
* Try fewer keywords.

You can also try your search on other engines:

* [Twingly](http://www.twingly.com/search?q=$qe&content=microblog&site=identi.ca)
* [Tweet scan](http://www.tweetscan.com/indexi.php?s=$qe)
* [Google](http://www.google.com/search?q=site%3Aidenti.ca+$qe)
* [Yahoo](http://search.yahoo.com/search?p=site%3Aidenti.ca+$qe)

E_O_T
);
            $this->elementStart('div', 'blankfiller');
            $this->raw(common_markup_to_html($message));
            $this->elementEnd('div');
            $profile->free();
        }
    }
}

