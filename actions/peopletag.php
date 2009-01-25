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

require_once INSTALLDIR.'/lib/profilelist.php';

class PeopletagAction extends Action
{
    
    var $tag = null;
    var $page = null;
        
    function handle($args)
    {
        parent::handle($args);    
        
        parent::prepare($args);

        $this->tag = $this->trimmed('tag');

        if (!common_valid_profile_tag($this->tag)) {
            $this->clientError(sprintf(_('Not a valid people tag: %s'), $this->tag));
            return;
        }

        $this->page = $this->trimmed('page');

        if (!$this->page) {
            $this->page = 1;
        }
        
        $this->showPage();
    }
    
    function showContent()
    {
        
        $profile = new Profile();

        $offset = ($page-1)*PROFILES_PER_PAGE;
        $limit = PROFILES_PER_PAGE + 1;
        
        if (common_config('db','type') == 'pgsql') {
            $lim = ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $lim = ' LIMIT ' . $offset . ', ' . $limit;
        }

        # XXX: memcached this
        
        $qry =  'SELECT profile.* ' .
                'FROM profile JOIN profile_tag ' .
                'ON profile.id = profile_tag.tagger ' .
                'WHERE profile_tag.tagger = profile_tag.tagged ' .
                'AND tag = "%s" ' .
                'ORDER BY profile_tag.modified DESC';
        
        $profile->query(sprintf($qry, $this->tag, $lim));

        $pl = new ProfileList($profile, null, $this);
        $cnt = $pl->show();
                
        $this->pagination($this->page > 1,
                          $cnt > PROFILES_PER_PAGE,
                          $this->page,
                          $this->trimmed('action'),
                          array('tag' => $this->tag));
    }
    
    function title() 
    {
        return sprintf( _('Users self-tagged with %s - page %d'), $this->tag, $this->page);
    }
    
}
