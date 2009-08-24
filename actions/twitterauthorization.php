<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Class for doing OAuth authentication against Twitter
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
 * @category  TwitterauthorizationAction
 * @package   Laconica
 * @author    Zach Copely <zach@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

class TwitterauthorizationAction extends Action
{

    function prepare($args)
    {
        parent::prepare($args);

        $this->oauth_token = $this->arg('oauth_token');

        return true;
    }

    /**
     * Handler method
     *
     * @param array $args is ignored since it's now passed in in prepare()
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'), 403);
        }

        $user  = common_current_user();
        $flink = Foreign_link::getByUserID($user->id, TWITTER_SERVICE);

        // If there's already a foreign link record, it means we already
        // have an access token, and this is unecessary. So go back.

        if (isset($flink)) {
            common_redirect(common_local_url('twittersettings'));
        }

        // $this->oauth_token is only populated once Twitter authorizes our
        // request token. If it's empty we're at the beginning of the auth
        // process

        if (empty($this->oauth_token)) {
            $this->authorizeRequestToken();
        } else {
            $this->saveAccessToken();
        }
    }

    /**
     * Asks Twitter for a request token, and then redirects to Twitter
     * to authorize it.
     *
     * @return nothing
     */
    function authorizeRequestToken()
    {
        try {

            // Get a new request token and authorize it

            $client  = new TwitterOAuthClient();
            $req_tok =
              $client->getRequestToken(TwitterOAuthClient::$requestTokenURL);

            // Sock the request token away in the session temporarily

            $_SESSION['twitter_request_token']        = $req_tok->key;
            $_SESSION['twitter_request_token_secret'] = $req_tok->secret;

            $auth_link = $client->getAuthorizeLink($req_tok);

        } catch (TwitterOAuthClientException $e) {
            $msg = sprintf('OAuth client cURL error - code: %1s, msg: %2s',
                           $e->getCode(), $e->getMessage());
            $this->serverError(_('Couldn\'t link your Twitter account.'));
        }

        common_redirect($auth_link);
    }

    /**
     * Called when Twitter returns an authorized request token. Exchanges
     * it for an access token and stores it.
     *
     * @return nothing
     */
    function saveAccessToken()
    {

        // Check to make sure Twitter returned the same request
        // token we sent them

        if ($_SESSION['twitter_request_token'] != $this->oauth_token) {
            $this->serverError(_('Couldn\'t link your Twitter account.'));
        }

        try {

            $client = new TwitterOAuthClient($_SESSION['twitter_request_token'],
                $_SESSION['twitter_request_token_secret']);

            // Exchange the request token for an access token

            $atok = $client->getAccessToken(TwitterOAuthClient::$accessTokenURL);

            // Test the access token and get the user's Twitter info

            $client       = new TwitterOAuthClient($atok->key, $atok->secret);
            $twitter_user = $client->verifyCredentials();

        } catch (OAuthClientException $e) {
            $msg = sprintf('OAuth client cURL error - code: %1$s, msg: %2$s',
                           $e->getCode(), $e->getMessage());
            $this->serverError(_('Couldn\'t link your Twitter account.'));
        }

        // Save the access token and Twitter user info

        $this->saveForeignLink($atok, $twitter_user);

        // Clean up the the mess we made in the session

        unset($_SESSION['twitter_request_token']);
        unset($_SESSION['twitter_request_token_secret']);

        common_redirect(common_local_url('twittersettings'));
    }

    /**
     * Saves a Foreign_link between Twitter user and local user,
     * which includes the access token and secret.
     *
     * @param OAuthToken $access_token the access token to save
     * @param mixed      $twitter_user twitter API user object
     *
     * @return nothing
     */
    function saveForeignLink($access_token, $twitter_user)
    {
        $user = common_current_user();

        $flink = new Foreign_link();

        $flink->user_id     = $user->id;
        $flink->foreign_id  = $twitter_user->id;
        $flink->service     = TWITTER_SERVICE;

        $creds = TwitterOAuthClient::packToken($access_token);

        $flink->credentials = $creds;
        $flink->created     = common_sql_now();

        // Defaults: noticesync on, everything else off

        $flink->set_flags(true, false, false, false);

        $flink_id = $flink->insert();

        if (empty($flink_id)) {
            common_log_db_error($flink, 'INSERT', __FILE__);
                $this->serverError(_('Couldn\'t link your Twitter account.'));
        }

        save_twitter_user($twitter_user->id, $twitter_user->screen_name);
    }

}

