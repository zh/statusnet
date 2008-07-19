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



function client_prefered_language($httplang) {
        $client_langs = array();
        $all_languages = get_all_languages();

        preg_match_all('"(((\S\S)-?(\S\S)?)(;q=([0-9.]+))?)\s*(,\s*|$)"',strtolower($httplang),$httplang);
        for ($i = 0; $i < count($httplang); $i++) {
             if(!empty($httplang[2][$i])) {
                    #if no q default to 1.0
                    $client_langs[$httplang[2][$i]] = ($httplang[6][$i]? (float) $httplang[6][$i] : 1.0);
                }
                if(!empty($httplang[3][$i]) && empty($client_langs[$httplang[3][$i]])) {
                    #if a catchall default 0.01 lower
                    $client_langs[$httplang[3][$i]] = ($httplang[6][$i]? (float) $httplang[6][$i]-0.01 : 0.99);
                }
            }
            #sort in decending q
            arsort($client_langs);

            foreach ($client_langs as $lang => $q) {
                if (isset($all_languages[$lang])) {
                    return($all_languages[$lang]['lang']);
                }
            }
            return FALSE;
}

function get_nice_language_list() {
        $nice_lang = array();
        $all_languages = get_all_languages();
        foreach ($all_languages as $lang) {
                $nice_lang = $nice_lang + array($lang['lang'] => $lang['name']);
        }
        return $nice_lang;
}

function get_all_languages() {
        $all_languages = array('en-us' => array('q' => 1, 'lang' => 'en_US', 'name' => 'English (US)', 'direction' => 'ltr'),
                            'en-nz' => array('q' => 1, 'lang' => 'en_NZ', 'name' => 'English (NZ)', 'direction' => 'ltr'),
                            'en' => array('q' => 1, 'lang' => 'en', 'name' => 'English', 'direction' => 'ltr'),
                            'fr-fr' => array('q' => 1, 'lang' => 'fr_FR', 'name' => 'French', 'direction' => 'ltr'),
                        );
        return $all_languages;
}
