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
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class FeedSubSettingsAction extends ConnectSettingsAction
{
    protected $profile_uri;
    protected $preview;
    protected $munger;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _m('Feed subscriptions');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _m('You can subscribe to feeds from other sites; ' .
                  'updates will appear in your personal timeline.');
    }

    /**
     * Content area of the page
     *
     * Shows a form for associating a Twitter account with this
     * StatusNet account. Also lets the user set preferences.
     *
     * @return void
     */

    function showContent()
    {
        $user = common_current_user();

        $profile = $user->getProfile();

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_feedsub',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('feedsubsettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'settings_twitter_login_button'));
        $this->input('profile_uri',
                     _m('Feed URL'),
                     $this->profile_uri,
                     _m('Enter the profile URL of a PubSubHubbub-enabled feed'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        if ($this->preview) {
            $this->submit('subscribe', _m('Subscribe'));
        } else {
            $this->submit('validate', _m('Continue'));
        }

        $this->elementEnd('fieldset');

        $this->elementEnd('form');

        if ($this->preview) {
            $this->previewFeed();
        }
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
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

        if ($this->arg('validate')) {
            $this->validateAndPreview();
        } else if ($this->arg('subscribe')) {
            $this->saveFeed();
        } else {
            $this->showForm(_('Unexpected form submission.'));
        }
    }

    /**
     * Set up and add a feed
     *
     * @return boolean true if feed successfully read
     * Sends you back to input form if not.
     */
    function validateFeed()
    {
        $profile_uri = trim($this->arg('profile_uri'));
        
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
            $err = _m('Invalid URL or could not reach server.');
        } catch (FeedSubBadResponseException $e) {
            $err = _m('Cannot read feed; server returned error.');
        } catch (FeedSubEmptyException $e) {
            $err = _m('Cannot read feed; server returned an empty page.');
        } catch (FeedSubBadHTMLException $e) {
            $err = _m('Bad HTML, could not find feed link.');
        } catch (FeedSubNoFeedException $e) {
            $err = _m('Could not find a feed linked from this URL.');
        } catch (FeedSubUnrecognizedTypeException $e) {
            $err = _m('Not a recognized feed type.');
        } catch (FeedSubException $e) {
            // Any new ones we forgot about
            $err = sprintf(_m('Bad feed URL: %s %s'), get_class($e), $e->getMessage());
        }

        $this->showForm($err);
        return false;
    }

    function saveFeed()
    {
        if ($this->validateFeed()) {
            $this->preview = true;

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
                } elseif (Group_member::join($this->profile->group_id, $user->id)) {
                    $this->showForm(_m('Joined remote group!'));
                } else {
                    $this->showForm(_m('Remote group join failed!'));
                }
            } else {
                $local = $this->oprofile->localProfile();
                if ($user->isSubscribed($local)) {
                    $this->showForm(_m('Already subscribed!'));
                } elseif ($this->oprofile->subscribeLocalToRemote($user)) {
                    $this->showForm(_m('Remote user subscribed!'));
                } else {
                    $this->showForm(_m('Remote subscription failed!'));
                }
            }
        }
    }

    function validateAndPreview()
    {
        if ($this->validateFeed()) {
            $this->preview = true;
            $this->showForm(_m('Previewing feed:'));
        }
    }

    function previewFeed()
    {
        $this->text('Profile preview should go here');
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('feedurl');
    }
}
