<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010 StatusNet, Inc.
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

$shortoptions = 'i:n:r:w:';
$longoptions = array('id=', 'nickname=', 'remote=', 'password=');

$helptext = <<<END_OF_MOVEUSER_HELP
moveuser.php [options]
Move a local user to a remote account.

  -i --id       ID of user to move
  -n --nickname nickname of the user to move
  -r --remote   Full ID of remote users
  -w --password Password of remote user

Remote user identity must be a Webfinger (nickname@example.com) or 
an HTTP or HTTPS URL (http://example.com/social/site/user/nickname).

END_OF_MOVEUSER_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';
require_once INSTALLDIR.'/extlib/htmLawed/htmLawed.php';

class ActivitySink
{
    protected $svcDocUrl   = null;
    protected $username    = null;
    protected $password    = null;
    protected $collections = array();

    function __construct($svcDocUrl, $username, $password)
    {
        $this->svcDocUrl = $svcDocUrl;
        $this->username  = $username;
        $this->password  = $password;

        $this->_parseSvcDoc();
    }

    private function _parseSvcDoc()
    {
        $client   = new HTTPClient();
        $response = $client->get($this->svcDocUrl);

        if ($response->getStatus() != 200) {
            throw new Exception("Can't get {$this->svcDocUrl}; response status " . $response->getStatus());
        }

        $xml = $response->getBody();

        $dom = new DOMDocument();

        // We don't want to bother with white spaces
        $dom->preserveWhiteSpace = false;

        // Don't spew XML warnings to output
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        $ok = $dom->loadXML($xml);
        error_reporting($old);

        $path = new DOMXPath($dom);

        $path->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $path->registerNamespace('app', 'http://www.w3.org/2007/app');
        $path->registerNamespace('activity', 'http://activitystrea.ms/spec/1.0/');

        $collections = $path->query('//app:collection');

        for ($i = 0; $i < $collections->length; $i++) {
            $collection = $collections->item($i);
            $url = $collection->getAttribute('href');
            $takesEntries = false;
            $accepts = $path->query('app:accept', $collection);
            for ($j = 0; $j < $accepts->length; $j++) {
                $accept = $accepts->item($j);
                $acceptValue = $accept->nodeValue;
                if (preg_match('#application/atom\+xml(;\s*type=entry)?#', $acceptValue)) {
                    $takesEntries = true;
                    break;
                }
            }
            if (!$takesEntries) {
                continue;
            }
            $verbs = $path->query('activity:verb', $collection);
            if ($verbs->length == 0) {
                $this->_addCollection(ActivityVerb::POST, $url);
            } else {
                for ($k = 0; $k < $verbs->length; $k++) {
                    $verb = $verbs->item($k);
                    $this->_addCollection($verb->nodeValue, $url);
                }
            }
        }
    }

    private function _addCollection($verb, $url)
    {
        if (array_key_exists($verb, $this->collections)) {
            $this->collections[$verb][] = $url;
        } else {
            $this->collections[$verb] = array($url);
        }
        return;
    }

    function postActivity($activity)
    {
        if (!array_key_exists($activity->verb, $this->collections)) {
            throw new Exception("No collection for verb {$activity->verb}");
        } else {
            if (count($this->collections[$activity->verb]) > 1) {
                common_log(LOG_NOTICE, "More than one collection for verb {$activity->verb}");
            }
            $this->postToCollection($this->collections[$activity->verb][0], $activity);
        }
    }

    function postToCollection($url, $activity)
    {
        $client = new HTTPClient($url);

        $client->setMethod('POST');
        $client->setAuth($this->username, $this->password);
        $client->setHeader('Content-Type', 'application/atom+xml;type=entry');
        $client->setBody($activity->asString(true, true, true));

        $response = $client->send();
    }
}

function getServiceDocument($remote)
{
    $discovery = new Discovery();

    $xrd = $discovery->lookup($remote);

    if (empty($xrd)) {
        throw new Exception("Can't find XRD for $remote");
    } 

    $svcDocUrl = null;
    $username  = null;

    foreach ($xrd->links as $link) {
        if ($link['rel'] == 'http://apinamespace.org/atom' &&
            $link['type'] == 'application/atomsvc+xml') {
            $svcDocUrl = $link['href'];
            if (!empty($link['property'])) {
                foreach ($link['property'] as $property) {
                    if ($property['type'] == 'http://apinamespace.org/atom/username') {
                        $username = $property['value'];
                        break;
                    }
                }
            }
            break;
        }
    }

    if (empty($svcDocUrl)) {
        throw new Exception("No AtomPub API service for $remote.");
    }

    return array($svcDocUrl, $username);
}

class AccountMover
{
    private $_user    = null;
    private $_profile = null;
    private $_remote  = null;
    private $_sink    = null;
    
    function __construct($user, $remote, $password)
    {
        $this->_user    = $user;
        $this->_profile = $user->getProfile();

        $oprofile = Ostatus_profile::ensureProfileURI($remote);

        if (empty($oprofile)) {
            throw new Exception("Can't locate account {$remote}");
        }

        $this->_remote = $oprofile->localProfile();

        list($svcDocUrl, $username) = getServiceDocument($remote);

        $this->_sink = new ActivitySink($svcDocUrl, $username, $password);
    }

    function move()
    {
        $stream = new UserActivityStream($this->_user);

        $acts = array_reverse($stream->activities);

        // Reverse activities to run in correct chron order

        foreach ($acts as $act) {
            $this->_moveActivity($act);
        }
    }

    private function _moveActivity($act)
    {
        switch ($act->verb) {
        case ActivityVerb::FAVORITE:
            // push it, then delete local
            $this->_sink->postActivity($act);
            $notice = Notice::staticGet('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $fave = Fave::pkeyGet(array('user_id' => $this->_user->id,
                                            'notice_id' => $notice->id));
                $fave->delete();
            }
            break;
        case ActivityVerb::POST:
            // XXX: send a reshare, not a post
            common_log(LOG_INFO, "Pushing notice {$act->objects[0]->id} to {$this->_remote->getURI()}");
            $this->_sink->postActivity($act);
            $notice = Notice::staticGet('uri', $act->objects[0]->id);
            if (!empty($notice)) {
                $notice->delete();
            }
            break;
        case ActivityVerb::JOIN:
            $this->_sink->postActivity($act);
            $group = User_group::staticGet('uri', $act->objects[0]->id);
            if (!empty($group)) {
                Group_member::leave($group->id, $this->_user->id);
            }
            break;
        case ActivityVerb::FOLLOW:
            if ($act->actor->id == $this->_user->uri) {
                $this->_sink->postActivity($act);
                $other = Profile::fromURI($act->objects[0]->id);
                if (!empty($other)) {
                    Subscription::cancel($this->_profile, $other);
                }
            } else {
                $otherUser = User::staticGet('uri', $act->actor->id);
                if (!empty($otherUser)) {
                    $otherProfile = $otherUser->getProfile();
                    Subscription::start($otherProfile, $this->_remote);
                    Subscription::cancel($otherProfile, $this->_user->getProfile());
                } else {
                    // It's a remote subscription. Do something here!
                }
            }
            break;
        }
    }
}

try {

    $user = getUser();

    $remote = get_option_value('r', 'remote');

    if (empty($remote)) {
        show_help();
        exit(1);
    }

    $password = get_option_value('w', 'password');

    $mover = new AccountMover($user, $remote, $password);

    $mover->move();

} catch (Exception $e) {
    print $e->getMessage()."\n";
    exit(1);
}
