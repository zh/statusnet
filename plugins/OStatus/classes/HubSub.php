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
 * PuSH feed subscription record
 * @package Hub
 * @author Brion Vibber <brion@status.net>
 */
class HubSub extends Memcached_DataObject
{
    public $__table = 'hubsub';

    public $hashkey; // sha1(topic . '|' . $callback); (topic, callback) key is too long for myisam in utf8
    public $topic;
    public $callback;
    public $secret;
    public $verify_token;
    public $challenge;
    public $lease;
    public $sub_start;
    public $sub_end;
    public $created;

    public /*static*/ function staticGet($topic, $callback)
    {
        return parent::staticGet(__CLASS__, 'hashkey', self::hashkey($topic, $callback));
    }

    protected static function hashkey($topic, $callback)
    {
        return sha1($topic . '|' . $callback);
    }

    /**
     * return table definition for DB_DataObject
     *
     * DB_DataObject needs to know something about the table to manipulate
     * instances. This method provides all the DB_DataObject needs to know.
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array('hashkey' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'topic' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'callback' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'secret' => DB_DATAOBJECT_STR,
                     'verify_token' => DB_DATAOBJECT_STR,
                     'challenge' => DB_DATAOBJECT_STR,
                     'lease' =>  DB_DATAOBJECT_INT,
                     'sub_start' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'sub_end' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }

    static function schemaDef()
    {
        return array(new ColumnDef('hashkey', 'char',
                                   /*size*/40,
                                   /*nullable*/false,
                                   /*key*/'PRI'),
                     new ColumnDef('topic', 'varchar',
                                   /*size*/255,
                                   /*nullable*/false,
                                   /*key*/'KEY'),
                     new ColumnDef('callback', 'varchar',
                                   255, false),
                     new ColumnDef('secret', 'text',
                                   null, true),
                     new ColumnDef('verify_token', 'text',
                                   null, true),
                     new ColumnDef('challenge', 'varchar',
                                   32, true),
                     new ColumnDef('lease', 'int',
                                   null, true),
                     new ColumnDef('sub_start', 'datetime',
                                   null, true),
                     new ColumnDef('sub_end', 'datetime',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false));
    }

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function sequenceKeys()
    {
        return array(false, false, false);
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return array('hashkey' => 'K');
    }

    /**
     * Validates a requested lease length, sets length plus
     * subscription start & end dates.
     *
     * Does not save to database -- use before insert() or update().
     *
     * @param int $length in seconds
     */
    function setLease($length)
    {
        assert(is_int($length));

        $min = 86400;
        $max = 86400 * 30;

        if ($length == 0) {
            // We want to garbage collect dead subscriptions!
            $length = $max;
        } elseif( $length < $min) {
            $length = $min;
        } else if ($length > $max) {
            $length = $max;
        }

        $this->lease = $length;
        $this->start_sub = common_sql_now();
        $this->end_sub = common_sql_date(time() + $length);
    }

    /**
     * Send a verification ping to subscriber
     * @param string $mode 'subscribe' or 'unsubscribe'
     */
    function verify($mode)
    {
        assert($mode == 'subscribe' || $mode == 'unsubscribe');

        // Is this needed? data object fun...
        $clone = clone($this);
        $clone->challenge = common_good_rand(16);
        $clone->update($this);
        $this->challenge = $clone->challenge;
        unset($clone);

        $params = array('hub.mode' => $mode,
                        'hub.topic' => $this->topic,
                        'hub.challenge' => $this->challenge);
        if ($mode == 'subscribe') {
            $params['hub.lease_seconds'] = $this->lease;
        }
        if ($this->verify_token) {
            $params['hub.verify_token'] = $this->verify_token;
        }
        $url = $this->callback . '?' . http_build_query($params, '', '&'); // @fixme ugly urls

        try {
            $request = new HTTPClient();
            $response = $request->get($url);
            $status = $response->getStatus();

            if ($status >= 200 && $status < 300) {
                $fail = false;
            } else {
                // @fixme how can we schedule a second attempt?
                // Or should we?
                $fail = "Returned HTTP $status";
            }
        } catch (Exception $e) {
            $fail = $e->getMessage();
        }
        if ($fail) {
            // @fixme how can we schedule a second attempt?
            // or save a fail count?
            // Or should we?
            common_log(LOG_ERR, "Failed to verify $mode for $this->topic at $this->callback: $fail");
            return false;
        } else {
            if ($mode == 'subscribe') {
                // Establish or renew the subscription!
                // This seems unnecessary... dataobject fun!
                $clone = clone($this);
                $clone->challenge = null;
                $clone->setLease($this->lease);
                $clone->update($this);
                unset($clone);

                $this->challenge = null;
                $this->setLease($this->lease);
                common_log(LOG_ERR, "Verified $mode of $this->callback:$this->topic for $this->lease seconds");
            } else if ($mode == 'unsubscribe') {
                common_log(LOG_ERR, "Verified $mode of $this->callback:$this->topic");
                $this->delete();
            }
            return true;
        }
    }

    /**
     * Insert wrapper; transparently set the hash key from topic and callback columns.
     * @return boolean success
     */
    function insert()
    {
        $this->hashkey = self::hashkey($this->topic, $this->callback);
        return parent::insert();
    }

    /**
     * Send a 'fat ping' to the subscriber's callback endpoint
     * containing the given Atom feed chunk.
     *
     * Determination of which items to send should be done at
     * a higher level; don't just shove in a complete feed!
     *
     * @param string $atom well-formed Atom feed
     */
    function push($atom)
    {
        $headers = array('Content-Type: application/atom+xml');
        if ($this->secret) {
            $hmac = sha1($atom . $this->secret);
            $headers[] = "X-Hub-Signature: sha1=$hmac";
        } else {
            $hmac = '(none)';
        }
        common_log(LOG_INFO, "About to push feed to $this->callback for $this->topic, HMAC $hmac");
        try {
            $request = new HTTPClient();
            $request->setBody($atom);
            $response = $request->post($this->callback, $headers);

            if ($response->isOk()) {
                return true;
            }
            common_log(LOG_ERR, "Error sending PuSH content " .
                                "to $this->callback for $this->topic: " .
                                $response->getStatus());
            return false;

        } catch (Exception $e) {
            common_log(LOG_ERR, "Error sending PuSH content " .
                                "to $this->callback for $this->topic: " .
                                $e->getMessage());
            return false;
        }
    }
}

