<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Laconica-only extensions to the Twitter-like API
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
 * @category  Twitter
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/twitterapi.php';

/**
 * Laconica-specific API methods
 *
 * This class handles all /laconica/ API methods.
 *
 * @category  Twitter
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

class TwitapilaconicaAction extends TwitterapiAction
{
    /**
     * A version stamp for the API
     *
     * Returns a version number for this version of Laconica, which
     * should make things a bit easier for upgrades.
     * URL: http://identi.ca/api/laconica/version.(xml|json)
     * Formats: xml, json
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function version($args, $apidata)
    {
        parent::handle($args);
        switch ($apidata['content-type']) {
         case 'xml':
            $this->init_document('xml');
            common_element('version', null, LACONICA_VERSION);
            $this->end_document('xml');
         case 'json':
            $this->init_document('json');
            print '"'.LACONICA_VERSION.'"';
            $this->end_document('json');
         default:
            $this->client_error(_('API method not found!'), $code=404);
        }
    }

    /**
     * Dump of configuration variables
     *
     * Gives a full dump of configuration variables for this instance
     * of Laconica, minus variables that may be security-sensitive (like
     * passwords).
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function config($args, $apidata)
    {
        parent::handle($args);
        common_server_error(_('API method under construction.'), 501);
    }

    /**
     * WADL description of the API
     *
     * Gives a WADL description of the API provided by this version of the
     * software.
     *
     * @param array $args    Web arguments
     * @param array $apidata Twitter API data
     *
     * @return void
     *
     * @see ApiAction::process_command()
     */

    function wadl($args, $apidata)
    {
        parent::handle($args);
        common_server_error(_('API method under construction.'), 501);
    }
}
