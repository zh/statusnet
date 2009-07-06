<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapifavoritesAction extends TwitterapiAction
{

    function favorites($args, $apidata)
    {
        parent::handle($args);

        $this->auth_user = $apidata['user'];
        $user = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($user)) {
        if ($apidata['content-type'] == 'xml') {
            $this->show_single_xml_status($notice);
        } elseif ($apidata['content-type'] == 'json') {
            $this->show_single_json_status($notice);
        }
            $this->clientError('Not Found', 404, $apidata['content-type']);
            return;
        }

        $profile = $user->getProfile();

        $sitename   = common_config('site', 'name');
        $title      = sprintf(_('%s / Favorites from %s'), $sitename,
            $user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:Favorites:".$user->id;
        $link       = common_local_url('favorites',
            array('nickname' => $user->nickname));
        $subtitle   = sprintf(_('%s updates favorited by %s / %s.'), $sitename,
            $profile->getBestName(), $user->nickname);

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        if (!empty($this->auth_user) && $this->auth_user->id == $user->id) {
            $notice = $user->favoriteNotices(($page-1)*$count, $count, true);
        } else {
            $notice = $user->favoriteNotices(($page-1)*$count, $count, false);
        }

        switch($apidata['content-type']) {
        case 'xml':
            $this->show_xml_timeline($notice);
            break;
        case 'rss':
            $this->show_rss_timeline($notice, $title, $link, $subtitle);
            break;
        case 'atom':
            if (isset($apidata['api_arg'])) {
                 $selfuri = $selfuri = common_root_url() .
                     'api/favorites/' . $apidata['api_arg'] . '.atom';
            } else {
                 $selfuri = $selfuri = common_root_url() .
                  'api/favorites.atom';
            }
            $this->show_atom_timeline($notice, $title, $id, $link,
                $subtitle, null, $selfuri);
            break;
        case 'json':
            $this->show_json_timeline($notice);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    function create($args, $apidata)
    {
        parent::handle($args);

        // Check for RESTfulness
        if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
            $this->clientError(_('This method requires a POST or DELETE.'),
                400, $apidata['content-type']);
            return;
        }

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        $user      = $apidata['user']; // Always the auth user
        $notice_id = $apidata['api_arg'];
        $notice    = Notice::staticGet($notice_id);

        if (empty($notice)) {
            $this->clientError(_('No status found with that ID.'),
                404, $apidata['content-type']);
            return;
        }

        // XXX: Twitter lets you fave things repeatedly via api.
        if ($user->hasFave($notice)) {
            $this->clientError(_('This status is already a favorite!'),
                403, $apidata['content-type']);
            return;
        }

        $fave = Fave::addNew($user, $notice);

        if (empty($fave)) {
            $this->clientError(_('Could not create favorite.'));
            return;
        }

        $this->notify($fave, $notice, $user);
        $user->blowFavesCache();

        if ($apidata['content-type'] == 'xml') {
            $this->show_single_xml_status($notice);
        } elseif ($apidata['content-type'] == 'json') {
            $this->show_single_json_status($notice);
        }

    }

    function destroy($args, $apidata)
    {
        parent::handle($args);

        // Check for RESTfulness
        if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
            $this->clientError(_('This method requires a POST or DELETE.'),
                400, $apidata['content-type']);
            return;
        }

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        $user      = $apidata['user']; // Always the auth user
        $notice_id = $apidata['api_arg'];
        $notice    = Notice::staticGet($notice_id);

        if (empty($notice)) {
            $this->clientError(_('No status found with that ID.'),
                404, $apidata['content-type']);
            return;
        }

        $fave            = new Fave();
        $fave->user_id   = $this->id;
        $fave->notice_id = $notice->id;

        if (!$fave->find(true)) {
            $this->clientError(_('That status is not a favorite!'),
                403, $apidata['content-type']);
            return;
        }

        $result = $fave->delete();

        if (!$result) {
            common_log_db_error($fave, 'DELETE', __FILE__);
            $this->clientError(_('Could not delete favorite.'), 404);
            return;
        }

        $user->blowFavesCache();

        if ($apidata['content-type'] == 'xml') {
            $this->show_single_xml_status($notice);
        } elseif ($apidata['content-type'] == 'json') {
            $this->show_single_json_status($notice);
        }

    }

    // XXX: these two funcs swiped from faves.
    // Maybe put in util.php, or some common base class?

    function notify($fave, $notice, $user)
    {
        $other = User::staticGet('id', $notice->profile_id);
        if ($other && $other->id != $user->id) {
            if ($other->email && $other->emailnotifyfav) {
                $this->notify_mail($other, $user, $notice);
            }
            # XXX: notify by IM
            # XXX: notify by SMS
        }
    }

    function notify_mail($other, $user, $notice)
    {
        $profile = $user->getProfile();
        $bestname = $profile->getBestName();
        $subject = sprintf(_('%s added your notice as a favorite'), $bestname);
        $body = sprintf(_("%1\$s just added your notice from %2\$s as one of their favorites.\n\n" .
                          "In case you forgot, you can see the text of your notice here:\n\n" .
                          "%3\$s\n\n" .
                          "You can see the list of %1\$s's favorites here:\n\n" .
                          "%4\$s\n\n" .
                          "Faithfully yours,\n" .
                          "%5\$s\n"),
                        $bestname,
                        common_exact_date($notice->created),
                        common_local_url('shownotice', array('notice' => $notice->id)),
                        common_local_url('showfavorites', array('nickname' => $user->nickname)),
                        common_config('site', 'name'));

        mail_to_user($other, $subject, $body);
    }

}