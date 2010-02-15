<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class OStatusSubAction extends Action
{

    protected $feedurl;
    
    function title()
    {
        return _m("OStatus Subscribe");
    }

    function handle($args)
    {
        if ($this->validateFeed()) {
            $this->showForm();
        }

        return true;

    }

    function showForm($err = null)
    {
        $this->err = $err;
        $this->showPage();
    }


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
        $this->elementStart('li');
        $this->input('feedurl', _('Feed URL'), $this->feedurl, _('Enter the URL of a PubSubHubbub-enabled feed'));
        $this->elementEnd('li');
        $this->elementEnd('ul');

        $this->submit('subscribe', _m('Subscribe'));

        $this->elementEnd('fieldset');

        $this->elementEnd('form');

        $this->previewFeed();
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

        if ($this->arg('subscribe')) {
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
        $feedurl = $this->trimmed('feed');
        
        if ($feedurl == '') {
            $this->showForm(_m('Empty feed URL!'));
            return;
        }
        $this->feedurl = $feedurl;
        
        // Get the canonical feed URI and check it
        try {
            $discover = new FeedDiscovery();
            $uri = $discover->discoverFromURL($feedurl);
        } catch (FeedSubBadURLException $e) {
            $this->showForm(_m('Invalid URL or could not reach server.'));
            return false;
        } catch (FeedSubBadResponseException $e) {
            $this->showForm(_m('Cannot read feed; server returned error.'));
            return false;
        } catch (FeedSubEmptyException $e) {
            $this->showForm(_m('Cannot read feed; server returned an empty page.'));
            return false;
        } catch (FeedSubBadHTMLException $e) {
            $this->showForm(_m('Bad HTML, could not find feed link.'));
            return false;
        } catch (FeedSubNoFeedException $e) {
            $this->showForm(_m('Could not find a feed linked from this URL.'));
            return false;
        } catch (FeedSubUnrecognizedTypeException $e) {
            $this->showForm(_m('Not a recognized feed type.'));
            return false;
        } catch (FeedSubException $e) {
            // Any new ones we forgot about
            $this->showForm(_m('Bad feed URL.'));
            return false;
        }
        
        $this->munger = $discover->feedMunger();
        $this->profile = $this->munger->ostatusProfile();

        if ($this->profile->huburi == '') {
            $this->showForm(_m('Feed is not PuSH-enabled; cannot subscribe.'));
            return false;
        }
        
        return true;
    }

    function saveFeed()
    {
        if ($this->validateFeed()) {
            $this->preview = true;
            $this->profile = Ostatus_profile::ensureProfile($this->munger);

            // If not already in use, subscribe to updates via the hub
            if ($this->profile->sub_start) {
                common_log(LOG_INFO, __METHOD__ . ": double the fun! new sub for {$this->profile->feeduri} last subbed {$this->profile->sub_start}");
            } else {
                $ok = $this->profile->subscribe();
                common_log(LOG_INFO, __METHOD__ . ": sub was $ok");
                if (!$ok) {
                    $this->showForm(_m('Feed subscription failed! Bad response from hub.'));
                    return;
                }
            }
            
            // And subscribe the current user to the local profile
            $user = common_current_user();
            $profile = $this->profile->getProfile();
            
            if ($user->isSubscribed($profile)) {
                $this->showForm(_m('Already subscribed!'));
            } elseif ($user->subscribeTo($profile)) {
                $this->showForm(_m('Feed subscribed!'));
            } else {
                $this->showForm(_m('Feed subscription failed!'));
            }
        }
    }

    
    function previewFeed()
    {
        $profile = $this->munger->ostatusProfile();
        $notice = $this->munger->notice(0, true); // preview

        if ($notice) {
            $this->element('b', null, 'Preview of latest post from this feed:');

            $item = new NoticeList($notice, $this);
            $item->show();
        } else {
            $this->element('b', null, 'No posts in this feed yet.');
        }
    }


}
