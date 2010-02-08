<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009-2010, StatusNet, Inc.
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
 * @package FeedSubPlugin
 * @maintainer Brion Vibber <brion@status.net>
 */

/*
PuSH subscription flow:

    $feedinfo->subscribe()
        generate random verification token
            save to verify_token
        sends a sub request to the hub...
    
    feedsub/callback
        hub sends confirmation back to us via GET
        We verify the request, then echo back the challenge.
        On our end, we save the time we subscribed and the lease expiration
    
    feedsub/callback
        hub sends us updates via POST
    
*/

class FeedDBException extends FeedSubException
{
    public $obj;

    function __construct($obj)
    {
        parent::__construct('Database insert failure');
        $this->obj = $obj;
    }
}

class Feedinfo extends Memcached_DataObject
{
    public $__table = 'feedinfo';

    public $id;
    public $profile_id;

    public $feeduri;
    public $homeuri;
    public $huburi;

    // PuSH subscription data
    public $secret;
    public $verify_token;
    public $sub_start;
    public $sub_end;

    public $created;
    public $lastupdate;


    public /*static*/ function staticGet($k, $v=null)
    {
        return parent::staticGet(__CLASS__, $k, $v);
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
        return array('id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'profile_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
                     'feeduri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'homeuri' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'huburi' =>  DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
                     'secret' => DB_DATAOBJECT_STR,
                     'verify_token' => DB_DATAOBJECT_STR,
                     'sub_start' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'sub_end' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME,
                     'created' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL,
                     'lastupdate' => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL);
    }
    
    static function schemaDef()
    {
        return array(new ColumnDef('id', 'integer',
                                   /*size*/ null,
                                   /*nullable*/ false,
                                   /*key*/ 'PRI',
                                   /*default*/ '0',
                                   /*extra*/ null,
                                   /*auto_increment*/ true),
                     new ColumnDef('profile_id', 'integer',
                                   null, false),
                     new ColumnDef('feeduri', 'varchar',
                                   255, false, 'UNI'),
                     new ColumnDef('homeuri', 'varchar',
                                   255, false),
                     new ColumnDef('huburi', 'varchar',
                                   255, false),
                     new ColumnDef('verify_token', 'varchar',
                                   32, true),
                     new ColumnDef('secret', 'varchar',
                                   64, true),
                     new ColumnDef('sub_start', 'datetime',
                                   null, true),
                     new ColumnDef('sub_end', 'datetime',
                                   null, true),
                     new ColumnDef('created', 'datetime',
                                   null, false),
                     new ColumnDef('lastupdate', 'datetime',
                                   null, false));
    }

    /**
     * return key definitions for DB_DataObject
     *
     * DB_DataObject needs to know about keys that the table has; this function
     * defines them.
     *
     * @return array key definitions
     */

    function keys()
    {
        return array_keys($this->keyTypes());
    }

    /**
     * return key definitions for Memcached_DataObject
     *
     * Our caching system uses the same key definitions, but uses a different
     * method to get them.
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return array('id' => 'K'); // @fixme we'll need a profile_id key at least
    }

    function sequenceKey()
    {
        return array('id', true, false);
    }

    /**
     * Fetch the StatusNet-side profile for this feed
     * @return Profile
     */
    public function getProfile()
    {
        return Profile::staticGet('id', $this->profile_id);
    }

    /**
     * @param FeedMunger $munger
     * @return Feedinfo
     */
    public static function ensureProfile($munger)
    {
        $feedinfo = $munger->feedinfo();

        $current = self::staticGet('feeduri', $feedinfo->feeduri);
        if ($current) {
            // @fixme we should probably update info as necessary
            return $current;
        }

        $feedinfo->query('BEGIN');

        // Awful hack! Awful hack!
        $feedinfo->verify = common_good_rand(16);
        $feedinfo->secret = common_good_rand(32);

        try {
            $profile = $munger->profile();
            $result = $profile->insert();
            if (empty($result)) {
                throw new FeedDBException($profile);
            }

            $avatar = $munger->getAvatar();
            if ($avatar) {
                // @fixme this should be better encapsulated
                // ripped from oauthstore.php (for old OMB client)
                $temp_filename = tempnam(sys_get_temp_dir(), 'listener_avatar');
                copy($avatar, $temp_filename);
                $imagefile = new ImageFile($profile->id, $temp_filename);
                $filename = Avatar::filename($profile->id,
                                             image_type_to_extension($imagefile->type),
                                             null,
                                             common_timestamp());
                rename($temp_filename, Avatar::path($filename));
                $profile->setOriginal($filename);
            }

            $feedinfo->profile_id = $profile->id;
            $result = $feedinfo->insert();
            if (empty($result)) {
                throw new FeedDBException($feedinfo);
            }

            $feedinfo->query('COMMIT');
        } catch (FeedDBException $e) {
            common_log_db_error($e->obj, 'INSERT', __FILE__);
            $feedinfo->query('ROLLBACK');
            return false;
        }
        return $feedinfo;
    }

    /**
     * Send a subscription request to the hub for this feed.
     * The hub will later send us a confirmation POST to /feedsub/callback.
     *
     * @return bool true on success, false on failure
     */
    public function subscribe()
    {
        if (common_config('feedsub', 'nohub')) {
            // Fake it! We're just testing remote feeds w/o hubs.
            return true;
        }
        // @fixme use the verification token
        #$token = md5(mt_rand() . ':' . $this->feeduri);
        #$this->verify_token = $token;
        #$this->update(); // @fixme
        try {
            $callback = common_local_url('feedsubcallback', array('feed' => $this->id));
            $headers = array('Content-Type: application/x-www-form-urlencoded');
            $post = array('hub.mode' => 'subscribe',
                          'hub.callback' => $callback,
                          'hub.verify' => 'async',
                          'hub.verify_token' => $this->verify_token,
                          'hub.secret' => $this->secret,
                          //'hub.lease_seconds' => 0,
                          'hub.topic' => $this->feeduri);
            $client = new HTTPClient();
            $response = $client->post($this->huburi, $headers, $post);
            $status = $response->getStatus();
            if ($status == 202) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok, awaiting verification callback');
                return true;
            } else if ($status == 204) {
                common_log(LOG_INFO, __METHOD__ . ': sub req ok and verified');
                return true;
            } else if ($status >= 200 && $status < 300) {
                common_log(LOG_ERR, __METHOD__ . ": sub req returned unexpected HTTP $status: " . $response->getBody());
                return false;
            } else {
                common_log(LOG_ERR, __METHOD__ . ": sub req failed with HTTP $status: " . $response->getBody());
                return false;
            }
        } catch (Exception $e) {
            // wtf!
            common_log(LOG_ERR, __METHOD__ . ": error \"{$e->getMessage()}\" hitting hub $this->huburi subscribing to $this->feeduri");
            return false;
        }
    }

    /**
     * Read and post notices for updates from the feed.
     * Currently assumes that all items in the feed are new,
     * coming from a PuSH hub.
     *
     * @param string $xml source of Atom or RSS feed
     * @param string $hmac X-Hub-Signature header, if present
     */
    public function postUpdates($xml, $hmac)
    {
        common_log(LOG_INFO, __METHOD__ . ": packet for \"$this->feeduri\"! $hmac $xml");

        if ($this->secret) {
            if (preg_match('/^sha1=([0-9a-fA-F]{40})$/', $hmac, $matches)) {
                $their_hmac = strtolower($matches[1]);
                $our_hmac = sha1($xml . $this->secret);
                if ($their_hmac !== $our_hmac) {
                    common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bad SHA-1 HMAC: got $their_hmac, expected $our_hmac");
                    return;
                }
            } else {
                common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with bogus HMAC '$hmac'");
                return;
            }
        } else if ($hmac) {
            common_log(LOG_ERR, __METHOD__ . ": ignoring PuSH with unexpected HMAC '$hmac'");
            return;
        }

        require_once "XML/Feed/Parser.php";
        $feed = new XML_Feed_Parser($xml, false, false, true);
        $munger = new FeedMunger($feed);
        
        $hits = 0;
        foreach ($feed as $index => $entry) {
            // @fixme this might sort in wrong order if we get multiple updates
            
            $notice = $munger->notice($index);
            $notice->profile_id = $this->profile_id;
            
            // Double-check for oldies
            // @fixme this could explode horribly for multiple feeds on a blog. sigh
            $dupe = new Notice();
            $dupe->uri = $notice->uri;
            if ($dupe->find(true)) {
                common_log(LOG_WARNING, __METHOD__ . ": tried to save dupe notice for entry {$notice->uri} of feed {$this->feeduri}");
                continue;
            }
            
            if (Event::handle('StartNoticeSave', array(&$notice))) {
                $id = $notice->insert();
                Event::handle('EndNoticeSave', array($notice));
            }
            $notice->addToInboxes();

            common_log(LOG_INFO, __METHOD__ . ": saved notice {$notice->id} for entry $index of update to \"{$this->feeduri}\"");
            $hits++;
        }
        if ($hits == 0) {
            common_log(LOG_INFO, __METHOD__ . ": no updates in packet for \"$this->feeduri\"! $xml");
        }
    }
}
