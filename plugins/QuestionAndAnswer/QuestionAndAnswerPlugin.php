<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Microapp plugin for Questions and Answers
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
 * Question and Answer plugin
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class QuestionAndAnswerPlugin extends MicroappPlugin
{
    /**
     * Set up our tables (question and answer)
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('question', Question::schemaDef());
        $schema->ensureTable('answer', Answer::schemaDef());

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'NewquestionAction':
        case 'NewanswerAction':
        case 'ShowquestionAction':
        case 'ShowanswerAction':
            include_once $dir . '/actions/'
                . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'QuestionForm':
        case 'AnswerForm':
            include_once $dir . '/lib/' . strtolower($cls).'.php';
            break;
        case 'Question':
        case 'Answer':
            include_once $dir . '/classes/' . $cls.'.php';
            return false;
            break;
        default:
            return true;
        }
    }

    /**
     * Map URLs to actions
     *
     * @param Net_URL_Mapper $m path-to-action mapper
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onRouterInitialized($m)
    {
        $m->connect('main/question/new',
                    array('action' => 'newquestion'));
        $m->connect('main/question/answer',
                    array('action' => 'newanswer'));
        $m->connect('question/:id',
                    array('action' => 'showquestion'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        $m->connect('answer/:id',
                    array('action' => 'showanswer'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array(
            'name'        => 'QuestionAndAnswer',
            'version'     => STATUSNET_VERSION,
            'author'      => 'Zach Copley',
            'homepage'    => 'http://status.net/wiki/Plugin:QuestionAndAnswer',
            'description' =>
             _m('Question and Answers micro-app.')
        );
        return true;
    }

    function appTitle() {
        return _m('Question');
    }

    function tag() {
        return 'question';
    }

    function types() {
        return array(
            Question::OBJECT_TYPE,
            Answer::NORMAL
        );
    }

    /**
     * Given a parsed ActivityStreams activity, save it into a notice
     * and other data structures.
     *
     * @param Activity $activity
     * @param Profile $actor
     * @param array $options=array()
     *
     * @return Notice the resulting notice
     */
    function saveNoticeFromActivity($activity, $actor, $options=array())
    {
        if (count($activity->objects) != 1) {
            throw new Exception('Too many activity objects.');
        }

        $questionObj = $activity->objects[0];

        if ($questinoObj->type != Question::OBJECT_TYPE) {
            throw new Exception('Wrong type for object.');
        }

        $notice = null;

        switch ($activity->verb) {
        case ActivityVerb::POST:
            $notice = Question::saveNew(
                $actor,
                $questionObj->title
               // null,
               // $questionObj->summary,
               // $options
            );
            break;
        case Answer::NORMAL:
        case Answer::ANONYMOUS:
            $question = Question::staticGet('uri', $questionObj->id);
            if (empty($question)) {
                // FIXME: save the question
                throw new Exception("Answer to unknown question.");
            }
            $notice = Answer::saveNew($actor, $question, $activity->verb, $options);
            break;
        default:
            throw new Exception("Unknown verb for question");
        }

        return $notice;
    }

    /**
     * Turn a Notice into an activity object
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */

    function activityObjectFromNotice($notice)
    {
        $question = null;

        switch ($notice->object_type) {
        case Question::OBJECT_TYPE:
            $question = Qeustion::fromNotice($notice);
            break;
        case Answer::NORMAL:
        case Answer::ANONYMOUS:
            $answer   = Answer::fromNotice($notice);
            $question = $answer->getQuestion();
            break;
        }

        if (empty($question)) {
            throw new Exception("Unknown object type.");
        }

        $notice = $question->getNotice();

        if (empty($notice)) {
            throw new Exception("Unknown question notice.");
        }

        $obj = new ActivityObject();

        $obj->id      = $question->uri;
        $obj->type    = Question::OBJECT_TYPE;
        $obj->title   = $question->title;
        $obj->link    = $notice->bestUrl();

        // XXX: probably need other stuff here

        return $obj;
    }

    /**
     * Change the verb on Answer notices
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */

    function onEndNoticeAsActivity($notice, &$act) {
        switch ($notice->object_type) {
        case Answer::NORMAL:
        case Answer::ANONYMOUS:
            $act->verb = $notice->object_type;
            break;
        }
        return true;
    }

    /**
     * Custom HTML output for our notices
     *
     * @param Notice $notice
     * @param HTMLOutputter $out
     */

    function showNotice($notice, $out)
    {
        switch ($notice->object_type) {
        case Question::OBJECT_TYPE:
            $this->showQuestionNotice($notice, $out);
            break;
        case Answer::NORMAL:
        case Answer::ANONYMOUS:
        case RSVP::POSSIBLE:
            $this->showAnswerNotice($notice, $out);
            break;
        }

        $out->elementStart('div', array('class' => 'question'));

        $profile = $notice->getProfile();
        $avatar = $profile->getAvatar(AVATAR_MINI_SIZE);

        $out->element('img',
                      array('src' => ($avatar) ?
                            $avatar->displayUrl() :
                            Avatar::defaultImage(AVATAR_MINI_SIZE),
                            'class' => 'avatar photo bookmark-avatar',
                            'width' => AVATAR_MINI_SIZE,
                            'height' => AVATAR_MINI_SIZE,
                            'alt' => $profile->getBestName()));

        $out->raw('&#160;'); // avoid &nbsp; for AJAX XML compatibility

        $out->elementStart('span', 'vcard author'); // hack for belongsOnTimeline; JS needs to be able to find the author
        $out->element('a',
                      array('class' => 'url',
                            'href' => $profile->profileurl,
                            'title' => $profile->getBestName()),
                      $profile->nickname);
        $out->elementEnd('span');
    }

    function showAnswerNotice($notice, $out)
    {
        $rsvp = Answer::fromNotice($notice);

        $out->elementStart('div', 'answer');
        $out->raw($answer->asHTML());
        $out->elementEnd('div');
        return;
    }

    function showQuestionNotice($notice, $out)
    {
        $profile  = $notice->getProfile();
        $question = Question::fromNotice($notice);

        assert(!empty($question));
        assert(!empty($profile));

        $out->elementStart('div', 'question-notice');

        $out->elementStart('h3');

        if (!empty($question->url)) {
            $out->element('a',
                          array('href' => $question->url,
                                'class' => 'question-title'),
                          $question->title);
        } else {
            $out->text($question->title);
        }

        if (!empty($question->location)) {
            $out->elementStart('div', 'question-location');
            $out->element('strong', null, _('Location: '));
            $out->element('span', 'location', $question->location);
            $out->elementEnd('div');
        }

        if (!empty($question->description)) {
            $out->elementStart('div', 'question-description');
            $out->element('strong', null, _('Description: '));
            $out->element('span', 'description', $question->description);
            $out->elementEnd('div');
        }

        $answers = $question->getAnswers();

        $out->elementStart('div', 'question-answers');
        $out->element('strong', null, _('Answer: '));
        $out->element('span', 'question-answer');

        // XXX I dunno

        $out->elementEnd('div');

        $user = common_current_user();

        if (!empty($user)) {
            $question = $question->getAnswer($user->getProfile());

            if (empty($answer)) {
                $form = new AnswerForm($question, $out);
            }

            $form->show();
        }

        $out->elementEnd('div');
    }

    /**
     * Form for our app
     *
     * @param HTMLOutputter $out
     * @return Widget
     */

    function entryForm($out)
    {
        return new QuestionForm($out);
    }

    /**
     * When a notice is deleted, clean up related tables.
     *
     * @param Notice $notice
     */

    function deleteRelated($notice)
    {
        switch ($notice->object_type) {
        case Question::OBJECT_TYPE:
            common_log(LOG_DEBUG, "Deleting question from notice...");
            $question = Question::fromNotice($notice);
            $question->delete();
            break;
        case Answer::NORMAL:
        case Answer::ANONYMOUS:
            common_log(LOG_DEBUG, "Deleting answer from notice...");
            $answer = Answer::fromNotice($notice);
            common_log(LOG_DEBUG, "to delete: $answer->id");
            $answer->delete();
            break;
        default:
            common_log(LOG_DEBUG, "Not deleting related, wtf...");
        }
    }

    function onEndShowScripts($action)
    {
        // XXX maybe some cool shiz here
    }

    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('css/questionandanswer.css'));
        return true;
    }
}
