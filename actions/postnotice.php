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

require_once(INSTALLDIR.'/lib/omb.php');

class PostnoticeAction extends Action
{
    function handle($args)
    {
        parent::handle($args);
        try {
            common_remove_magic_from_request();
            $req = OAuthRequest::from_request('POST', common_local_url('postnotice'));
            # Note: server-to-server function!
            $server = omb_oauth_server();
            list($consumer, $token) = $server->verify_request($req);
            if ($this->save_notice($req, $consumer, $token)) {
                print "omb_version=".OMB_VERSION_01;
            }
        } catch (OAuthException $e) {
            $this->serverError($e->getMessage());
            return;
        }
    }

    function save_notice(&$req, &$consumer, &$token)
    {
        $version = $req->get_parameter('omb_version');
        if ($version != OMB_VERSION_01) {
            $this->clientError(_('Unsupported OMB version'), 400);
            return false;
        }
        # First, check to see
        $listenee =  $req->get_parameter('omb_listenee');
        $remote_profile = Remote_profile::staticGet('uri', $listenee);
        if (!$remote_profile) {
            $this->clientError(_('Profile unknown'), 403);
            return false;
        }
        $sub = Subscription::staticGet('token', $token->key);
        if (!$sub) {
            $this->clientError(_('No such subscription'), 403);
            return false;
        }
        $content = $req->get_parameter('omb_notice_content');
        $content_shortened = common_shorten_links($content);
        if (mb_strlen($content_shortened) > 140) {
            $this->clientError(_('Invalid notice content'), 400);
            return false;
        }
        $notice_uri = $req->get_parameter('omb_notice');
        if (!Validate::uri($notice_uri) &&
            !common_valid_tag($notice_uri)) {
            $this->clientError(_('Invalid notice uri'), 400);
            return false;
        }
        $notice_url = $req->get_parameter('omb_notice_url');
        if ($notice_url && !common_valid_http_url($notice_url)) {
            $this->clientError(_('Invalid notice url'), 400);
            return false;
        }
        $notice = Notice::staticGet('uri', $notice_uri);
        if (!$notice) {
            $notice = Notice::saveNew($remote_profile->id, $content, 'omb', false, null, $notice_uri);
            if (is_string($notice)) {
                common_server_serror($notice, 500);
                return false;
            }
            common_broadcast_notice($notice, true);
        }
        return true;
    }
}
