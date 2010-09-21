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
 * Basic client class for Yammer's OAuth/JSON API.
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class YammerImporter
{
    function messageToNotice($message)
    {
        $messageId = $message['id'];
        $messageUrl = $message['url'];

        $profile = $this->findImportedProfile($message['sender_id']);
        $content = $message['body']['plain'];
        $source = 'yammer';
        $options = array();

        if ($message['replied_to_id']) {
            $replyto = $this->findImportedNotice($message['replied_to_id']);
            if ($replyto) {
                $options['replyto'] = $replyto;
            }
        }
        $options['created'] = common_sql_date(strtotime($message['created_at']));

        // Parse/save rendered text?
        // Save liked info?
        // @todo attachments?

        return array('orig_id' => $messageId,
                     'profile' => $profile,
                     'content' => $content,
                     'source' => $source,
                     'options' => $options);
    }

    function findImportedProfile($userId)
    {
        // @fixme
        return $userId;
    }

    function findImportedNotice($messageId)
    {
        // @fixme
        return $messageId;
    }
}