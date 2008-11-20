<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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

if (!defined('LACONICA')) { exit(1); }

class SearchEngine {
    protected $profile;

    function __construct($profile) {
        $this->profile = $profile;
    }

    function query($q) {
    }

    function limit($offset, $count) {
        return $this->profile->limit($offset, $count);
    }
}

class SphinxSearch extends SearchEngine {
    private $sphinx;

    function __construct($profile) {
        parent::__construct($profile);
        $this->sphinx = new SphinxClient;
        $this->sphinx->setServer(common_config('sphinx', 'server'), common_config('sphinx', 'port'));
    }

    function limit($offset, $count) {
        $this->sphinx->setLimits($offset, $count);
        $this->profile->limit($offset, $count);
    }

    function query($q) {
        $result = $this->sphinx->query($q);
        if (!isset($result['matches'])) return false;
        $id_set = join(', ', array_keys($result['matches']));
        return $this->profile->whereAdd("id in ($id_set)");
     }
}

class MySQLSearch extends SearchEngine {
    function query($q) {
        return $this->profile->whereAdd('MATCH(nickname, fullname, location, bio, homepage) ' .
						   'against (\''.addslashes($q).'\')');
    }
}

class PGSearch extends SearchEngine {
    function query($q) {
        $this->profile->whereAdd('textsearch @@ plainto_tsquery(\''.addslashes($q).'\')');
    }
}

