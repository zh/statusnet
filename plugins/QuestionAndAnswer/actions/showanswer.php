<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * Show an answer to a question
 *
 * PHP version 5
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
 * @category  QuestionAndAnswer
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Show an answer to a question, and associated data
 *
 * @category  QuestionAndAnswer
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class ShowAnswerAction extends ShownoticeAction
{
    protected $answer = null;

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */

    function prepare($argarray)
    {
        OwnerDesignAction::prepare($argarray);

        $this->id = $this->trimmed('id');

        $this->answer = Answer::staticGet('id', $this->id);

        if (empty($this->answer)) {
            throw new ClientException(_('No such answer.'), 404);
        }

        $this->notice = Notice::staticGet('uri', $this->answer->uri);

        if (empty($this->notice)) {
            // Did we used to have it, and it got deleted?
            throw new ClientException(_('No such answer.'), 404);
        }

        $this->user = User::staticGet('id', $this->answer->profile_id);

        if (empty($this->user)) {
            throw new ClientException(_('No such user.'), 404);
        }

        $this->profile = $this->user->getProfile();

        if (empty($this->profile)) {
            throw new ServerException(_('User without a profile.'));
        }

        $this->avatar = $this->profile->getAvatar(AVATAR_PROFILE_SIZE);

        return true;
    }

    /**
     * Title of the page
     *
     * Used by Action class for layout.
     *
     * @return string page tile
     */

    function title()
    {
        return sprintf(_('%s\'s answer to "%s"'),
                       $this->user->nickname,
                       $this->answer->title);
    }

    /**
     * Overload page title display to show answer link
     *
     * @return void
     */

    function showPageTitle()
    {
        $this->elementStart('h1');
        $this->element('a',
                       array('href' => $this->answer->url),
                       $this->asnwer->title);
        $this->elementEnd('h1');
    }
}
