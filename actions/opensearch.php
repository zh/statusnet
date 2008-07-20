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

class OpensearchAction extends Action {

	function handle($args) {

		parent::handle($args);

                $type = $this->trimmed('type');
                
                $short_name = '';
                if ($type == 'people') {
                    $type = 'peoplesearch';
                    $short_name = 'People Search';
                } else {
                    $short_name = 'Notice Search';
                    $type = 'noticesearch';
                }

		header('Content-Type: text/html');

		common_start_xml();
		common_element_start('OpenSearchDescription', array('xmlns' => 'http://a9.com/-/spec/opensearch/1.1/'));
                
                $short_name =  common_config('site', 'name').' '.$short_name;
		common_element('ShortName', NULL, $short_name);
		common_element('Contact', NULL, common_config('site', 'email'));
                common_element('Url', array('type' => 'text/html', 'method' => 'get', 
                               'template' => common_path('index.php?action='.$type.'&q={searchTerms}'))); 
                common_element('Image', array('height' => 16, 'width' => 16, 'type' => 'image/vnd.microsoft.icon'), common_path('favicon.ico')); 
                common_element('Image', array('height' => 50, 'width' => 50, 'type' => 'image/png'), theme_path('logo.png')); 
                common_element('AdultContent', NULL, 'false');
                common_element('Language', NULL, common_language());
                common_element('OutputEncoding', NULL, 'UTF-8');
                common_element('InputEncoding', NULL, 'UTF-8');

		common_element_end('OpenSearchDescription');
		common_end_xml();
	}
}
