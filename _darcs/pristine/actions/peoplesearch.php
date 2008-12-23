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

require_once(INSTALLDIR.'/lib/searchaction.php');
require_once(INSTALLDIR.'/lib/profilelist.php');

class PeoplesearchAction extends SearchAction {

    function get_instructions() {
        return _('Search for people on %%site.name%% by their name, location, or interests. ' .
                  'Separate the terms by spaces; they must be 3 characters or more.');
    }

    function get_title() {
        return _('People search');
    }

    function show_results($q, $page) {

        $profile = new Profile();

        # lcase it for comparison
        $q = strtolower($q);

        $search_engine = $profile->getSearchEngine('identica_people');

        $search_engine->set_sort_mode('chron');
        # Ask for an extra to see if there's more.
        $search_engine->limit((($page-1)*PROFILES_PER_PAGE), PROFILES_PER_PAGE + 1);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        }
        else {
            $cnt = $profile->find();
        }
        if ($cnt > 0) {
            $terms = preg_split('/[\s,]+/', $q);
            $results = new PeopleSearchResults($profile, $terms);
            $results->show_list();
        } else {
            common_element('p', 'error', _('No results'));
        }

        $profile->free();
        
        common_pagination($page > 1, $cnt > PROFILES_PER_PAGE,
                          $page, 'peoplesearch', array('q' => $q));
    }
}

class PeopleSearchResults extends ProfileList {

    var $terms = null;
    var $pattern = null;
    
    function __construct($profile, $terms) {
        parent::__construct($profile);
        $this->terms = array_map('preg_quote', 
                                 array_map('htmlspecialchars', $terms));
        $this->pattern = '/('.implode('|',$terms).')/i';
    }
    
    function highlight($text) {
        return preg_replace($this->pattern, '<strong>\\1</strong>', htmlspecialchars($text));
    }
}
