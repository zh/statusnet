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

require_once(INSTALLDIR.'/lib/accountsettingsaction.php');

class OthersettingsAction extends AccountSettingsAction
{

    function get_instructions()
    {
        return _('Manage various other options.');
    }

    function show_form($msg=null, $success=false)
    {
        $user = common_current_user();

        $this->form_header(_('Other Settings'), $msg, $success);

        $this->element('h2', null, _('URL Auto-shortening'));
        $this->elementStart('form', array('method' => 'post',
                                           'id' => 'othersettings',
                                           'action' =>
                                           common_local_url('othersettings')));
        $this->hidden('token', common_session_token());

        $services = array(
            '' => 'None',
            'ur1.ca' => 'ur1.ca (free service)',
            '2tu.us' => '2tu.us (free service)',
            'ptiturl.com' => 'ptiturl.com',
            'bit.ly' => 'bit.ly',
            'tinyurl.com' => 'tinyurl.com',
            'is.gd' => 'is.gd',
            'snipr.com' => 'snipr.com',
            'metamark.net' => 'metamark.net'
        );

        $this->dropdown('urlshorteningservice', _('Service'), $services, _('Automatic shortening service to use.'), false, $user->urlshorteningservice);

        $this->submit('save', _('Save'));

        $this->elementEnd('form');

//        $this->element('h2', null, _('Delete my account'));
//        $this->show_delete_form();

        common_show_footer();
    }

    function show_feeds_list($feeds)
    {
        $this->elementStart('div', array('class' => 'feedsdel'));
        $this->element('p', null, 'Feeds:');
        $this->elementStart('ul', array('class' => 'xoxo'));

        foreach ($feeds as $key => $value) {
            $this->common_feed_item($feeds[$key]);
        }
        $this->elementEnd('ul');
        $this->elementEnd('div');
    }

    //TODO move to common.php (and retrace its origin)
    function common_feed_item($feed)
    {
        $user = common_current_user();
        $nickname = $user->nickname;

        switch($feed['item']) {
            case 'notices': default:
                $feed_classname = $feed['type'];
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's ".$feed['version']." notice feed";
                $feed['textContent'] = "RSS";
                break;

            case 'foaf':
                $feed_classname = "foaf";
                $feed_mimetype = "application/".$feed['type']."+xml";
                $feed_title = "$nickname's FOAF file";
                $feed['textContent'] = "FOAF";
                break;
        }
        $this->elementStart('li');
        $this->element('a', array('href' => $feed['href'],
                                  'class' => $feed_classname,
                                  'type' => $feed_mimetype,
                                  'title' => $feed_title),
                            $feed['textContent']);
        $this->elementEnd('li');
    }

//    function show_delete_form() {
//        $user = common_current_user();
//      $notices = DB_DataObject::factory('notice');
//      $notices->profile_id = $user->id;
//      $notice_count = (int) $notices->count();
//
//        $this->elementStart('form', array('method' => 'POST',
//                                           'id' => 'delete',
//                                           'action' =>
//                                           common_local_url('deleteprofile')));
//
//        $this->hidden('token', common_session_token());
//      $this->element('p', null, "You can copy your notices and contacts by saving the two links below before deleting your account. Be careful, this operation cannot be undone.");
//
//        $this->show_feeds_list(array(0=>array('href'=>common_local_url('userrss', array('limit' => $notice_count, 'nickname' => $user->nickname)),
//                                              'type' => 'rss',
//                                              'version' => 'RSS 1.0',
//                                              'item' => 'notices'),
//                                     1=>array('href'=>common_local_url('foaf',array('nickname' => $user->nickname)),
//                                              'type' => 'rdf',
//                                              'version' => 'FOAF',
//                                              'item' => 'foaf')));
//
//        $this->submit('deleteaccount', _('Delete my account'));
//        $this->elementEnd('form');
//    }

    function handle_post()
    {

        # CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }

        if ($this->arg('save')) {
            $this->save_preferences();
        }else {
            $this->show_form(_('Unexpected form submission.'));
        }
    }

    function save_preferences()
    {

        $urlshorteningservice = $this->trimmed('urlshorteningservice');

        if (!is_null($urlshorteningservice) && strlen($urlshorteningservice) > 50) {
            $this->show_form(_('URL shortening service is too long (max 50 chars).'));
            return;
        }

        $user = common_current_user();

        assert(!is_null($user)); # should already be checked

        $user->query('BEGIN');

        $original = clone($user);

        $user->urlshorteningservice = $urlshorteningservice;

        $result = $user->update($original);

        if ($result === false) {
            common_log_db_error($user, 'UPDATE', __FILE__);
            $this->serverError(_('Couldn\'t update user.'));
            return;
        }

        $user->query('COMMIT');

        $this->show_form(_('Preferences saved.'), true);
    }
}
