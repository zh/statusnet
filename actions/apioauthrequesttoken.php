<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Get an OAuth request token
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
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apioauth.php';

/**
 * Get an OAuth request token
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiOauthRequestTokenAction extends ApiOauthAction
{
    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        $this->callback  = $this->arg('oauth_callback');

        if (!empty($this->callback)) {
            common_debug("callback: $this->callback");
        }

        return true;
    }

    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return void
     */
    function handle($args)
    {
        parent::handle($args);

        $datastore   = new ApiStatusNetOAuthDataStore();
        $server      = new OAuthServer($datastore);
        $hmac_method = new OAuthSignatureMethod_HMAC_SHA1();

        $server->add_signature_method($hmac_method);

        try {
            $req   = OAuthRequest::from_request();
            $token = $server->fetch_request_token($req);
            print $token;
        } catch (OAuthException $e) {
            common_log(LOG_WARNING, 'API OAuthException - ' . $e->getMessage());
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: text/html; charset=utf-8');
            print $e->getMessage() . "\n";
        }
    }

}
