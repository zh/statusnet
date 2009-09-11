<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Plugin to support RSSCloud
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

define('RSSCLOUDPLUGIN_VERSION', '0.1');

class RSSCloudPlugin extends Plugin 
{
    function __construct()
    {
        parent::__construct();
    }
    
    function onInitializePlugin(){
        $this->domain   = common_config('rsscloud', 'domain');
        $this->port     = common_config('rsscloud', 'port');
        $this->path     = common_config('rsscloud', 'path');
        $this->funct    = common_config('rsscloud', 'function');
        $this->protocol = common_config('rsscloud', 'protocol');
        
        // set defaults
        
        if (empty($this->domain)) {
            $this->domain = 'rpc.rsscloud.org';
        }
           
        if (empty($this->port)) {
            $this->port = '5337';
        }
            
        if (empty($this->path)) {
            $this->path = '/rsscloud/pleaseNotify';
        }

        if (empty($this->funct)) {
            $this->funct = '';
        }

        if (empty($this->protocol)) {
            $this->protocol = 'http-post';
        }
    }
    
    function onStartApiRss($action){
        
        $attrs = array('domain'            => $this->domain,
                       'port'              => $this->port,
                       'path'              => $this->path,
                       'registerProcedure' => $this->funct,
                       'protocol'          => $this->protocol);

        // Dipping into XMLWriter to avoid a full end element (</cloud>).
        
        $action->xw->startElement('cloud');
        foreach ($attrs as $name => $value) {
            $action->xw->writeAttribute($name, $value);
        }
        $action->xw->endElement('cloud');
        
    }

    function onEndNoticeSave($notice){

        $user = User::staticGet('id', $notice->profile_id);
        $rss  = common_local_url('api', array('apiaction' => 'statuses',
                                              'method'    => 'user_timeline',
                                              'argument'  => $user->nickname . '.rss'));
 
        $notifier = new CloudNotifier();
        $notifier->notify($rss);
    }
    

}


class CloudNotifier {
 

    function notify($feed) {
        common_debug("CloudNotifier->notify: $feed");
        
        $params = 'url=' . urlencode($feed);
        
        $result = $this->httpPost('http://rpc.rsscloud.org:5337/rsscloud/ping', 
                                  $params);
        
        if ($result) {
            common_debug('success notifying cloud');
        } else {
            common_debug('failure notifying cloud');
        }

    }
    
    function userAgent()
    {
        return 'rssCloudPlugin/' . RSSCLOUDPLUGIN_VERSION .
          ' StatusNet/' . STATUSNET_VERSION;
    }
    
    
    private function httpPost($url, $params) {
        
        
        common_debug('params: ' . var_export($params, true));
                
        $options = array(CURLOPT_URL            => $url,
                         CURLOPT_POST           => true,
                         CURLOPT_POSTFIELDS     => $params,
                         CURLOPT_USERAGENT      => $this->userAgent(),
                         CURLOPT_RETURNTRANSFER => true,
                         CURLOPT_FAILONERROR    => true,
                         CURLOPT_HEADER         => false,
                         CURLOPT_FOLLOWLOCATION => true,
                         CURLOPT_CONNECTTIMEOUT => 5,
                         CURLOPT_TIMEOUT        => 5);

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
    

    
        $info = curl_getinfo($ch);
        
        curl_close($ch);
        
        common_debug('curl response: ' . var_export($response, true));
        common_debug('curl info: ' . var_export($info, true));
        
        if ($info['http_code'] == 200) {
            return true;
        } else {
            return false;
        }
    }
    
}