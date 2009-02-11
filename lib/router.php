<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * URL routing utilities
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
 * @category  URL
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once 'Net/URL/Mapper.php';

/**
 * URL Router
 *
 * Cheap wrapper around Net_URL_Mapper
 *
 * @category URL
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class Router
{
    var $m = null;

    function __construct()
    {
        $m = Net_URL_Mapper::getInstance();

        // In the "root"

        $m->connect('', array('action' => 'public'));
        $m->connect('rss', array('action' => 'publicrss'));
        $m->connect('xrds', array('action' => 'publicxrds'));
        $m->connect('featuredrss', array('action' => 'featuredrss'));
        $m->connect('favoritedrss', array('action' => 'favoritedrss'));
        $m->connect('opensearch/people', array('action' => 'opensearch',
                                               'type' => 'people'));
        $m->connect('opensearch/notice', array('action' => 'opensearch',
                                               'type' => 'notice'));

        // docs

        $m->connect('doc/:title', array('action' => 'doc'));

        // facebook

        $m->connect('facebook', array('action' => 'facebookhome'));
        $m->connect('facebook/index.php', array('action' => 'facebookhome'));
        $m->connect('facebook/settings.php', array('action' => 'facebooksettings'));
        $m->connect('facebook/invite.php', array('action' => 'facebookinvite'));
        $m->connect('facebook/remove', array('action' => 'facebookremove'));

        // main stuff is repetitive

        $main = array('login', 'logout', 'register', 'subscribe',
                      'unsubscribe', 'confirmaddress', 'recoverpassword',
                      'invite', 'favor', 'disfavor', 'sup',
                      'tagother', 'block');

        foreach ($main as $a) {
            $m->connect('main/'.$a, array('action' => $a));
        }

        // these take a code

        foreach (array('register', 'confirmaddress', 'recoverpassword') as $c) {
            $m->connect('main/'.$c.'/:code', array('action' => $c));
        }

        // exceptional

        $m->connect('main/openid', array('action' => 'openidlogin'));
        $m->connect('main/remote', array('action' => 'remotesubscribe'));

        // settings

        foreach (array('profile', 'avatar', 'password', 'openid', 'im',
                       'email', 'sms', 'twitter', 'other') as $s) {
            $m->connect('settings/'.$s, array('action' => $s.'settings'));
        }

        // search

        foreach (array('group', 'people', 'notice') as $s) {
            $m->connect('search/'.$s, array('action' => $s.'search'));
        }

        $m->connect('search/notice/rss', array('action' => 'noticesearchrss'));

        // notice

        $m->connect('notice/new', array('action' => 'newnotice'));
        $m->connect('notice/:notice',
                    array('action' => 'shownotice'),
                    array('notice' => '[0-9]+'));
        $m->connect('notice/delete', array('action' => 'deletenotice'));
        $m->connect('notice/delete/:notice',
                    array('action' => 'deletenotice'),
                    array('notice' => '[0-9]+'));

        $m->connect('message/new', array('action' => 'newmessage'));
        $m->connect('message/:message',
                    array('action' => 'showmessage'),
                    array('message' => '[0-9]+'));

        $m->connect('user/:id',
                    array('action' => 'userbyid'),
                    array('id' => '[0-9]+'));

        $m->connect('tags/?', array('action' => 'publictagcloud'));
        $m->connect('tag/?', array('action' => 'publictagcloud'));
        $m->connect('tag/:tag/rss',
                    array('action' => 'tagrss'),
                    array('tag' => '[a-zA-Z0-9]+'));
        $m->connect('tag/:tag',
                    array('action' => 'tag'),
                    array('tag' => '[a-zA-Z0-9]+'));

        $m->connect('peopletag/:tag',
                    array('action' => 'peopletag'),
                    array('tag' => '[a-zA-Z0-9]+'));

        $m->connect('featured/?', array('action' => 'featured'));
        $m->connect('favorited/?', array('action' => 'favorited'));

        // groups

        $m->connect('group/new', array('action' => 'newgroup'));

        foreach (array('edit', 'join', 'leave') as $v) {
            $m->connect('group/:nickname/'.$v,
                        array('action' => $v.'group'),
                        array('nickname' => '[a-zA-Z0-9]+'));
        }

        foreach (array('members', 'logo', 'rss') as $n) {
            $m->connect('group/:nickname/'.$n,
                        array('action' => 'group'.$n),
                        array('nickname' => '[a-zA-Z0-9]+'));
        }

        $m->connect('group/:id/id',
                    array('action' => 'groupbyid'),
                    array('id' => '[0-9]+'));

        $m->connect('group/:nickname',
                    array('action' => 'showgroup'),
                    array('nickname' => '[a-zA-Z0-9]+'));

        $m->connect('group/?', array('action' => 'groups'));

        // Twitter-compatible API

        // statuses API

        $m->connect('api/statuses/:method',
                    array('action' => 'api',
                          'apiaction' => 'statuses'),
                    array('method' => '(public_timeline|friends_timeline|user_timeline|update|replies|friends|followers|featured)(\.(atom|rss|xml|json))?'));

        $m->connect('api/statuses/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'statuses'),
                    array('method' => '(user_timeline|show|destroy|friends|followers)'));

        // users

        $m->connect('api/users/show/:argument',
                    array('action' => 'api',
                          'apiaction' => 'users'));

        $m->connect('api/users/:method',
                    array('action' => 'api',
                          'apiaction' => 'users'),
                    array('method' => 'show(\.(xml|json|atom|rss))?'));

        // direct messages

        $m->connect('api/direct_messages/:method',
                    array('action' => 'api',
                          'apiaction' => 'direct_messages'),
                    array('method' => '(sent|new)(\.(xml|json|atom|rss))?'));

        $m->connect('api/direct_messages/destroy/:argument',
                    array('action' => 'api',
                          'apiaction' => 'direct_messages'));

        $m->connect('api/:method',
                    array('action' => 'api',
                          'apiaction' => 'direct_messages'),
                    array('method' => 'direct_messages(\.(xml|json|atom|rss))?'));

        // friendships

        $m->connect('api/friendships/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'friendships'),
                    array('method' => '(create|destroy)'));

        $m->connect('api/friendships/:method',
                    array('action' => 'api',
                          'apiaction' => 'friendships'),
                    array('method' => 'exists(\.(xml|json|rss|atom))'));

        // account

        $m->connect('api/account/:method',
                    array('action' => 'api',
                          'apiaction' => 'account'));

        // favorites

        $m->connect('api/favorites/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'favorites'));

        $m->connect('api/favorites/:argument',
                    array('action' => 'api',
                          'apiaction' => 'favorites',
                          'method' => 'favorites'));

        $m->connect('api/:method',
                    array('action' => 'api',
                          'apiaction' => 'favorites'),
                    array('method' => 'favorites(\.(xml|json|rss|atom))?'));

        // notifications

        $m->connect('api/notifications/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'favorites'));

        // blocks

        $m->connect('api/blocks/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'blocks'));

        // help

        $m->connect('api/help/:method',
                    array('action' => 'api',
                          'apiaction' => 'help'));

        // laconica

        $m->connect('api/laconica/:method',
                    array('action' => 'api',
                          'apiaction' => 'laconica'));

        // user stuff

        foreach (array('subscriptions', 'subscribers',
                       'nudge', 'xrds', 'all', 'foaf',
                       'replies', 'inbox', 'outbox', 'microsummary') as $a) {
            $m->connect(':nickname/'.$a,
                        array('action' => $a),
                        array('nickname' => '[a-zA-Z0-9]{1,64}'));
        }

        foreach (array('subscriptions', 'subscribers') as $a) {
            $m->connect(':nickname/'.$a.'/:tag',
                        array('action' => $a),
                        array('tag' => '[a-zA-Z0-9]+',
                              'nickname' => '[a-zA-Z0-9]{1,64}'));
        }

        foreach (array('rss', 'groups') as $a) {
            $m->connect(':nickname/'.$a,
                        array('action' => 'user'.$a),
                        array('nickname' => '[a-zA-Z0-9]{1,64}'));
        }

        foreach (array('all', 'replies', 'favorites') as $a) {
            $m->connect(':nickname/'.$a.'/rss',
                        array('action' => $a.'rss'),
                        array('nickname' => '[a-zA-Z0-9]{1,64}'));
        }

        $m->connect(':nickname/favorites',
                    array('action' => 'showfavorites'),
                    array('nickname' => '[a-zA-Z0-9]{1,64}'));

        $m->connect(':nickname/avatar/:size',
                    array('action' => 'avatarbynickname'),
                    array('size' => '(original|96|48|24)',
                          'nickname' => '[a-zA-Z0-9]{1,64}'));

        $m->connect(':nickname',
                    array('action' => 'showstream'),
                    array('nickname' => '[a-zA-Z0-9]{1,64}'));

        $this->m = $m;
    }

    function map($path)
    {
        return $this->m->match($path);
    }

    function build($action, $args=null, $fragment=null)
    {
        $action_arg = array('action' => $action);

        if ($args) {
            $args = array_merge($args, $action_arg);
        } else {
            $args = $action_arg;
        }

        return $this->m->generate($args, null, $fragment);
    }
}