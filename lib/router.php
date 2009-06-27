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
    static $inst = null;
    static $bare = array('requesttoken', 'accesstoken', 'userauthorization',
                         'postnotice', 'updateprofile', 'finishremotesubscribe',
                         'finishopenidlogin', 'finishaddopenid');

    static function get()
    {
        if (!Router::$inst) {
            Router::$inst = new Router();
        }
        return Router::$inst;
    }

    function __construct()
    {
        if (!$this->m) {
            $this->m = $this->initialize();
        }
    }

    function initialize()
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
                      'block', 'unblock', 'subedit',
                      'groupblock', 'groupunblock');

        foreach ($main as $a) {
            $m->connect('main/'.$a, array('action' => $a));
        }

        $m->connect('main/sup/:seconds', array('action' => 'sup'),
                    array('seconds' => '[0-9]+'));

        $m->connect('main/tagother/:id', array('action' => 'tagother'));

        // these take a code

        foreach (array('register', 'confirmaddress', 'recoverpassword') as $c) {
            $m->connect('main/'.$c.'/:code', array('action' => $c));
        }

        // exceptional

        $m->connect('main/openid', array('action' => 'openidlogin'));
        $m->connect('main/remote', array('action' => 'remotesubscribe'));
        $m->connect('main/remote?nickname=:nickname', array('action' => 'remotesubscribe'), array('nickname' => '[A-Za-z0-9_-]+'));

        foreach (Router::$bare as $action) {
            $m->connect('index.php?action=' . $action, array('action' => $action));
        }

        // settings

        foreach (array('profile', 'avatar', 'password', 'openid', 'im',
                       'email', 'sms', 'twitter', 'userdesign', 'other') as $s) {
            $m->connect('settings/'.$s, array('action' => $s.'settings'));
        }

        // search

        foreach (array('group', 'people', 'notice') as $s) {
            $m->connect('search/'.$s, array('action' => $s.'search'));
            $m->connect('search/'.$s.'?q=:q',
                        array('action' => $s.'search'),
                        array('q' => '.+'));
        }

        // The second of these is needed to make the link work correctly
        // when inserted into the page. The first is needed to match the
        // route on the way in. Seems to be another Net_URL_Mapper bug to me.
        $m->connect('search/notice/rss', array('action' => 'noticesearchrss'));
        $m->connect('search/notice/rss?q=:q', array('action' => 'noticesearchrss'),
                    array('q' => '.+'));

        $m->connect('attachment/:attachment',
                    array('action' => 'attachment'),
                    array('attachment' => '[0-9]+'));

        $m->connect('attachment/:attachment/ajax',
                    array('action' => 'attachment_ajax'),
                    array('attachment' => '[0-9]+'));

        $m->connect('attachment/:attachment/thumbnail',
                    array('action' => 'attachment_thumbnail'),
                    array('attachment' => '[0-9]+'));

        $m->connect('notice/new', array('action' => 'newnotice'));
        $m->connect('notice/new?replyto=:replyto',
                    array('action' => 'newnotice'),
                    array('replyto' => '[A-Za-z0-9_-]+'));

        $m->connect('notice/:notice/file',
            array('action' => 'file'),
            array('notice' => '[0-9]+'));

        $m->connect('notice/:notice',
                    array('action' => 'shownotice'),
                    array('notice' => '[0-9]+'));
        $m->connect('notice/delete', array('action' => 'deletenotice'));
        $m->connect('notice/delete/:notice',
                    array('action' => 'deletenotice'),
                    array('notice' => '[0-9]+'));

        // conversation

        $m->connect('conversation/:id',
                    array('action' => 'conversation'),
                    array('id' => '[0-9]+'));

        $m->connect('message/new', array('action' => 'newmessage'));
        $m->connect('message/new?to=:to', array('action' => 'newmessage'), array('to' => '[A-Za-z0-9_-]+'));
        $m->connect('message/:message',
                    array('action' => 'showmessage'),
                    array('message' => '[0-9]+'));

        $m->connect('user/:id',
                    array('action' => 'userbyid'),
                    array('id' => '[0-9]+'));

        $m->connect('tags/', array('action' => 'publictagcloud'));
        $m->connect('tag/', array('action' => 'publictagcloud'));
        $m->connect('tags', array('action' => 'publictagcloud'));
        $m->connect('tag', array('action' => 'publictagcloud'));
        $m->connect('tag/:tag/rss',
                    array('action' => 'tagrss'),
                    array('tag' => '[a-zA-Z0-9]+'));
        $m->connect('tag/:tag',
                    array('action' => 'tag'),
                    array('tag' => '[a-zA-Z0-9]+'));

        $m->connect('peopletag/:tag',
                    array('action' => 'peopletag'),
                    array('tag' => '[a-zA-Z0-9]+'));

        $m->connect('featured/', array('action' => 'featured'));
        $m->connect('featured', array('action' => 'featured'));
        $m->connect('favorited/', array('action' => 'favorited'));
        $m->connect('favorited', array('action' => 'favorited'));

        // groups

        $m->connect('group/new', array('action' => 'newgroup'));

        foreach (array('edit', 'join', 'leave') as $v) {
            $m->connect('group/:nickname/'.$v,
                        array('action' => $v.'group'),
                        array('nickname' => '[a-zA-Z0-9]+'));
        }

        foreach (array('members', 'logo', 'rss', 'designsettings') as $n) {
            $m->connect('group/:nickname/'.$n,
                        array('action' => 'group'.$n),
                        array('nickname' => '[a-zA-Z0-9]+'));
        }

        $m->connect('group/:nickname/blocked',
                    array('action' => 'blockedfromgroup'),
                    array('nickname' => '[a-zA-Z0-9]+'));

        $m->connect('group/:nickname/makeadmin',
                    array('action' => 'makeadmin'),
                    array('nickname' => '[a-zA-Z0-9]+'));

        $m->connect('group/:id/id',
                    array('action' => 'groupbyid'),
                    array('id' => '[0-9]+'));

        $m->connect('group/:nickname',
                    array('action' => 'showgroup'),
                    array('nickname' => '[a-zA-Z0-9]+'));

        $m->connect('group/', array('action' => 'groups'));
        $m->connect('group', array('action' => 'groups'));
        $m->connect('groups/', array('action' => 'groups'));
        $m->connect('groups', array('action' => 'groups'));

        // Twitter-compatible API

        // statuses API

        $m->connect('api/statuses/:method',
                    array('action' => 'api',
                          'apiaction' => 'statuses'),
                    array('method' => '(public_timeline|friends_timeline|user_timeline|update|replies|mentions|friends|followers|featured)(\.(atom|rss|xml|json))?'));

        $m->connect('api/statuses/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'statuses'),
                    array('method' => '(user_timeline|friends_timeline|replies|mentions|show|destroy|friends|followers)'));

        // users

        $m->connect('api/users/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'users'),
                    array('method' => 'show(\.(xml|json))?'));

        $m->connect('api/users/:method',
                    array('action' => 'api',
                          'apiaction' => 'users'),
                    array('method' => 'show(\.(xml|json))?'));

        // direct messages

        foreach (array('xml', 'json') as $e) {
            $m->connect('api/direct_messages/new.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'direct_messages',
                              'method' => 'create.'.$e));
        }

        foreach (array('xml', 'json', 'rss', 'atom') as $e) {
            $m->connect('api/direct_messages.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'direct_messages',
                              'method' => 'direct_messages.'.$e));
        }

        foreach (array('xml', 'json', 'rss', 'atom') as $e) {
            $m->connect('api/direct_messages/sent.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'direct_messages',
                              'method' => 'sent.'.$e));
        }

        $m->connect('api/direct_messages/destroy/:argument',
                    array('action' => 'api',
                          'apiaction' => 'direct_messages'));

        // friendships

        $m->connect('api/friendships/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'friendships'),
                    array('method' => '(create|destroy)'));

        $m->connect('api/friendships/:method',
                    array('action' => 'api',
                          'apiaction' => 'friendships'),
                    array('method' => 'exists(\.(xml|json))'));

        // Social graph

        $m->connect('api/friends/ids/:argument',
                    array('action' => 'api',
                          'apiaction' => 'statuses',
                          'method' => 'friendsIDs'));

        foreach (array('xml', 'json') as $e) {
            $m->connect('api/friends/ids.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'statuses',
                              'method' => 'friendsIDs.'.$e));
        }

        $m->connect('api/followers/ids/:argument',
                    array('action' => 'api',
                          'apiaction' => 'statuses',
                          'method' => 'followersIDs'));

        foreach (array('xml', 'json') as $e) {
            $m->connect('api/followers/ids.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'statuses',
                              'method' => 'followersIDs.'.$e));
        }

        // account

        $m->connect('api/account/:method',
                    array('action' => 'api',
                          'apiaction' => 'account'));

        // favorites

        $m->connect('api/favorites/:method/:argument',
                    array('action' => 'api',
                          'apiaction' => 'favorites',
                          array('method' => '(create|destroy)')));

        $m->connect('api/favorites/:argument',
                    array('action' => 'api',
                          'apiaction' => 'favorites',
                          'method' => 'favorites'));

        foreach (array('xml', 'json', 'rss', 'atom') as $e) {
            $m->connect('api/favorites.'.$e,
                        array('action' => 'api',
                              'apiaction' => 'favorites',
                              'method' => 'favorites.'.$e));
        }

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

        // search
        $m->connect('api/search.atom', array('action' => 'twitapisearchatom'));
        $m->connect('api/search.json', array('action' => 'twitapisearchjson'));
        $m->connect('api/trends.json', array('action' => 'twitapitrends'));

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

        $m->connect(':nickname/tag/:tag/rss',
            array('action' => 'userrss'),
            array('nickname' => '[a-zA-Z0-9]{1,64}'),
            array('tag' => '[a-zA-Z0-9]+'));

        $m->connect(':nickname/tag/:tag',
                    array('action' => 'showstream'),
                    array('nickname' => '[a-zA-Z0-9]{1,64}'),
                    array('tag' => '[a-zA-Z0-9]+'));

        $m->connect(':nickname',
                    array('action' => 'showstream'),
                    array('nickname' => '[a-zA-Z0-9]{1,64}'));

        Event::handle('RouterInitialized', array($m));

        return $m;
    }

    function map($path)
    {
        try {
            $match = $this->m->match($path);
        } catch (Net_URL_Mapper_InvalidException $e) {
            common_log(LOG_ERR, "Problem getting route for $path - " .
                       $e->getMessage());
            $cac = new ClientErrorAction("Page not found.", 404);
            $cac->showPage();
        }

        return $match;
    }

    function build($action, $args=null, $params=null, $fragment=null)
    {
        $action_arg = array('action' => $action);

        if ($args) {
            $args = array_merge($action_arg, $args);
        } else {
            $args = $action_arg;
        }

        $url = $this->m->generate($args, $params, $fragment);

        // Due to a bug in the Net_URL_Mapper code, the returned URL may
        // contain a malformed query of the form ?p1=v1?p2=v2?p3=v3. We
        // repair that here rather than modifying the upstream code...

        $qpos = strpos($url, '?');
        if ($qpos !== false) {
            $url = substr($url, 0, $qpos+1) .
              str_replace('?', '&', substr($url, $qpos+1));
        }
        return $url;
    }
}
