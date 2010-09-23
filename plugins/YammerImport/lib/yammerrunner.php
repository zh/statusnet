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

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * State machine for running through Yammer import.
 *
 * @package YammerImportPlugin
 * @author Brion Vibber <brion@status.net>
 */
class YammerRunner
{
    private $state;
    private $client;
    private $importer;

    function __construct()
    {
        $state = Yammer_state::staticGet('id', 1);
        if (!$state) {
            common_log(LOG_ERR, "No YammerImport state during import run. Should not happen!");
            throw new ServerException('No YammerImport state during import run.');
        }

        $this->state = $state;
        $this->client = new SN_YammerClient(
            common_config('yammer', 'consumer_key'),
            common_config('yammer', 'consumer_secret'),
            $this->state->oauth_token,
            $this->state->oauth_secret);
        $this->importer = new YammerImporter($client);
    }

    public function iterate()
    {

        switch($state->state)
        {
            case null:
            case 'requesting-auth':
                // Neither of these should reach our background state!
                common_log(LOG_ERR, "Non-background YammerImport state '$state->state' during import run!");
                return false;
            case 'import-users':
                return $this->iterateUsers();
            case 'import-groups':
                return $this->iterateGroups();
            case 'fetch-messages':
                return $this->iterateFetchMessages();
            case 'save-messages':
                return $this->iterateSaveMessages();
            default:
                common_log(LOG_ERR, "Invalid YammerImport state '$state->state' during import run!");
                return false;
        }
    }

    /**
     * Trundle through one 'page' return of up to 50 user accounts retrieved
     * from the Yammer API, importing them as we go.
     *
     * When we run out of users, move on to groups.
     *
     * @return boolean success
     */
    private function iterateUsers()
    {
        $old = clone($this->state);

        $page = intval($this->state->users_page) + 1;
        $data = $this->client->users(array('page' => $page));

        if (count($data) == 0) {
            common_log(LOG_INFO, "Finished importing Yammer users; moving on to groups.");
            $this->state->state = 'import-groups';
        } else {
            foreach ($data as $item) {
                $user = $imp->importUser($item);
                common_log(LOG_INFO, "Imported Yammer user " . $item['id'] . " as $user->nickname ($user->id)");
            }
            $this->state->users_page = $page;
        }
        $this->state->update($old);
        return true;
    }

    /**
     * Trundle through one 'page' return of up to 20 user groups retrieved
     * from the Yammer API, importing them as we go.
     *
     * When we run out of groups, move on to messages.
     *
     * @return boolean success
     */
    private function iterateGroups()
    {
        $old = clone($this->state);

        $page = intval($this->state->groups_page) + 1;
        $data = $this->client->groups(array('page' => $page));

        if (count($data) == 0) {
            common_log(LOG_INFO, "Finished importing Yammer groups; moving on to messages.");
            $this->state->state = 'import-messages';
        } else {
            foreach ($data as $item) {
                $group = $imp->importGroup($item);
                common_log(LOG_INFO, "Imported Yammer group " . $item['id'] . " as $group->nickname ($group->id)");
            }
            $this->state->groups_page = $page;
        }
        $this->state->update($old);
        return true;
    }

    /**
     * Trundle through one 'page' return of up to 20 public messages retrieved
     * from the Yammer API, saving them to our stub table for future import in
     * correct chronological order.
     *
     * When we run out of messages to fetch, move on to saving the messages.
     *
     * @return boolean success
     */
    private function iterateFetchMessages()
    {
        $old = clone($this->state);

        $oldest = intval($this->state->messages_oldest);
        if ($oldest) {
            $params = array('older_than' => $oldest);
        } else {
            $params = array();
        }
        $data = $this->client->messages($params);
        $messages = $data['messages'];

        if (count($data) == 0) {
            common_log(LOG_INFO, "Finished fetching Yammer messages; moving on to save messages.");
            $this->state->state = 'save-messages';
        } else {
            foreach ($data as $item) {
                Yammer_notice_stub::record($item['id'], $item);
                $oldest = $item['id'];
            }
            $this->state->messages_oldest = $oldest;
        }
        $this->state->update($old);
        return true;
    }

    private function iterateSaveMessages()
    {
        $old = clone($this->state);

        $newest = intval($this->state->messages_newest);
        if ($newest) {
            $stub->addWhere('id > ' . $newest);
        }
        $stub->limit(20);
        $stub->find();
        
        if ($stub->N == 0) {
            common_log(LOG_INFO, "Finished saving Yammer messages; import complete!");
            $this->state->state = 'done';
        } else {
            while ($stub->fetch()) {
                $item = json_decode($stub->json_data);
                $notice = $this->importer->importNotice($item);
                common_log(LOG_INFO, "Imported Yammer notice " . $item['id'] . " as $notice->id");
                $newest = $item['id'];
            }
            $this->state->messages_newest = $newest;
        }
        $this->state->update($old);
        return true;
    }

}
