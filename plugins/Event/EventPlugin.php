<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Microapp plugin for event invitations and RSVPs
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
 * @category  Event
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
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
 * Event plugin
 *
 * @category  Sample
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class EventPlugin extends MicroappPlugin
{
    /**
     * Set up our tables (event and rsvp)
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */
    function onCheckSchema()
    {
        $schema = Schema::get();

        $schema->ensureTable('happening', Happening::schemaDef());
        $schema->ensureTable('rsvp', RSVP::schemaDef());

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
        case 'NeweventAction':
        case 'NewrsvpAction':
        case 'ShoweventAction':
        case 'ShowrsvpAction':
            include_once $dir . '/' . strtolower(mb_substr($cls, 0, -6)) . '.php';
            return false;
        case 'EventForm':
            include_once $dir . '/'.strtolower($cls).'.php';
            break;
        case 'Happening':
        case 'RSVP':
            include_once $dir . '/'.$cls.'.php';
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
        $m->connect('main/event/new',
                    array('action' => 'newevent'));
        $m->connect('main/event/rsvp',
                    array('action' => 'newrsvp'));
        $m->connect('event/:id',
                    array('action' => 'showevent'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        $m->connect('rsvp/:id',
                    array('action' => 'showrsvp'),
                    array('id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'));
        return true;
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Event',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:Event',
                            'description' =>
                            _m('Event invitations and RSVPs.'));
        return true;
    }

    function appTitle() {
        return _m('Event');
    }

    function tag() {
        return 'event';
    }

    function types() {
        return array(Happening::OBJECT_TYPE,
                     RSVP::POSITIVE,
                     RSVP::NEGATIVE,
                     RSVP::POSSIBLE);
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

        $happeningObj = $activity->objects[0];

        if ($happeningObj->type != Happening::OBJECT_TYPE) {
            throw new Exception('Wrong type for object.');
        }

        $notice = null;

        switch ($activity->verb) {
        case ActivityVerb::POST:
            $notice = Happening::saveNew($actor, 
                                     $start_time, 
                                     $end_time,
                                     $happeningObj->title,
                                     null,
                                     $happeningObj->summary,
                                     $options);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $happening = Happening::staticGet('uri', $happeningObj->id);
            if (empty($happening)) {
                // FIXME: save the event
                throw new Exception("RSVP for unknown event.");
            }
            $notice = RSVP::saveNew($actor, $happening, $activity->verb, $options);
            break;
        default:
            throw new Exception("Unknown verb for events");
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
        $happening = null;

        switch ($notice->object_type) {
        case Happening::OBJECT_TYPE:
            $happening = Happening::fromNotice($notice);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $rsvp  = RSVP::fromNotice($notice);
            $happening = $rsvp->getEvent();
            break;
        }

        if (empty($happening)) {
            throw new Exception("Unknown object type.");
        }

        $notice = $happening->getNotice();

        if (empty($notice)) {
            throw new Exception("Unknown event notice.");
        }

        $obj = new ActivityObject();

        $obj->id      = $happening->uri;
        $obj->type    = Happening::OBJECT_TYPE;
        $obj->title   = $happening->title;
        $obj->summary = $happening->description;
        $obj->link    = $notice->bestUrl();

        // XXX: how to get this stuff into JSON?!

        $obj->extra[] = array('dtstart',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->start_date));

        $obj->extra[] = array('dtend',
                              array('xmlns' => 'urn:ietf:params:xml:ns:xcal'),
                              common_date_iso8601($happening->end_date));

        // XXX: probably need other stuff here

        return $obj;
    }

    /**
     * Change the verb on RSVP notices
     *
     * @param Notice $notice
     *
     * @return ActivityObject
     */

    function onEndNoticeAsActivity($notice, &$act) {
        switch ($notice->object_type) {
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
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
        case Happening::OBJECT_TYPE:
            $this->showEventNotice($notice, $out);
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $this->showRSVPNotice($notice, $out);
            break;
        }
    }

    function showRSVPNotice($notice, $out)
    {
        $out->element('span', null, 'RSVP');
        return;
    }

    function showEventNotice($notice, $out)
    {
        $out->raw($notice->rendered);
        return;
    }

    /**
     * Form for our app
     *
     * @param HTMLOutputter $out
     * @return Widget
     */

    function entryForm($out)
    {
        return new EventForm($out);
    }

    /**
     * When a notice is deleted, clean up related tables.
     *
     * @param Notice $notice
     */

    function deleteRelated($notice)
    {
        switch ($notice->object_type) {
        case Happening::OBJECT_TYPE:
            $happening = Happening::fromNotice($notice);
            $happening->delete();
            break;
        case RSVP::POSITIVE:
        case RSVP::NEGATIVE:
        case RSVP::POSSIBLE:
            $rsvp = RSVP::fromNotice($notice);
            $rsvp->delete();
            break;
        }
    }
}
