<?php
/**
 * Notice search action class.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
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

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/searchaction.php';

/**
 * Notice search action class.
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 * @todo     common parent for people and content search?
 */
class NoticesearchAction extends SearchAction
{

    function prepare($args)
    {
        parent::prepare($args);

        common_set_returnto($this->selfUrl());

        return true;
    }

    /**
     * Get instructions
     *
     * @return string instruction text
     */
    function getInstructions()
    {
        return _('Search for notices on %%site.name%% by their contents. Separate search terms by spaces; they must be 3 characters or more.');
    }

    /**
     * Get title
     *
     * @return string title
     */
    function title()
    {
        return _('Text search');
    }

    function getFeeds()
    {
        $q = $this->trimmed('q');

        if (!$q) {
            return null;
        }

        return array(new Feed(Feed::RSS1, common_local_url('noticesearchrss',
                                                           array('q' => $q)),
                              sprintf(_('Search results for "%s" on %s'),
                                      $q, common_config('site', 'name'))));
    }

    /**
     * Show results
     *
     * @param string  $q    search query
     * @param integer $page page number
     *
     * @return void
     */
    function showResults($q, $page)
    {
        $notice        = new Notice();
        $q             = strtolower($q);
        $search_engine = $notice->getSearchEngine('identica_notices');
        $search_engine->set_sort_mode('chron');
        // Ask for an extra to see if there's more.
        $search_engine->limit((($page-1)*NOTICES_PER_PAGE), NOTICES_PER_PAGE + 1);
        if (false === $search_engine->query($q)) {
            $cnt = 0;
        } else {
            $cnt = $notice->find();
        }
        if ($cnt > 0) {
            $terms = preg_split('/[\s,]+/', $q);
            $this->elementStart('ul', array('class' => 'notices'));
            for ($i = 0; $i < min($cnt, NOTICES_PER_PAGE); $i++) {
                if ($notice->fetch()) {
                    $this->showNotice($notice, $terms);
                } else {
                    // shouldn't happen!
                    break;
                }
            }
            $this->elementEnd('ul');
        } else {
            $this->element('p', 'error', _('No results'));
        }

        $this->pagination($page > 1, $cnt > NOTICES_PER_PAGE,
                          $page, 'noticesearch', array('q' => $q));
    }

    /**
     * Show notice
     *
     * @param class $notice notice
     * @param array $terms  terms to highlight
     *
     * @return void
     *
     * @todo refactor and combine with StreamAction::showNotice()
     */
    function showNotice($notice, $terms)
    {
        $profile = $notice->getProfile();
        if (!$profile) {
            common_log_db_error($notice, 'SELECT', __FILE__);
            $this->serverError(_('Notice without matching profile'));
            return;
        }
        // XXX: RDFa
        $this->elementStart('li', array('class' => 'hentry notice',
                                          'id' => 'notice-' . $notice->id));

        $this->elementStart('div', 'entry-title');
        $this->elementStart('span', 'vcard author');
        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        $this->elementStart('a', array('href' => $profile->profileurl,
                                       'class' => 'url'));
        $this->element('img', array('src' => ($avatar) ? $avatar->displayUrl() : Avatar::defaultImage(AVATAR_STREAM_SIZE),
                                    'class' => 'avatar photo',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($profile->fullname) ? $profile->fullname :
                                    $profile->nickname));
        $this->element('span', 'nickname fn', $profile->nickname);
        $this->elementEnd('a');
        $this->elementEnd('span');

        // FIXME: URL, image, video, audio
        $this->elementStart('p', array('class' => 'entry-content'));
        if ($notice->rendered) {
            $this->raw($this->highlight($notice->rendered, $terms));
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->raw($this->highlight(common_render_content($notice->content, $notice), $terms));
        }
        $this->elementEnd('p');
        $this->elementEnd('div');

        $noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
        $this->elementStart('div', 'entry-content');
        $this->elementStart('dl', 'timestamp');
        $this->element('dt', null, _('Published'));
        $this->elementStart('dd', null);
        $this->elementStart('a', array('rel' => 'bookmark',
                                       'href' => $noticeurl));
        $dt = common_date_iso8601($notice->created);
        $this->element('abbr', array('class' => 'published',
                                          'title' => $dt),
                            common_date_string($notice->created));
        $this->elementEnd('a');
        $this->elementEnd('dd');
        $this->elementEnd('dl');

        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice',
                                         array('notice' => $notice->reply_to));
            $this->elementStart('dl', 'response');
            $this->element('dt', null, _('To'));
            $this->elementStart('dd');
            $this->element('a', array('href' => $replyurl,
                                           'rel' => 'in-reply-to'),
                                _('in reply to'));
            $this->elementEnd('dd');
            $this->elementEnd('dl');
        }
        $this->elementEnd('div');

        $this->elementStart('div', 'notice-options');

        $reply_url = common_local_url('newnotice',
                                      array('replyto' => $profile->nickname));

        $this->elementStart('dl', 'notice_reply');
        $this->element('dt', null, _('Reply to this notice'));
        $this->elementStart('dd');
        $this->elementStart('a', array('href' => $reply_url,
                                       'title' => _('Reply to this notice')));
        $this->text(_('Reply'));
        $this->element('span', 'notice_id', $notice->id);
        $this->elementEnd('a');
        $this->elementEnd('dd');
        $this->elementEnd('dl');
        $this->elementEnd('div');
        $this->elementEnd('li');
    }

    /**
     * Highlist query terms
     *
     * @param string $text  notice text
     * @param array  $terms terms to highlight
     *
     * @return void
     */
    function highlight($text, $terms)
    {
        /* Highligh serach terms */
        $pattern = '/('.implode('|', array_map('htmlspecialchars', $terms)).')/i';
        $result  = preg_replace($pattern, '<strong>\\1</strong>', $text);

        /* Remove highlighting from inside links, loop incase multiple highlights in links */
        $pattern = '/(href="[^"]*)<strong>('.implode('|', array_map('htmlspecialchars', $terms)).')<\/strong>([^"]*")/iU';
        do {
            $result = preg_replace($pattern, '\\1\\2\\3', $result, -1, $count);
        } while ($count);
        return $result;
    }

    function isReadOnly()
    {
        return true;
    }
}

