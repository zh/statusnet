<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * A plugin to enable social-bookmarking functionality
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
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Poll plugin main class
 *
 * @category  PollPlugin
 * @package   StatusNet
 * @author    Brion Vibber <brionv@status.net>
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class PollPlugin extends MicroAppPlugin
{
    const VERSION         = '0.1';
    const POLL_OBJECT     = 'http://apinamespace.org/activitystreams/object/poll';

    /**
     * Database schema setup
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();
        $schema->ensureTable('poll', Poll::schemaDef());
        $schema->ensureTable('poll_response', Poll_response::schemaDef());
        return true;
    }

    /**
     * Show the CSS necessary for this plugin
     *
     * @param Action $action the action being run
     *
     * @return boolean hook value
     */

    function onEndShowStyles($action)
    {
        $action->cssLink($this->path('poll.css'));
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
        case 'ShowpollAction':
        case 'NewpollAction':
        case 'RespondpollAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'Poll':
        case 'Poll_response':
            include_once $dir.'/'.$cls.'.php';
            return false;
        case 'NewPollForm':
        case 'PollResponseForm':
        case 'PollResultForm':
            include_once $dir.'/'.strtolower($cls).'.php';
            return false;
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
        $m->connect('main/poll/new',
                    array('action' => 'newpoll'));

        $m->connect('main/poll/:id',
                    array('action' => 'showpoll'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        $m->connect('main/poll/:id/respond',
                    array('action' => 'respondpoll'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));

        return true;
    }

    /**
     * Plugin version data
     *
     * @param array &$versions array of version data
     *
     * @return value
     */

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Poll',
                            'version' => self::VERSION,
                            'author' => 'Brion Vibber',
                            'homepage' => 'http://status.net/wiki/Plugin:Poll',
                            'rawdescription' =>
                            _m('Simple extension for supporting basic polls.'));
        return true;
    }

    function types()
    {
        return array(self::POLL_OBJECT);
    }

    /**
     * When a notice is deleted, delete the related Poll
     *
     * @param Notice $notice Notice being deleted
     *
     * @return boolean hook value
     */

    function deleteRelated($notice)
    {
        $p = Poll::getByNotice($notice);

        if (!empty($p)) {
            $p->delete();
        }

        return true;
    }

    /**
     * Save a poll from an activity
     *
     * @param Profile  $profile  Profile to use as author
     * @param Activity $activity Activity to save
     * @param array    $options  Options to pass to bookmark-saving code
     *
     * @return Notice resulting notice
     */

    function saveNoticeFromActivity($activity, $profile, $options=array())
    {
        // @fixme
        common_log(LOG_DEBUG, "XXX activity: " . var_export($activity, true));
        common_log(LOG_DEBUG, "XXX options: " . var_export($options, true));

        // Ok for now, we can grab stuff from the XML entry directly.
        // This won't work when reading from JSON source
        if ($activity->entry) {
            $elements = $activity->entry->getElementsByTagNameNS(self::POLL_OBJECT, 'data');
            if ($elements->length) {
                $data = $elements->item(0);
                $question = $data->getAttribute('question');
                $opts = array();
                foreach ($data->attributes as $node) {
                    $name = $node->nodeName;
                    if (substr($name, 0, 6) == 'option') {
                        $n = intval(substr($name, 6));
                        if ($n > 0) {
                            $opts[$n - 1] = $node->nodeValue;
                        }
                    }
                }
                common_log(LOG_DEBUG, "YYY question: $question");
                common_log(LOG_DEBUG, "YYY opts: " . var_export($opts, true));
            } else {
                common_log(LOG_DEBUG, "YYY no poll data");
            }
        }
    }

    function activityObjectFromNotice($notice)
    {
        assert($this->isMyNotice($notice));

        $object = new ActivityObject();
        $object->id      = $notice->uri;
        $object->type    = self::POLL_OBJECT;
        $object->title   = 'Poll title';
        $object->summary = 'Poll summary';
        $object->link    = $notice->bestUrl();

        $poll = Poll::getByNotice($notice);
        /**
         * Adding the poll-specific data. There's no standard in AS for polls,
         * so we're making stuff up.
         *
         * For the moment, using a kind of icky-looking schema that happens to
         * work with out code for generating both Atom and JSON forms, though
         * I don't like it:
         *
         * <poll:data xmlns:poll="http://apinamespace.org/activitystreams/object/poll"
         *            question="Who wants a poll question?"
         *            option1="Option one"
         *            option2="Option two"
         *            option3="Option three"></poll:data>
         *
         * "poll:data": {
         *     "xmlns:poll": http://apinamespace.org/activitystreams/object/poll
         *     "question": "Who wants a poll question?"
         *     "option1": "Option one"
         *     "option2": "Option two"
         *     "option3": "Option three"
         * }
         *
         */
        // @fixme there's no way to specify an XML node tree here, like <poll><option/><option/></poll>
        // @fixme there's no way to specify a JSON array or multi-level tree unless you break the XML attribs
        // @fixme XML node contents don't get shown in JSON
        $data = array('xmlns:poll' => self::POLL_OBJECT,
                      'question'   => $poll->question);
        foreach ($poll->getOptions() as $i => $opt) {
            $data['option' . ($i + 1)] = $opt;
        }
        $object->extra[] = array('poll:data', $data, '');
        return $object;
    }

    /**
     * @fixme WARNING WARNING WARNING parent class closes the final div that we
     * open here, but we probably shouldn't open it here. Check parent class
     * and Bookmark plugin for if that's right.
     */
    function showNotice($notice, $out)
    {
        $user = common_current_user();

        // @hack we want regular rendering, then just add stuff after that
        $nli = new NoticeListItem($notice, $out);
        $nli->showNotice();

        $out->elementStart('div', array('class' => 'entry-content poll-content'));
        $poll = Poll::getByNotice($notice);
        if ($poll) {
            if ($user) {
                $profile = $user->getProfile();
                $response = $poll->getResponse($profile);
                if ($response) {
                    // User has already responded; show the results.
                    $form = new PollResultForm($poll, $out);
                } else {
                    $form = new PollResponseForm($poll, $out);
                }
                $form->show();
            }
        } else {
            $out->text('Poll data is missing');
        }
        $out->elementEnd('div');

        // @fixme
        $out->elementStart('div', array('class' => 'entry-content'));
    }

    function entryForm($out)
    {
        return new NewPollForm($out);
    }

    // @fixme is this from parent?
    function tag()
    {
        return 'poll';
    }

    function appTitle()
    {
        return _m('Poll');
    }
}
