<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 *
 * @category Actions
 * @package  Actions
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 * @link     http://status.net
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/profileminilist.php';
require_once INSTALLDIR.'/lib/peopletaglist.php';
require_once INSTALLDIR.'/lib/noticelist.php';
require_once INSTALLDIR.'/lib/feedlist.php';

class ShowprofiletagAction extends Action
{
    var $notice, $tagger, $peopletag;

    function isReadOnly($args)
    {
        return true;
    }

    function prepare($args)
    {
        parent::prepare($args);

        $tagger_arg = $this->arg('tagger');
        $tag_arg = $this->arg('tag');
        $tagger = common_canonical_nickname($tagger_arg);
        $tag = common_canonical_tag($tag_arg);

        // Permanent redirect on non-canonical nickname

        if ($tagger_arg != $tagger || $tag_arg != $tag) {
            $args = array('tagger' => $nickname, 'tag' => $tag);
            if ($this->page != 1) {
                $args['page'] = $this->page;
            }
            common_redirect(common_local_url('showprofiletag', $args), 301);
            return false;
        }

        if (!$tagger) {
            $this->clientError(_('No tagger.'), 404);
            return false;
        }

        $user = User::staticGet('nickname', $tagger);

        if (!$user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->tagger = $user->getProfile();
        $this->peopletag = Profile_list::pkeyGet(array('tagger' => $user->id, 'tag' => $tag));

        $current = common_current_user();
        $can_see = !empty($this->peopletag) && (!$this->peopletag->private ||
                   ($this->peopletag->private && $this->peopletag->tagger === $current->id));

        if (!$can_see) {
            $this->clientError(_('No such peopletag.'), 404);
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        $this->notice = $this->peopletag->getNotices(($this->page-1)*NOTICES_PER_PAGE, NOTICES_PER_PAGE + 1);

        if ($this->page > 1 && $this->notice->N == 0) {
            // TRANS: Server error when page not found (404)
            $this->serverError(_('No such page.'), $code = 404);
        }

        return true;
    }

    function handle($args)
    {
        parent::handle($args);

        if (!$this->peopletag) {
            $this->clientError(_('No such user.'));
            return;
        }

        $this->showPage();
    }

    function title()
    {
        if ($this->page > 1) {

            if($this->peopletag->private) {
                return sprintf(_('Private timeline for people tagged %s by you, page %d'),
                                $this->peopletag->tag, $this->page);
            }

            $current = common_current_user();
            if (!empty($current) && $current->id == $this->peopletag->tagger) {
                return sprintf(_('Timeline for people tagged %s by you, page %d'),
                                $this->peopletag->tag, $this->page);
            }

            // TRANS: Page title. %1$s is user nickname, %2$d is page number
            return sprintf(_('Timeline for people tagged %1$s by %2$s, page %3$d'),
                                $this->peopletag->tag,
                                $this->tagger->nickname,
                                $this->page
                          );
        } else {

            if($this->peopletag->private) {
                return sprintf(_('Private timeline of people tagged %s by you'),
                                $this->peopletag->tag, $this->page);
            }

            $current = common_current_user();
            if (!empty($current) && $current->id == $this->peopletag->tagger) {
                return sprintf(_('Timeline for people tagged %s by you'),
                                $this->peopletag->tag, $this->page);
            }

            // TRANS: Page title. %1$s is user nickname, %2$d is page number
            return sprintf(_('Timeline for people tagged %1$s by %2$s'),
                                $this->peopletag->tag,
                                $this->tagger->nickname, 
                                $this->page
                          );
        }
    }

    function getFeeds()
    {
        #XXX: make these actually work
        return array(new Feed(Feed::RSS2,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->tagger->id,
                        'id' => $this->peopletag->id,
                        'format' => 'rss'
                    )
                ),
            // TRANS: %1$s is user nickname
                sprintf(_('Feed for friends of %s (RSS 2.0)'), $this->tagger->nickname)),
            new Feed(Feed::ATOM,
                common_local_url(
                    'ApiTimelineList', array(
                        'user' => $this->tagger->id,
                        'id' => $this->peopletag->id,
                        'format' => 'atom'
                    )
                ),
                // TRANS: %1$s is user nickname
                sprintf(_('Feed for people tagged %s by %s (Atom)'),
                            $this->peopletag->tag, $this->tagger->nickname
                       )
              )
        );
    }

    function showLocalNav()
    {
        $nav = new PeopletagGroupNav($this);
        $nav->show();
    }

    function showEmptyListMessage()
    {
        // TRANS: %1$s is user nickname
        $message = sprintf(_('This is the timeline for people tagged %s by %s but no one has posted anything yet.'), $this->peopletag->tag, $this->tagger->nickname) . ' ';

        if (common_logged_in()) {
            $current_user = common_current_user();
            if ($this->tagger->id == $current_user->id) {
                $message .= _('Try tagging more people.');
            }
        } else {
            $message .= _('Why not [register an account](%%%%action.register%%%%) and start following this timeline.');
        }

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showContent()
    {
        $this->showPeopletag();
        $this->showNotices();
    }

    function showPeopletag()
    {
        $cur = common_current_user();
        $tag = new Peopletag($this->peopletag, $cur, $this);
        $tag->show();
    }

    function showNotices()
    {
        if (Event::handle('StartShowProfileTagContent', array($this))) {
            $nl = new NoticeList($this->notice, $this);

            $cnt = $nl->show();

            if (0 == $cnt) {
                $this->showEmptyListMessage();
            }

            $this->pagination(
                $this->page > 1, $cnt > NOTICES_PER_PAGE,
                $this->page, 'showprofiletag', array('tag' => $this->peopletag->tag,
                                                     'tagger' => $this->tagger->nickname)
            );

            Event::handle('EndShowProfileTagContent', array($this));
        }
    }

    function showSections()
    {
        $this->showTagged();
        if (!$this->peopletag->private) {
            $this->showSubscribers();
        }
        # $this->showStatistics();
    }

    function showPageTitle()
    {
        $this->element('h1', null, $this->title());
    }

    function showTagged()
    {
        $profile = $this->peopletag->getTagged(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_tagged',
                                         'class' => 'section'));
        if (Event::handle('StartShowTaggedProfilesMiniList', array($this))) {

            $title = '';

            $current = common_current_user();
            if(!empty($current) && $this->peopletag->tagger == $current->id) {
                $title =  sprintf(_('People tagged %s by you'), $this->peopletag->tag);
            } else {
                $title = sprintf(_('People tagged %1$s by %2$s'),
                                $this->peopletag->tag,
                                $this->tagger->nickname);
            }

            $this->element('h2', null, $title);

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('taggedprofiles',
                                                                     array('nickname' => $this->tagger->nickname,
                                                                           'profiletag' => $this->peopletag->tag)),
                                          'class' => 'more'),
                               _('Show all'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowTaggedProfilesMiniList', array($this));
        }
        $this->elementEnd('div');
    }

    function showSubscribers()
    {
        $profile = $this->peopletag->getSubscribers(0, PROFILES_PER_MINILIST + 1);

        $this->elementStart('div', array('id' => 'entity_subscribers',
                                         'class' => 'section'));
        if (Event::handle('StartShowProfileTagSubscribersMiniList', array($this))) {
            $this->element('h2', null, _('Subscribers'));

            $cnt = 0;

            if (!empty($profile)) {
                $pml = new ProfileMiniList($profile, $this);
                $cnt = $pml->show();
                if ($cnt == 0) {
                    $this->element('p', null, _('(None)'));
                }
            }

            if ($cnt > PROFILES_PER_MINILIST) {
                $this->elementStart('p');
                $this->element('a', array('href' => common_local_url('profiletagsubscribers',
                                                                     array('nickname' => $this->tagger->nickname,
                                                                           'profiletag' => $this->peopletag->tag)),
                                          'class' => 'more'),
                               _('All subscribers'));
                $this->elementEnd('p');
            }

            Event::handle('EndShowProfileTagSubscribersMiniList', array($this));
        }
        $this->elementEnd('div');
    }
}

class Peopletag extends PeopletagListItem
{
    function showStart()
    {
        $mode = $this->peopletag->private ? 'private' : 'public';
        $this->out->elementStart('div', array('class' => 'hentry peopletag peopletag-profile mode-'.$mode,
                                             'id' => 'peopletag-' . $this->peopletag->id));
    }

    function showEnd()
    {
        $this->out->elementEnd('div');
    }

    function showAvatar()
    {
        parent::showAvatar(AVATAR_PROFILE_SIZE);
    }
}
