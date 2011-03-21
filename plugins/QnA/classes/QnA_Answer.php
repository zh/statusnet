<?php
/**
 * Data class to save answers to questions
 *
 * PHP version 5
 *
 * @category QnA
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * For storing answers
 *
 * @category QnA
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */
class QnA_Answer extends Managed_DataObject
{
    const  OBJECT_TYPE = 'http://activityschema.org/object/answer';

    public $__table = 'qna_answer'; // table name
    public $id;          // char(36) primary key not null -> UUID
    public $question_id; // char(36) -> question.id UUID
    public $profile_id;  // int -> question.id
    public $best;        // (int) boolean -> whether the question asker has marked this as the best answer
    public $created;     // datetime

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup
     * @param mixed  $v Value to lookup
     *
     * @return QnA_Answer object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('QnA_Answer', $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return QA_Answer object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('QnA_Answer', $kv);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Record of answers to questions',
            'fields' => array(
                'id' => array(
                    'type'     => 'char',
                    'length'   => 36,
                    'not null' => true, 'description' => 'UUID of the response'),
                    'uri'      => array(
                        'type'        => 'varchar',
                        'length'      => 255,
                        'not null'    => true,
                        'description' => 'UUID to the answer notice'
                    ),
                    'question_id' => array(
                        'type'     => 'char',
                        'length'   => 36,
                        'not null' => true,
                        'description' => 'UUID of question being responded to'
                    ),
                    'best'     => array('type' => 'int', 'size' => 'tiny'),
                    'profile_id'  => array('type' => 'int'),
                    'created'     => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'question_uri_key' => array('uri'),
                'question_id_profile_id_key' => array('question_id', 'profile_id'),
            ),
            'indexes' => array(
                'profile_id_question_id_index' => array('profile_id', 'question_id'),
            )
        );
    }

    /**
     * Get an answer based on a notice
     *
     * @param Notice $notice Notice to check for
     *
     * @return QnA_Answer found response or null
     */
    function getByNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    /**
     * Get the notice that belongs to this answer
     *
     * @return Notice
     */
    function getNotice()
    {
        return Notice::staticGet('uri', $this->uri);
    }

    function bestUrl()
    {
        return $this->getNotice()->bestUrl();
    }

    /**
     * Get the Question this is an answer to
     *
     * @return QnA_Question
     */
    function getQuestion()
    {
        return Question::staticGet('id', $this->question_id);
    }

    static function fromNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    /**
     * Save a new answer notice
     *
     * @param Profile  $profile
     * @param Question $Question the question being answered
     * @param array
     *
     * @return Notice saved notice
     */
    static function saveNew($profile, $question, $options = null)
    {
        if (empty($options)) {
            $options = array();
        }

        $answer              = new QnA_Answer();
        $answer->id          = UUID::gen();
        $answer->profile_id  = $profile->id;
        $answer->question_id = $question->id;
        $answer->created     = common_sql_now();
        $answer->uri         = common_local_url(
            'showanswer',
            array('id' => $answer->id)
        );

        common_log(LOG_DEBUG, "Saving answer: $answer->id, $answer->uri");
        $answer->insert();

        // TRANS: Notice content answering a question.
        // TRANS: %s is the answer
        $content  = sprintf(
            _m('answered "%s"'),
            $answer->uri
        );

        $link = '<a href="' . htmlspecialchars($answer->uri) . '">' . htmlspecialchars($answer) . '</a>';
        // TRANS: Rendered version of the notice content answering a question.
        // TRANS: %s a link to the question with question title as the link content.
        $rendered = sprintf(_m('answered "%s"'), $link);

        $tags    = array();
        $replies = array();

        $options = array_merge(
            array(
                'urls'        => array(),
                'rendered'    => $rendered,
                'tags'        => $tags,
                'replies'     => $replies,
                'reply_to'    => $question->getNotice()->id,
                'object_type' => self::OBJECT_TYPE),
                $options
            );

        if (!array_key_exists('uri', $options)) {
            $options['uri'] = $answer->uri;
        }

        $saved = Notice::saveNew(
            $profile->id,
            $content,
            array_key_exists('source', $options) ?
            $options['source'] : 'web',
            $options
        );

        return $saved;
    }
}
