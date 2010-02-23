<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

/**
 * Key UI methods:
 *
 *  showInputForm() - form asking for a remote profile account or URL
 *                    We end up back here on errors
 *
 *  showPreviewForm() - surrounding form for preview-and-confirm
 *    previewUser() - display profile for a remote user
 *    previewGroup() - display profile for a remote group
 *
 *  successUser() - redirects to subscriptions page on subscribe
 *  successGroup() - redirects to groups page on join
 */
class OStatusSubAction extends Action
{
    protected $profile_uri; // provided acct: or URI of remote entity
    protected $oprofile; // Ostatus_profile of remote entity, if valid

    /**
     * Show the initial form, when we haven't yet been given a valid
     * remote profile.
     */
    function showInputForm()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('ostatussub')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li');
        $this->input('profile',
                     _m('Address or profile URL'),
                     $this->profile_uri,
                     _m('Enter the profile URL of a PubSubHubbub-enabled feed'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->submit('validate', _m('Continue'));

        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Show the preview-and-confirm form. We've got a valid remote
     * profile and are ready to poke it!
     *
     * This controls the wrapper form; actual profile display will
     * be in previewUser() or previewGroup() depending on the type.
     */
    function showPreviewForm()
    {
        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_ostatus_sub',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('ostatussub')));

        $this->hidden('token', common_session_token());
        $this->hidden('profile', $this->profile_uri);

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        if ($this->oprofile->isGroup()) {
            $this->previewGroup();
            $this->submit('subscribe', _m('Join'));
        } else {
            $this->previewUser();
            $this->submit('subscribe', _m('Subscribe'));
        }


        $this->elementEnd('fieldset');

        $this->elementEnd('form');
    }

    /**
     * Show a preview for a remote user's profile
     */
    function previewUser()
    {
        $oprofile = $this->oprofile;
        $profile = $oprofile->localProfile();

        $this->text(sprintf(_m("Remote user %s"), $profile->nickname));
        // ...
    }

    /**
     * Show a preview for a remote group's profile
     */
    function previewGroup()
    {
        $oprofile = $this->oprofile;
        $group = $oprofile->localGroup();

        $this->text(sprintf(_m("Remote group %s"), $group->nickname));
        // ..
    }

    /**
     * Redirect on successful remote user subscription
     */
    function successUser()
    {
        $cur = common_current_user();
        $url = common_local_url('subscriptions', array('nickname' => $cur->nickname));
        common_redirect($url, 303);
    }

    /**
     * Redirect on successful remote group join
     */
    function successGroup()
    {
        $cur = common_current_user();
        $url = common_local_url('usergroups', array('nickname' => $cur->nickname));
        common_redirect($url, 303);
    }

    /**
     * Pull data for a remote profile and check if it's valid.
     * Fills out error UI string in $this->error
     * Fills out $this->oprofile on success.
     *
     * @return boolean
     */
    function validateFeed()
    {
        $profile_uri = trim($this->arg('profile'));

        if ($profile_uri == '') {
            $this->showForm(_m('Empty remote profile URL!'));
            return;
        }
        $this->profile_uri = $profile_uri;

        // @fixme validate, normalize bla bla
        try {
            $oprofile = Ostatus_profile::ensureProfile($this->profile_uri);
            $this->oprofile = $oprofile;
            return true;
        } catch (FeedSubBadURLException $e) {
            $this->error = _m('Invalid URL or could not reach server.');
        } catch (FeedSubBadResponseException $e) {
            $this->error = _m('Cannot read feed; server returned error.');
        } catch (FeedSubEmptyException $e) {
            $this->error = _m('Cannot read feed; server returned an empty page.');
        } catch (FeedSubBadHTMLException $e) {
            $this->error = _m('Bad HTML, could not find feed link.');
        } catch (FeedSubNoFeedException $e) {
            $this->error = _m('Could not find a feed linked from this URL.');
        } catch (FeedSubUnrecognizedTypeException $e) {
            $this->error = _m('Not a recognized feed type.');
        } catch (FeedSubException $e) {
            // Any new ones we forgot about
            $this->error = sprintf(_m('Bad feed URL: %s %s'), get_class($e), $e->getMessage());
        }

        return false;
    }

    /**
     * Attempt to finalize subscription.
     * validateFeed must have been run first.
     *
     * Calls showForm on failure or successUser/successGroup on success.
     */
    function saveFeed()
    {
        // And subscribe the current user to the local profile
        $user = common_current_user();

        if (!$this->oprofile->subscribe()) {
            $this->showForm(_m("Failed to set up server-to-server subscription."));
            return;
        }

        if ($this->oprofile->isGroup()) {
            $group = $this->oprofile->localGroup();
            if ($user->isMember($group)) {
                $this->showForm(_m('Already a member!'));
            } elseif (Group_member::join($this->oprofile->group_id, $user->id)) {
                $this->successGroup();
            } else {
                $this->showForm(_m('Remote group join failed!'));
            }
        } else {
            $local = $this->oprofile->localProfile();
            if ($user->isSubscribed($local)) {
                $this->showForm(_m('Already subscribed!'));
            } elseif ($this->oprofile->subscribeLocalToRemote($user)) {
                $this->successUser();
            } else {
                $this->showForm(_m('Remote subscription failed!'));
            }
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            // XXX: selfURL() didn't work. :<
            common_set_returnto($_SERVER['REQUEST_URI']);
            if (Event::handle('RedirectToLogin', array($this, null))) {
                common_redirect(common_local_url('login'), 303);
            }
            return false;
        }

        $this->profile_uri = $this->arg('profile');

        return true;
    }

    /**
     * Handle the submission.
     */
    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
        } else {
            if ($this->arg('profile')) {
                $this->validateFeed();
            }
            $this->showForm();
        }
    }


    /**
     * Handle posts to this form
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

        if ($this->validateFeed()) {
            if ($this->arg('subscribe')) {
                $this->saveFeed();
                return;
            }
        }
        $this->showForm();
    }

    /**
     * Show the appropriate form based on our input state.
     */
    function showForm($err=null)
    {
        if ($err) {
            $this->error = $err;
        }
        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            $this->element('title', null, _m('Subscribe to user'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showContent();
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            $this->showPage();
        }
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _m('Authorize subscription');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _m('You can subscribe to users from other supported sites. Paste their address or profile URI below:');
    }

    function showPageNotice()
    {
        if ($this->error) {
            $this->element('p', 'error', $this->error);
        }
    }

    /**
     * Content area of the page
     *
     * Shows a form for associating a remote OStatus account with this
     * StatusNet account.
     *
     * @return void
     */

    function showContent()
    {
        if ($this->oprofile) {
            $this->showPreviewForm();
        } else {
            $this->showInputForm();
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('feedurl');
    }
}
