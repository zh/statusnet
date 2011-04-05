<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Answer a question
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
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Answer a question
 *
 * @category  QnA
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QnanewanswerAction extends Action
{
    protected $user     = null;
    protected $error    = null;
    protected $complete = null;

    protected $question = null;
    protected $content  = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Page title for and answer to a question.
        return _m('Answer');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to answer a question while not logged in.
            throw new ClientException(
                _m("You must be logged in to answer to a question."),
                403
            );
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        $id = substr($this->trimmed('id'), 9);

        common_debug("XXXXXXXXXXXXXXXXXX id = " . $id);

        $this->question = QnA_Question::staticGet('id', $id);

        if (empty($this->question)) {
            // TRANS: Client exception thrown trying to respond to a non-existing question.
            throw new ClientException(
                _m('Invalid or missing question.'),
                404
            );
        }

        $this->answerText = $this->trimmed('answer');

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->newAnswer();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new answer
     *
     * @return void
     */
    function newAnswer()
    {
        $profile = $this->user->getProfile();

        try {
            $notice = QnA_Answer::saveNew(
                $profile,
                $this->question,
                $this->answerText
            );
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }
        if ($this->boolean('ajax')) {
            common_debug("ajaxy part");
            $answer = $this->question->getAnswer($profile);
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');

            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending an answer.
            $this->element('title', null, _m('Answers'));
            $this->elementEnd('head');

            $this->elementStart('body');

            $nli = new NoticeListItem($notice, $this);
            $nli->show();
            //$this->raw($answer->asHTML());

            /*
            $question = $this->question;

            $nli = new NoticeListItem($notice, $this);
            $nli->showNotice();

            $this->elementStart('div', array('class' => 'entry-content answer-content'));

            if (!empty($answer)) {
                $form = new QnashowanswerForm($this, $answer);
                $form->show();
            } else {
                $this->text(_m('Answer data is missing.'));
            }

            $this->elementEnd('div');

            // @fixme
            //$this->elementStart('div', array('class' => 'entry-content'));
            */
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($this->question->bestUrl(), 303);
        }
    }

    /**
     * Show the Answer form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new QnanewanswerForm($this->question, $this);
        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}
