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
 * @package OStatusPlugin
 * @author James Walker <james@status.net>
 */

if (!defined('STATUSNET')) {
    exit(1);
}

class GroupsalmonAction extends SalmonAction
{
    var $group = null;

    function prepare($args)
    {
        parent::prepare($args);

        $id = $this->trimmed('id');

        if (!$id) {
            $this->clientError(_('No ID.'));
        }

        $this->group = User_group::staticGet('id', $id);

        if (empty($this->group)) {
            $this->clientError(_('No such group.'));
        }

        return true;
    }

    /**
     * We've gotten a post event on the Salmon backchannel, probably a reply.
     */

    function handlePost()
    {
        switch ($this->act->object->type) {
        case ActivityObject::ARTICLE:
        case ActivityObject::BLOGENTRY:
        case ActivityObject::NOTE:
        case ActivityObject::STATUS:
        case ActivityObject::COMMENT:
            break;
        default:
            throw new ClientException("Can't handle that kind of post.");
        }

        // Notice must be to the attention of this group

        $context = $this->act->context;

        if (empty($context->attention)) {
            throw new ClientException("Not to the attention of anyone.");
        } else {
            $uri = common_local_url('groupbyid', array('id' => $this->group->id));
            if (!in_array($context->attention, $uri)) {
                throw new ClientException("Not to the attention of this group.");
            }
        }

        $profile = $this->ensureProfile();
        // @fixme save the post
    }

    /**
     * We've gotten a follow/subscribe notification from a remote user.
     * Save a subscription relationship for them.
     */

    function handleFollow()
    {
        $this->handleJoin(); // ???
    }

    function handleUnfollow()
    {
    }

    /**
     * A remote user joined our group.
     */

    function handleJoin()
    {
    }

}
