<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * utility functions for i18n
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
 * @category I18n
 * @package  Laconica
 * @author   Matthew Gregg <matthew.gregg@gmail.com>
 * @author   Ciaran Gultnieks <ciaran@ciarang.com>
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Content negotiation for language codes
 *
 * @param string $httplang HTTP Accept-Language header
 *
 * @return string language code for best language match
 */

function client_prefered_language($httplang)
{
    $client_langs = array();

    $all_languages = common_config('site', 'languages');

    preg_match_all('"(((\S\S)-?(\S\S)?)(;q=([0-9.]+))?)\s*(,\s*|$)"',
                   strtolower($httplang), $httplang);

    for ($i = 0; $i < count($httplang); $i++) {
        if (!empty($httplang[2][$i])) {
            // if no q default to 1.0
            $client_langs[$httplang[2][$i]] =
              ($httplang[6][$i]? (float) $httplang[6][$i] : 1.0);
        }
        if (!empty($httplang[3][$i]) && empty($client_langs[$httplang[3][$i]])) {
            // if a catchall default 0.01 lower
            $client_langs[$httplang[3][$i]] =
              ($httplang[6][$i]? (float) $httplang[6][$i]-0.01 : 0.99);
        }
    }
    // sort in decending q
    arsort($client_langs);

    foreach ($client_langs as $lang => $q) {
        if (isset($all_languages[$lang])) {
            return($all_languages[$lang]['lang']);
        }
    }
    return false;
}

/**
 * returns a simple code -> name mapping for languages
 *
 * @return array map of available languages by code to language name.
 */

function get_nice_language_list()
{
    $nice_lang = array();

    $all_languages = common_config('site', 'languages');

    foreach ($all_languages as $lang) {
        $nice_lang = $nice_lang + array($lang['lang'] => $lang['name']);
    }
    return $nice_lang;
}

/**
 * Get a list of all languages that are enabled in the default config
 *
 * This should ONLY be called when setting up the default config in common.php.
 * Any other attempt to get a list of languages should instead call
 * common_config('site','languages')
 *
 * @return array mapping of language codes to language info
 */
function get_all_languages() {
	return array(
		'bg'      => array('q' => 0.8, 'lang' => 'bg_BG', 'name' => 'Bulgarian', 'direction' => 'ltr'),
		'ca'      => array('q' => 0.5, 'lang' => 'ca_ES', 'name' => 'Catalan', 'direction' => 'ltr'),
		'cs'      => array('q' => 0.5, 'lang' => 'cs_CZ', 'name' => 'Czech', 'direction' => 'ltr'),
		'de'      => array('q' => 0.8, 'lang' => 'de_DE', 'name' => 'German', 'direction' => 'ltr'),
		'el'      => array('q' => 0.1, 'lang' => 'el',    'name' => 'Greek', 'direction' => 'ltr'),
		'en-us'   => array('q' => 1, 'lang' => 'en_US', 'name' => 'English (US)', 'direction' => 'ltr'),
		'en-gb'   => array('q' => 1, 'lang' => 'en_GB', 'name' => 'English (British)', 'direction' => 'ltr'),
		'en'      => array('q' => 1, 'lang' => 'en_US',    'name' => 'English (US)', 'direction' => 'ltr'),
		'es'      => array('q' => 1, 'lang' => 'es',    'name' => 'Spanish', 'direction' => 'ltr'),
		'fi'      => array('q' => 1, 'lang' => 'fi', 'name' => 'Finnish', 'direction' => 'ltr'),
		'fr-fr'   => array('q' => 1, 'lang' => 'fr_FR', 'name' => 'French', 'direction' => 'ltr'),
		'he'      => array('q' => 0.5, 'lang' => 'he_IL', 'name' => 'Hebrew', 'direction' => 'rtl'),
		'it'      => array('q' => 1, 'lang' => 'it_IT', 'name' => 'Italian', 'direction' => 'ltr'),
		'jp'      => array('q' => 0.5, 'lang' => 'ja_JP', 'name' => 'Japanese', 'direction' => 'ltr'),
		'ko'      => array('q' => 0.9, 'lang' => 'ko_KR',    'name' => 'Korean', 'direction' => 'ltr'),
		'mk'      => array('q' => 0.5, 'lang' => 'mk_MK', 'name' => 'Macedonian', 'direction' => 'ltr'),
		'nb'      => array('q' => 0.1, 'lang' => 'nb_NO', 'name' => 'Norwegian (Bokmål)', 'direction' => 'ltr'),
		'no'      => array('q' => 0.1, 'lang' => 'nb_NO', 'name' => 'Norwegian (Bokmål)', 'direction' => 'ltr'),
		'nn'      => array('q' => 1, 'lang' => 'nn_NO', 'name' => 'Norwegian (Nynorsk)', 'direction' => 'ltr'),
		'nl'      => array('q' => 0.5, 'lang' => 'nl_NL', 'name' => 'Dutch', 'direction' => 'ltr'),
		'pl'      => array('q' => 0.5, 'lang' => 'pl_PL', 'name' => 'Polish', 'direction' => 'ltr'),
		'pt'      => array('q' => 0.1, 'lang' => 'pt',    'name' => 'Portuguese', 'direction' => 'ltr'),
		'pt-br'   => array('q' => 0.9, 'lang' => 'pt_BR', 'name' => 'Portuguese Brazil', 'direction' => 'ltr'),
		'ru'      => array('q' => 0.9, 'lang' => 'ru_RU', 'name' => 'Russian', 'direction' => 'ltr'),
		'sv'      => array('q' => 0.8, 'lang' => 'sv_SE', 'name' => 'Swedish', 'direction' => 'ltr'),
		'te'      => array('q' => 0.3, 'lang' => 'te_IN', 'name' => 'Telugu', 'direction' => 'ltr'),
		'tr'      => array('q' => 0.5, 'lang' => 'tr_TR', 'name' => 'Turkish', 'direction' => 'ltr'),
		'uk'      => array('q' => 1, 'lang' => 'uk_UA', 'name' => 'Ukrainian', 'direction' => 'ltr'),
		'vi'      => array('q' => 0.8, 'lang' => 'vi_VN', 'name' => 'Vietnamese', 'direction' => 'ltr'),
		'zh-cn'   => array('q' => 0.9, 'lang' => 'zh_CN', 'name' => 'Chinese (Simplified)', 'direction' => 'ltr'),
		'zh-hant' => array('q' => 0.2, 'lang' => 'zh_TW', 'name' => 'Chinese (Taiwanese)', 'direction' => 'ltr'),
	);
}
