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
    protected $feedurl;
    protected $preview;
    protected $munger;

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return dgettext('FeedSubPlugin', 'Feed subscriptions');
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return dgettext('FeedSubPlugin',
                        'You can subscribe to feeds from other sites; ' .
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

        $fuser = null;

        $flink = Foreign_link::getByUserID($user->id, FEEDSUB_SERVICE);

        if (!empty($flink)) {
            $fuser = $flink->getForeignUser();
        }

        $this->elementStart('form', array('method' => 'post',
                                          'id' => 'form_settings_feedsub',
                                          'class' => 'form_settings',
                                          'action' =>
                                          common_local_url('feedsubsettings')));

        $this->hidden('token', common_session_token());

        $this->elementStart('fieldset', array('id' => 'settings_feeds'));

        $this->elementStart('ul', 'form_data');
        $this->elementStart('li', array('id' => 'settings_twitter_login_button'));
        $this->input('feedurl', _('Feed URL'), $this->feedurl, _('Enter the URL of a PubSubHubbub-enabled feed'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        if ($this->preview) {
            $this->submit('subscribe', dgettext('FeedSubPlugin', 'Subscribe'));
        } else {
            $this->submit('validate', dgettext('FeedSubPlugin', 'Continue'));
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
        $feedurl = trim($this->arg('feedurl'));
        
        if ($feedurl == '') {
            $this->showForm(dgettext('FeedSubPlugin',
                                     'Empty feed URL!'));
            return;
        }
        $this->feedurl = $feedurl;
        
        // Get the canonical feed URI and check it
        try {
            $discover = new FeedDiscovery();
            $uri = $discover->discoverFromURL($feedurl);
        } catch (FeedSubBadURLException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Invalid URL or could not reach server.'));
            return false;
        } catch (FeedSubBadResponseException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Cannot read feed; server returned error.'));
            return false;
        } catch (FeedSubEmptyException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Cannot read feed; server returned an empty page.'));
            return false;
        } catch (FeedSubBadHTMLException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Bad HTML, could not find feed link.'));
            return false;
        } catch (FeedSubNoFeedException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Could not find a feed linked from this URL.'));
            return false;
        } catch (FeedSubUnrecognizedTypeException $e) {
            $this->showForm(dgettext('FeedSubPlugin', 'Not a recognized feed type.'));
            return false;
        } catch (FeedSubException $e) {
            // Any new ones we forgot about
            $this->showForm(dgettext('FeedSubPlugin', 'Bad feed URL.'));
            return false;
        }
        
        $this->munger = $discover->feedMunger();
        $this->feedinfo = $this->munger->feedInfo();

        if ($this->feedinfo->huburi == '') {
            $this->showForm(dgettext('FeedSubPlugin', 'Feed is not PuSH-enabled; cannot subscribe.'));
            return false;
        }
        
        return true;
    }

    function saveFeed()
    {
        if ($this->validateFeed()) {
            $this->preview = true;
            $this->feedinfo = Feedinfo::ensureProfile($this->munger);

            // If not already in use, subscribe to updates via the hub
            if ($this->feedinfo->sub_start) {
                common_log(LOG_INFO, __METHOD__ . ": double the fun! new sub for {$this->feedinfo->feeduri} last subbed {$this->feedinfo->sub_start}");
            } else {
                $ok = $this->feedinfo->subscribe();
                common_log(LOG_INFO, __METHOD__ . ": sub was $ok");
                if (!$ok) {
                    $this->showForm(dgettext('FeedSubPlugin', 'Feed subscription failed! Bad response from hub.'));
                    return;
                }
            }
            
            // And subscribe the current user to the local profile
            $user = common_current_user();
            $profile = $this->feedinfo->getProfile();
            
            if ($user->isSubscribed($profile)) {
                $this->showForm(dgettext('FeedSubPlugin', 'Already subscribed!'));
            } elseif ($user->subscribeTo($profile)) {
                $this->showForm(dgettext('FeedSubPlugin', 'Feed subscribed!'));
            } else {
                $this->showForm(dgettext('FeedSubPlugin', 'Feed subscription failed!'));
            }
        }
    }

    function validateAndPreview()
    {
        if ($this->validateFeed()) {
            $this->preview = true;
            $this->showForm(dgettext('FeedSubPlugin', 'Previewing feed:'));
        }
    }

    function previewFeed()
    {
        $feedinfo = $this->munger->feedinfo();
        $notice = $this->munger->notice(0, true); // preview

        if ($notice) {
            $this->element('b', null, 'Preview of latest post from this feed:');

            $item = new NoticeList($notice, $this);
            $item->show();
        } else {
            $this->element('b', null, 'No posts in this feed yet.');
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('feedurl');
    }
}
