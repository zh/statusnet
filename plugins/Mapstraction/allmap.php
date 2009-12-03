<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Show a map of user's friends' notices
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
 * @category  Mapstraction
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Show a map of user's notices
 *
 * @category Mapstraction
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class AllmapAction extends OwnerDesignAction
{
    var $profile = null;
    var $page    = null;
    var $notices = null;

    public $plugin  = null;

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname     = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url($this->trimmed('action'), $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->profile = $this->user->getProfile();

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $page = $this->trimmed('page');

        if (!empty($page) && Validate::number($page)) {
            $this->page = $page+0;
        } else {
            $this->page = 1;
        }

        return true;
    }

    function title()
    {
        if (!empty($this->profile->fullname)) {
            $base = $this->profile->fullname . ' (' . $this->user->nickname . ') ';
        } else {
            $base = $this->user->nickname;
        }

        if ($this->page == 1) {
            return sprintf(_("%s friends map"),
                           $base);
        } else {
            return sprintf(_("%s friends map, page %d"),
                           $base,
                           $this->page);
        }
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showContent()
    {
        $this->element('div', array('id' => 'map_canvas',
                                    'class' => 'gray smallmap',
                                    'style' => "width: 100%; height: 400px"));
    }

    function showScripts()
    {
        parent::showScripts();
        $cur = common_current_user();
        if (!empty($cur) && $cur->id == $this->user->id) {
            $notice = $this->user->noticeInbox(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        } else {
            $notice = $this->user->noticesWithFriends(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);
        }

        $jsonArray = array();

        while ($notice->fetch()) {
            if (!empty($notice->lat) && !empty($notice->lon)) {
                $jsonNotice = $this->noticeAsJson($notice);
                $jsonArray[] = $jsonNotice;
            }
        }

        $this->elementStart('script', array('type' => 'text/javascript'));
        $this->raw('/*<![CDATA[*/'); // XHTML compat for Safari
        $this->raw('var _notices = ' . json_encode($jsonArray).'; ');
        $this->raw('showMapstraction($("#map_canvas"),_notices);');
        $this->raw('/*]]>*/'); // XHTML compat for Safari
        $this->elementEnd('script');

        return true;
    }

    function noticeAsJson($notice)
    {
        // FIXME: this code should be abstracted to a neutral third
        // party, like Notice::asJson(). I'm not sure of the ethics
        // of refactoring from within a plugin, so I'm just abusing
        // the ApiAction method. Don't do this unless you're me!

        require_once(INSTALLDIR.'/lib/api.php');

        $act = new ApiAction('/dev/null');

        $arr = $act->twitterStatusArray($notice, true);
        $arr['url'] = $notice->bestUrl();
        $arr['html'] = $notice->rendered;
        $arr['source'] = $arr['source'];

        if (!empty($notice->reply_to)) {
            $reply_to = Notice::staticGet('id', $notice->reply_to);
            if (!empty($reply_to)) {
                $arr['in_reply_to_status_url'] = $reply_to->bestUrl();
            }
            $reply_to = null;
        }

        $profile = $notice->getProfile();
        $arr['user']['profile_url'] = $profile->profileurl;

        return $arr;
    }
}
