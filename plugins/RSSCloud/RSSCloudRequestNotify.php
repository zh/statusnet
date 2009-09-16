<?php

/**
 * Notifier
 *
 * PHP version 5
 *
 * @category Plugin
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

class RSSCloudRequestNotifyAction extends Action 
{

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */
    function prepare($args)
    {
        parent::prepare($args);
        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            showResult(false, 'Request must be POST.');
        }
        
        $ip      = $_SERVER['REMOTE_ADDR'];
        $missing = array();
        $port    = $this->arg('port');
        
        if (empty($this->port)) {
            $missing[] = 'port';
        }
        
        $path = $this->arg('path');

        if (empty($this->path)) {
            $missing[] = 'path';
        }
        
        $protocol = $this->arg('protocol');

        if (empty($this->protocol)) {
            $missing[] = 'protocol';
        }
        
        if (empty($this->notifyProcedure)) {
            $missing[] = 'notifyProcedure';
        }
        
        if (!empty($missing)) {
            $msg = 'The following parameters were missing from the request body: ' .
              implode(',', $missing) . '.';
            $this->showResult(false, $msg);
        }
        
        $feeds = $this->getFeeds();
        
        if (empty($feeds)) {
            $this->showResult(false, 
                              'You must provide at least one feed url (url1, url2, url3 ... urlN).');
        }
        
        $endpoint = $ip . ':' . $port . $path;
        
        foreach ($feeds as $feed) {
            
        }
        
        
    }
    
    
    function getFeeds()
    {
        $feeds = array();
        
        foreach ($this->args as $key => $feed ) {
            if (preg_match('|url\d+|', $key)) {
                
                // XXX: validate feeds somehow and kick bad ones out
                
                $feeds[] = $feed;
            }
        }
        
        return $feeds;
    }
    
    
    function checkNotifyHandler() 
    {
        
    }
    
    function validateFeed() 
    {
    }
    
    function showResult($success, $msg) 
    {
        $this->startXML();
        $this->elementStart('notifyResult', array('success' => ($success) ? 'true' : 'false',
                                                  'msg'     => $msg));
        $this->endXML();
        
    }
    
    
}



