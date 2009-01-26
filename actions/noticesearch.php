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
            $this->elementStart('ul', array('id' => 'notices'));
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
     * Show header
     *
     * @param array $arr array containing the query
     *
     * @return void
     */

    function extraHead()
    {
        $q = $this->trimmed('q');
        if ($q) {
            $this->element('link', array('rel' => 'alternate',
                                         'href' => common_local_url('noticesearchrss',
                                                                    array('q' => $q)),
                                         'type' => 'application/rss+xml',
                                         'title' => _('Search Stream Feed')));
        }
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
        $this->elementStart('li', array('class' => 'notice_single',
                                          'id' => 'notice-' . $notice->id));
        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        $this->elementStart('a', array('href' => $profile->profileurl));
        $this->element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
                                    'class' => 'avatar stream',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($profile->fullname) ? $profile->fullname :
                                    $profile->nickname));
        $this->elementEnd('a');
        $this->element('a', array('href' => $profile->profileurl,
                                  'class' => 'nickname'),
                       $profile->nickname);
        // FIXME: URL, image, video, audio
        $this->elementStart('p', array('class' => 'content'));
        if ($notice->rendered) {
            $this->raw($this->highlight($notice->rendered, $terms));
        } else {
            // XXX: may be some uncooked notices in the DB,
            // we cook them right now. This should probably disappear in future
            // versions (>> 0.4.x)
            $this->raw($this->highlight(common_render_content($notice->content, $notice), $terms));
        }
        $this->elementEnd('p');
        $noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
        $this->elementStart('p', 'time');
        $this->element('a', array('class' => 'permalink',
                                  'href' => $noticeurl,
                                  'title' => common_exact_date($notice->created)),
                       common_date_string($notice->created));
        if ($notice->reply_to) {
            $replyurl = common_local_url('shownotice', array('notice' => $notice->reply_to));
            $this->text(' (');
            $this->element('a', array('class' => 'inreplyto',
                                      'href' => $replyurl),
                           _('in reply to...'));
            $this->text(')');
        }
        $this->elementStart('a',
                             array('href' => common_local_url('newnotice',
                                                              array('replyto' => $profile->nickname)),
                                   'onclick' => 'doreply("'.$profile->nickname.'"); return false',
                                   'title' => _('reply'),
                                   'class' => 'replybutton'));
        $this->hidden('posttoken', common_session_token());
        
        $this->raw('&rarr;');
        $this->elementEnd('a');
        $this->elementEnd('p');
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

