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

require_once(INSTALLDIR.'/lib/stream.php');

class ShownoticeAction extends StreamAction
{

    var $notice = null;
    var $profile = null;
    var $avatar = null;

    function prepare($args)
    {

        parent::prepare($args);

        $id = $this->arg('notice');
        $this->notice = Notice::staticGet($id);

        if (!$this->notice) {
            $this->client_error(_('No such notice.'), 404);
            return false;
        }

        $this->profile = $this->notice->getProfile();

        if (!$this->profile) {
            $this->server_error(_('Notice has no profile'), 500);
            return false;
        }

        $this->avatar = $this->profile->getAvatar(AVATAR_STREAM_SIZE);

        return true;
    }

    function last_modified()
    {
        return max(strtotime($this->notice->created),
                   strtotime($this->profile->modified),
                   ($this->avatar) ? strtotime($this->avatar->modified) : 0);
    }

    function etag()
    {
        return 'W/"' . implode(':', array($this->arg('action'),
                                          common_language(),
                                          $this->notice->id,
                                          strtotime($this->notice->created),
                                          strtotime($this->profile->modified),
                                          ($this->avatar) ? strtotime($this->avatar->modified) : 0)) . '"';
    }

    function handle($args)
    {

        parent::handle($args);

        common_show_header(sprintf(_('%1$s\'s status on %2$s'),
                                   $this->profile->nickname,
                                   common_exact_date($this->notice->created)),
                           array($this, 'show_header'), null,
                           array($this, 'show_top'));

        $this->elementStart('ul', array('id' => 'notices'));
        $nli = new NoticeListItem($this->notice);
        $nli->show();
        $this->elementEnd('ul');

        common_show_footer();
    }

    function show_header()
    {

        $user = User::staticGet($this->profile->id);

        if (!$user) {
            return;
        }

        if ($user->emailmicroid && $user->email && $this->notice->uri) {
            $this->element('meta', array('name' => 'microid',
                                         'content' => "mailto+http:sha1:" . sha1(sha1('mailto:' . $user->email) . sha1($this->notice->uri))));
        }

        if ($user->jabbermicroid && $user->jabber && $this->notice->uri) {
            $this->element('meta', array('name' => 'microid',
                                         'content' => "xmpp+http:sha1:" . sha1(sha1('xmpp:' . $user->jabber) . sha1($this->notice->uri))));
        }
    }

    function show_top()
    {
        $cur = common_current_user();
        if ($cur && $cur->id == $this->profile->id) {
            common_notice_form();
        }
    }

    function no_such_notice()
    {
        common_user_error(_('No such notice.'));
    }
}
