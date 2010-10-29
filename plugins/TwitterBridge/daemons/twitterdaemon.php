#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008-2010, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$shortoptions = 'fi::a';
$longoptions = array('id::', 'foreground', 'all');

$helptext = <<<END_OF_XMPP_HELP
Daemon script for receiving new notices from Twitter users.

    -i --id           Identity (default none)
    -a --all          Handle Twitter for all local sites
                      (requires Stomp queue handler, status_network setup)
    -f --foreground   Stay in the foreground (default background)

END_OF_XMPP_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once INSTALLDIR . '/lib/jabber.php';

class TwitterDaemon extends SpawningDaemon
{
    protected $allsites = false;

    function __construct($id=null, $daemonize=true, $threads=1, $allsites=false)
    {
        if ($threads != 1) {
            // This should never happen. :)
            throw new Exception("TwitterDaemon must run single-threaded");
        }
        parent::__construct($id, $daemonize, $threads);
        $this->allsites = $allsites;
    }

    function runThread()
    {
        common_log(LOG_INFO, 'Waiting to listen to Twitter and queues');

        $master = new TwitterMaster($this->get_id(), $this->processManager());
        $master->init($this->allsites);
        $master->service();

        common_log(LOG_INFO, 'terminating normally');

        return $master->respawn ? self::EXIT_RESTART : self::EXIT_SHUTDOWN;
    }

}

class TwitterMaster extends IoMaster
{
    protected $processManager;

    function __construct($id, $processManager)
    {
        parent::__construct($id);
        $this->processManager = $processManager;
    }

    /**
     * Initialize IoManagers for the currently configured site
     * which are appropriate to this instance.
     */
    function initManagers()
    {
        if (common_config('twitter', 'enabled')) {
            $qm = QueueManager::get();
            $qm->setActiveGroup('twitter');
            $this->instantiate($qm);
            $this->instantiate(TwitterManager::get());
            $this->instantiate($this->processManager);
        }
    }
}


class TwitterManager extends IoManager
{
    // Recommended resource limits from http://dev.twitter.com/pages/site_streams
    const MAX_STREAMS = 1000;
    const USERS_PER_STREAM = 100;
    const STREAMS_PER_SECOND = 20;

    protected $twitterStreams;
    protected $twitterUsers;

    function __construct()
    {
    }

    /**
     * Pull the site's active Twitter-importing users and start spawning
     * some data streams for them!
     *
     * @fixme check their last-id and check whether we'll need to do a manual pull.
     * @fixme abstract out the fetching so we can work over multiple sites.
     */
    function initStreams()
    {
        // Pull Twitter user IDs for all users we want to pull data for
        $flink = new Foreign_link();
        $flink->service = TWITTER_SERVICE;
        // @fixme probably should do the bitfield check in a whereAdd but it's ugly :D
        $flink->find();

        $userIds = array();
        while ($flink->fetch()) {
            if (($flink->noticesync & FOREIGN_NOTICE_RECV) ==
                FOREIGN_NOTICE_RECV) {
                $userIds[] = $flink->foreign_id;

                if (count($userIds) >= self::USERS_PER_STREAM) {
                    $this->spawnStream($userIds);
                    $userIds = array();
                }
            }
        }

        if (count($userIds)) {
            $this->spawnStream($userIds);
        }
    }

    /**
     * Prepare a Site Stream connection for the given chunk of users.
     * The actual connection will be opened later.
     *
     * @param $users array of Twitter-side user IDs
     */
    function spawnStream($users)
    {
        $stream = $this->initSiteStream();
        $stream->followUsers($userIds);

        // Slip the stream reader into our list of active streams.
        // We'll manage its actual connection on the next go-around.
        $this->streams[] = $stream;

        // Record the user->stream mappings; this makes it easier for us to know
        // later if we need to kill something.
        foreach ($userIds as $id) {
            $this->users[$id] = $stream;
        }
    }

    /**
     * Initialize a generic site streams connection object.
     * All our connections will look like this, then we'll add users to them.
     *
     * @return TwitterStreamReader
     */
    function initSiteStream()
    {
        $auth = $this->siteStreamAuth();
        $stream = new TwitterSiteStream($auth);

        // Add our event handler callbacks. Whee!
        $this->setupEvents($stream);
        return $stream;
    }

    /**
     * Fetch the Twitter OAuth credentials to use to connect to the Site Streams API.
     *
     * This will use the locally-stored credentials for the applictation's owner account
     * from the site configuration. These should be configured through the administration
     * panels or manually in the config file.
     *
     * Will throw an exception if no credentials can be found -- but beware that invalid
     * credentials won't cause breakage until later.
     *
     * @return TwitterOAuthClient
     */
    function siteStreamAuth()
    {
        $token = common_config('twitter', 'stream_token');
        $secret = common_config('twitter', 'stream_secret');
        if (empty($token) || empty($secret)) {
            throw new ServerException('Twitter site streams have not been correctly configured. Configure the app owner account via the admin panel.');
        }
        return new TwitterOAuthClient($token, $secret);
    }

    /**
     * Collect the sockets for all active connections for i/o monitoring.
     *
     * @return array of resources
     */
    function getSockets()
    {
        $sockets = array();
        foreach ($this->streams as $stream) {
            foreach ($stream->getSockets() as $socket) {
                $sockets[] = $socket;
            }
        }
        return $streams;
    }

    /**
     * We're ready to process input from one of our data sources! Woooooo!
     * @fixme is there an easier way to map from socket back to owning module? :(
     *
     * @param resource $socket
     * @return boolean success
     */
    function handleInput($socket)
    {
        foreach ($this->streams as $stream) {
            foreach ($stream->getSockets() as $aSocket) {
                if ($socket === $aSocket) {
                    $stream->handleInput($socket);
                }
            }
        }
        return true;
    }

    /**
     * Start the system up!
     * @fixme do some rate-limiting on the stream setup
     * @fixme do some sensible backoff on failure etc
     */
    function start()
    {
        $this->initStreams();
        foreach ($this->streams as $stream) {
            $stream->connect();
        }
        return true;
    }

    function finish()
    {
        foreach ($this->streams as $index => $stream) {
            $stream->close();
            unset($this->streams[$index]);
        }
        return true;
    }

    public static function get()
    {
        throw new Exception('not a singleton');
    }

    /**
     * Set up event handlers on the streaming interface.
     *
     * @fixme add more event types as we add handling for them
     */
    protected function setupEvents(TwitterStream $stream)
    {
        $handlers = array(
            'status',
        );
        foreach ($handlers as $event) {
            $stream->hookEvent($event, array($this, 'onTwitter' . ucfirst($event)));
        }
    }

    /**
     * Event callback notifying that a user has a new message in their home timeline.
     *
     * @param object $data JSON data: Twitter status update
     */
    protected function onTwitterStatus($data, $context)
    {
        $importer = new TwitterImport();
        $notice = $importer->importStatus($data);
        if ($notice) {
            $user = $this->getTwitterUser($context);
            Inbox::insertNotice($user->id, $notice->id);
        }
    }

    /**
     * @fixme what about handling multiple sites?
     */
    function getTwitterUser($context)
    {
        if ($context->source != 'sitestream') {
            throw new ServerException("Unexpected stream source");
        }
        $flink = Foreign_link::getByForeignID(TWITTER_SERVICE, $context->for_user);
        if ($flink) {
            return $flink->getUser();
        } else {
            throw new ServerException("No local user for this Twitter ID");
        }
    }
}


if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$foreground = have_option('f', 'foreground');
$all = have_option('a') || have_option('--all');

$daemon = new TwitterDaemon($id, !$foreground, 1, $all);

$daemon->runOnce();
