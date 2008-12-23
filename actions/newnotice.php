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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('LACONICA')) { exit(1); }

require_once INSTALLDIR . '/lib/noticelist.php';

class NewnoticeAction extends Action {

    function handle($args) {
        parent::handle($args);

        if (!common_logged_in()) {
            common_user_error(_('Not logged in.'));
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            # CSRF protection - token set in common_notice_form()
            $token = $this->trimmed('token');
            if (!$token || $token != common_session_token()) {
                $this->client_error(_('There was a problem with your session token. Try again, please.'));
                return;
            }

            $this->save_new_notice();
        } else {
            $this->show_form();
        }
    }

    function save_new_notice() {

        $user = common_current_user();
        assert($user); # XXX: maybe an error instead...
        $content = $this->trimmed('status_textarea');

        if (!$content) {
            $this->show_form(_('No content!'));
            return;
        } else {
            $content_shortened = common_shorten_links($content);

            if (mb_strlen($content_shortened) > 140) {
                common_debug("Content = '$content_shortened'", __FILE__);
                common_debug("mb_strlen(\$content) = " . mb_strlen($content_shortened), __FILE__);
                $this->show_form(_('That\'s too long. Max notice size is 140 chars.'));
                return;
            }
        }

        $inter = new CommandInterpreter();

        $cmd = $inter->handle_command($user, $content_shortened);

        if ($cmd) {
            if ($this->boolean('ajax')) {
                $cmd->execute(new AjaxWebChannel());
            } else {
                $cmd->execute(new WebChannel());
            }
            return;
        }

        $replyto = $this->trimmed('inreplyto');

        $notice = Notice::saveNew($user->id, $content, 'web', 1, ($replyto == 'false') ? null : $replyto);

        if (is_string($notice)) {
            $this->show_form($notice);
            return;
        }

        common_broadcast_notice($notice);

        if ($this->boolean('ajax')) {
            common_start_html('text/xml;charset=utf-8', true);
            common_element_start('head');
            common_element('title', null, _('Notice posted'));
            common_element_end('head');
            common_element_start('body');
            $this->show_notice($notice);
            common_element_end('body');
            common_element_end('html');
        } else {
            $returnto = $this->trimmed('returnto');

            if ($returnto) {
                $url = common_local_url($returnto,
                                        array('nickname' => $user->nickname));
            } else {
                $url = common_local_url('shownotice',
                                        array('notice' => $notice->id));
            }
            common_redirect($url, 303);
        }
    }

    function ajax_error_msg($msg) {
        common_start_html('text/xml;charset=utf-8', true);
        common_element_start('head');
        common_element('title', null, _('Ajax Error'));
        common_element_end('head');
        common_element_start('body');
        common_element('p', array('id' => 'error'), $msg);
        common_element_end('body');
        common_element_end('html');
    }

    function show_top($content=null) {
        common_notice_form(null, $content);
    }

    function show_form($msg=null) {
        if ($msg && $this->boolean('ajax')) {
            $this->ajax_error_msg($msg);
            return;
        }
        $content = $this->trimmed('status_textarea');
        if (!$content) {
            $replyto = $this->trimmed('replyto');
            $profile = Profile::staticGet('nickname', $replyto);
            if ($profile) {
                $content = '@' . $profile->nickname . ' ';
            }
        }
        common_show_header(_('New notice'), null, $content,
                           array($this, 'show_top'));
        if ($msg) {
            common_element('p', array('id' => 'error'), $msg);
        }
        common_show_footer();
    }

    function show_notice($notice) {
        $nli = new NoticeListItem($notice);
        $nli->show();
    }

}
