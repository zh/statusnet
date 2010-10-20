<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/lib/oauthstore.php';

class ApiStatusNetOAuthDataStore extends StatusNetOAuthDataStore
{
    function lookup_consumer($consumerKey)
    {
        $con = Consumer::staticGet('consumer_key', $consumerKey);

        if (!$con) {

            // Create an anon consumer and anon application if one
            // doesn't exist already
            if ($consumerKey == 'anonymous') {
                $con = new Consumer();
                $con->consumer_key    = $consumerKey;
                $con->consumer_secret = $consumerKey;
                $result = $con->insert();
                if (!$result) {
                    // TRANS: Server error displayed when trying to create an anynymous OAuth consumer.
                    $this->serverError(_('Could not create anonymous consumer.'));
                }
                $app               = new OAuth_application();
                $app->consumer_key = $con->consumer_key;
                $app->name         = 'anonymous';

                // XXX: allow the user to set the access type when
                // authorizing? Currently we default to r+w for anonymous
                // OAuth client applications
                $app->access_type  = 3; // read + write
                $id = $app->insert();
                if (!$id) {
                    // TRANS: Server error displayed when trying to create an anynymous OAuth application.
                    $this->serverError(_('Could not create anonymous OAuth application.'));
                }
            } else {
                return null;
            }
        }

        return new OAuthConsumer(
            $con->consumer_key,
            $con->consumer_secret
        );
    }

    function getAppByRequestToken($token_key)
    {
        // Look up the full req tokenx
        $req_token = $this->lookup_token(null,
                                         'request',
                                         $token_key);

        if (empty($req_token)) {
            common_debug("couldn't get request token from oauth datastore");
            return null;
        }

        // Look up the full Token
        $token = new Token();
        $token->tok = $req_token->key;
        $result = $token->find(true);

        if (empty($result)) {
            common_debug('Couldn\'t find req token in the token table.');
            return null;
        }

        // Look up the app

        $app = new Oauth_application();
        $app->consumer_key = $token->consumer_key;
        $result = $app->find(true);

        if (!empty($result)) {
            return $app;
        } else {
            common_debug("Couldn't find the app!");
            return null;
        }
    }

    function new_access_token($token, $consumer, $verifier)
    {
        common_debug(
            sprintf(
                "%s - New access token from request token %s, consumer %s and verifier %s ",
                __FILE__,
                $token,
                $consumer,
                $verifier
            )
        );

        $rt = new Token();

        $rt->consumer_key = $consumer->key;
        $rt->tok          = $token->key;
        $rt->type         = 0; // request

        $app = Oauth_application::getByConsumerKey($consumer->key);
        assert(!empty($app));

        if ($rt->find(true) && $rt->state == 1 && $rt->verifier == $verifier) { // authorized

            common_debug('request token found.');

            // find the associated user of the app

            $appUser = new Oauth_application_user();

            $appUser->application_id = $app->id;
            $appUser->token          = $rt->tok;

            $result = $appUser->find(true);

            if (!empty($result)) {
                common_debug("Ouath app user found.");
            } else {
                common_debug("Oauth app user not found. app id $app->id token $rt->tok");
                return null;
            }

            // go ahead and make the access token

            $at = new Token();
            $at->consumer_key      = $consumer->key;
            $at->tok               = common_good_rand(16);
            $at->secret            = common_good_rand(16);
            $at->type              = 1; // access
            $at->verifier          = $verifier;
            $at->verified_callback = $rt->verified_callback; // 1.0a
            $at->created = DB_DataObject_Cast::dateTime();

            if (!$at->insert()) {
                $e = $at->_lastError;
                common_debug('access token "'.$at->tok.'" not inserted: "'.$e->message.'"', __FILE__);
                return null;
            } else {
                common_debug('access token "'.$at->tok.'" inserted', __FILE__);
                // burn the old one
                $orig_rt = clone($rt);
                $rt->state = 2; // used
                if (!$rt->update($orig_rt)) {
                    return null;
                }
                common_debug('request token "'.$rt->tok.'" updated', __FILE__);

                // update the token from req to access for the user

                $orig = clone($appUser);

                $appUser->token = $at->tok;

                // It's at this point that we change the access type
                // to whatever the application's access is.  Request
                // tokens should always have an access type of 0, and
                // therefore be unuseable for making requests for
                // protected resources.

                $appUser->access_type = $app->access_type;

                $result = $appUser->updateKeys($orig);

                if (!$result) {
                    throw new Exception('Couldn\'t update OAuth app user.');
                }

                // Okay, good
                return new OAuthToken($at->tok, $at->secret);
            }
        } else {
            return null;
        }
    }

    /**
     * Revoke specified access token
     *
     * Revokes the token specified by $token_key.
     * Throws exceptions in case of error.
     *
     * @param string $token_key the token to be revoked
     * @param int    $type      type of token (0 = req, 1 = access)
     *
     * @access public
     *
     * @return void
     */
    public function revoke_token($token_key, $type = 0) {
        $rt        = new Token();
        $rt->tok   = $token_key;
        $rt->type  = $type;
        $rt->state = 0;

        if (!$rt->find(true)) {
            // TRANS: Exception thrown when an attempt is made to revoke an unknown token.
            throw new Exception(_('Tried to revoke unknown token.'));
        }

        if (!$rt->delete()) {
            // TRANS: Exception thrown when an attempt is made to remove a revoked token.
            throw new Exception(_('Failed to delete revoked token.'));
        }
    }

    /*
     * Create a new request token. Overrided to support OAuth 1.0a callback
     *
     * @param OAuthConsumer $consumer the OAuth Consumer for this token
     * @param string        $callback the verified OAuth callback URL
     *
     * @return OAuthToken   $token a new unauthorized OAuth request token
     */
    function new_request_token($consumer, $callback)
    {
        $t = new Token();
        $t->consumer_key = $consumer->key;
        $t->tok = common_good_rand(16);
        $t->secret = common_good_rand(16);
        $t->type = 0; // request
        $t->state = 0; // unauthorized
        $t->verified_callback = $callback;

        if ($callback === 'oob') {
            // six digit pin
            $t->verifier = mt_rand(0, 9999999);
        } else {
            $t->verifier = common_good_rand(8);
        }

        $t->created = DB_DataObject_Cast::dateTime();
        if (!$t->insert()) {
            return null;
        } else {
            return new OAuthToken($t->tok, $t->secret);
        }
    }
}
