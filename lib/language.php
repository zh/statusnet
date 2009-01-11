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
        $all_languages = common_config('site','languages');

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
        $all_languages = common_config('site','languages');
        foreach ($all_languages as $lang) {
                $nice_lang = $nice_lang + array($lang['lang'] => $lang['name']);
        }
        return $nice_lang;
}

// Get a list of all languages that are enabled in the default config. This
// should ONLY be called when setting up the default config in common.php.
// Any other attempt to get a list of lanugages should instead call
// common_config('site','languages')
function get_all_languages() {
	return array(
		'en-us' => array('q' => 1, 'lang' => 'en_US', 'name' => 'English (US)', 'direction' => 'ltr'),
		'en-gb' => array('q' => 1, 'lang' => 'en_GB', 'name' => 'English (British)', 'direction' => 'ltr'),
		'en'    => array('q' => 1, 'lang' => 'en',    'name' => 'English', 'direction' => 'ltr'),
		'nl'    => array('q' => 1, 'lang' => 'nl_NL', 'name' => 'Dutch', 'direction' => 'ltr'),
		'fr-fr' => array('q' => 0.9, 'lang' => 'fr_FR', 'name' => 'French', 'direction' => 'ltr'),
		'de'    => array('q' => 1, 'lang' => 'de_DE', 'name' => 'German', 'direction' => 'ltr'),
		'it'    => array('q' => 1, 'lang' => 'it_IT', 'name' => 'Italian', 'direction' => 'ltr'),
		'ko'    => array('q' => 0.1, 'lang' => 'ko',    'name' => 'Korean', 'direction' => 'ltr'),
		'nb'    => array('q' => 1, 'lang' => 'nb_NO', 'name' => 'Norwegian (bokmal)', 'direction' => 'ltr'),
		'pt'    => array('q' => 0.2, 'lang' => 'pt',    'name' => 'Portuguese', 'direction' => 'ltr'),
		'pt-br' => array('q' => 1, 'lang' => 'pt_BR', 'name' => 'Portuguese Brazil', 'direction' => 'ltr'),
#		'ru'    => array('q' => 0.1, 'lang' => 'ru_RU', 'name' => 'Russian', 'direction' => 'ltr'),
		'es'    => array('q' => 1, 'lang' => 'es',    'name' => 'Spanish', 'direction' => 'ltr'),
		'tr'    => array('q' => 1, 'lang' => 'tr_TR', 'name' => 'Turkish', 'direction' => 'ltr'),
		'uk'    => array('q' => 1, 'lang' => 'uk_UA', 'name' => 'Ukrainian', 'direction' => 'ltr'),
#		'lt'    => array('q' => 0.1, 'lang' => 'lt_LT', 'name' => 'Lithuanian', 'direction' => 'ltr'),
#		'sv'    => array('q' => 1, 'lang' => 'sv_SE', 'name' => 'Swedish', 'direction' => 'ltr'),
		'pl'	=> array('q' => 1, 'lang' => 'pl_PL', 'name' => 'Polish', 'direction' => 'ltr'),
		'mk'	=> array('q' => 1, 'lang' => 'mk_MK', 'name' => 'Macedonian', 'direction' => 'ltr'),
		'jp'	=> array('q' => 0.1, 'lang' => 'ja_JP', 'name' => 'Japanese', 'direction' => 'ltr'),
		'cs'	=> array('q' => 1, 'lang' => 'cs_CZ', 'name' => 'Czech', 'direction' => 'ltr'),
		'ca'	=> array('q' => 1, 'lang' => 'ca_ES', 'name' => 'Catalan', 'direction' => 'ltr'),
#		'hr'    => array('q' => 0.1, 'lang' => 'he_IL', 'name' => 'Hebrew', 'direction' => 'ltr')
	);
}

