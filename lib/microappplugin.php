<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Superclass for microapp plugin
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
 * @category  Microapp
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
 * Superclass for microapp plugins
 *
 * This class lets you define micro-applications with different kinds of activities.
 *
 * The applications work more-or-less like other 
 * 
 * @category  Microapp
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

abstract class MicroAppPlugin extends Plugin
{
    abstract function appTitle();
    abstract function tag();
    abstract function types();
    abstract function saveNoticeFromActivity($activity, $actor);
    abstract function activityObjectFromNotice($notice);
    abstract function showNotice($notice, $out);
    abstract function entryForm($out);
    abstract function deleteRelated($notice);

    function isMyNotice($notice) {
        $types = $this->types();
        return in_array($notice->object_type, $types);
    }

    function isMyActivity($activity) {
        $types = $this->types();
        return (count($activity->objects) == 1 &&
                in_array($activity->objects[0]->type, $types));
    }

    /**
     * When a notice is deleted, delete the related objects
     *
     * @param Notice $notice Notice being deleted
     * 
     * @return boolean hook value
     */

    function onNoticeDeleteRelated($notice)
    {
        if ($this->isMyNotice($notice)) {
            $this->deleteRelated($notice);
        }

        return true;
    }

    /**
     * Output the HTML for this kind of object in a list
     *
     * @param NoticeListItem $nli The list item being shown.
     *
     * @return boolean hook value
     */

    function onStartShowNoticeItem($nli)
    {
        if (!$this->isMyNotice($nli->notice)) {
            return true;
        }

        $out = $nli->out;

        $this->showNotice($notice, $out);

        $nli->showNoticeLink();
        $nli->showNoticeSource();
        $nli->showNoticeLocation();
        $nli->showContext();
        $nli->showRepeat();
        
        $out->elementEnd('div');
        
        $nli->showNoticeOptions();

        return false;
    }

    /**
     * Render a notice as one of our objects
     *
     * @param Notice         $notice  Notice to render
     * @param ActivityObject &$object Empty object to fill
     *
     * @return boolean hook value
     */
     
    function onStartActivityObjectFromNotice($notice, &$object)
    {
        if ($this->isMyNotice($notice)) {
            $object = $this->activityObjectFromNotice($notice);
            return false;
        }

        return true;
    }

    /**
     * Handle a posted object from PuSH
     *
     * @param Activity        $activity activity to handle
     * @param Ostatus_profile $oprofile Profile for the feed
     *
     * @return boolean hook value
     */

    function onStartHandleFeedEntryWithProfile($activity, $oprofile)
    {
        if ($this->isMyActivity($activity)) {

            $actor = $oprofile->checkAuthorship($activity);

            if (empty($actor)) {
                throw new ClientException(_('Can\'t get author for activity.'));
            }

            $this->saveNoticeFromActivity($activity, $actor);

            return false;
        }

        return true;
    }

    /**
     * Handle a posted object from Salmon
     *
     * @param Activity $activity activity to handle
     * @param mixed    $target   user or group targeted
     *
     * @return boolean hook value
     */

    function onStartHandleSalmonTarget($activity, $target)
    {
        if ($this->isMyActivity($activity)) {

            $this->log(LOG_INFO, "Checking {$activity->id} as a valid Salmon slap.");

            if ($target instanceof User_group) {
                $uri = $target->getUri();
                if (!in_array($uri, $activity->context->attention)) {
                    throw new ClientException(_("Bookmark not posted ".
                                                "to this group."));
                }
            } else if ($target instanceof User) {
                $uri      = $target->uri;
                $original = null;
                if (!empty($activity->context->replyToID)) {
                    $original = Notice::staticGet('uri', 
                                                  $activity->context->replyToID); 
                }
                if (!in_array($uri, $activity->context->attention) &&
                    (empty($original) ||
                     $original->profile_id != $target->id)) {
                    throw new ClientException(_("Bookmark not posted ".
                                                "to this user."));
                }
            } else {
                throw new ServerException(_("Don't know how to handle ".
                                            "this kind of target."));
            }

            $actor = Ostatus_profile::ensureActivityObjectProfile($activity->actor);

            $this->saveNoticeFromActivity($activity, $actor);

            return false;
        }

        return true;
    }

    /**
     * Handle object posted via AtomPub
     *
     * @param Activity &$activity Activity that was posted
     * @param User     $user      User that posted it
     * @param Notice   &$notice   Resulting notice
     *
     * @return boolean hook value
     */

    function onStartAtomPubNewActivity(&$activity, $user, &$notice)
    {
        if ($this->isMyActivity($activity)) {

            $options = array('source' => 'atompub');

            $this->saveNoticeFromActivity($activity,
                                          $user->getProfile(),
                                          $options);

            return false;
        }

        return true;
    }

    /**
     * Handle object imported from a backup file
     *
     * @param User           $user     User to import for
     * @param ActivityObject $author   Original author per import file
     * @param Activity       $activity Activity to import
     * @param boolean        $trusted  Is this a trusted user?
     * @param boolean        &$done    Is this done (success or unrecoverable error)
     *
     * @return boolean hook value
     */

    function onStartImportActivity($user, $author, $activity, $trusted, &$done)
    {
        if ($this->isMyActivity($activity)) {

            $obj = $activity->objects[0];

            $options = array('uri' => $bookmark->id,
                             'url' => $bookmark->link,
                             'source' => 'restore');

            $saved = $this->saveNoticeFromActivity($activity,
                                                   $user->getProfile(),
                                                   $options);

            if (!empty($saved)) {
                $done = true;
            }

            return false;
        }

        return true;
    }
}
