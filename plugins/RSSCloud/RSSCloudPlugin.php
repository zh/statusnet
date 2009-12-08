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

    function onInitializePlugin()
    {
        $this->domain   = common_config('rsscloud', 'domain');
        $this->port     = common_config('rsscloud', 'port');
        $this->path     = common_config('rsscloud', 'path');
        $this->funct    = common_config('rsscloud', 'function');
        $this->protocol = common_config('rsscloud', 'protocol');

        // set defaults

        $local_server = parse_url(common_path('/main/rsscloud/request_notify'));

        if (empty($this->domain)) {
            $this->domain = $local_server['host'];
        }

        if (empty($this->port)) {
            $this->port = '80';
        }

        if (empty($this->path)) {
            $this->path = '/main/rsscloud/request_notify';
        }

        if (empty($this->funct)) {
            $this->funct = '';
        }

        if (empty($this->protocol)) {
            $this->protocol = 'http-post';
        }
    }

    /**
     * Add RSSCloud-related paths to the router table
     *
     * Hook for RouterInitialized event.
     *
     * @return boolean hook return
     */

    function onRouterInitialized(&$m)
    {
        $m->connect('/main/rsscloud/request_notify', array('action' => 'RSSCloudRequestNotify'));

        // XXX: This is just for end-to-end testing
        $m->connect('/main/rsscloud/notify', array('action' => 'LoggingAggregator'));

        return true;
    }

    function onAutoload($cls)
    {
        switch ($cls)
        {
         case 'RSSCloudSubscription':
            include_once(INSTALLDIR . '/plugins/RSSCloud/RSSCloudSubscription.php');
            return false;
         case 'RSSCloudNotifier':
            include_once(INSTALLDIR . '/plugins/RSSCloud/RSSCloudNotifier.php');
            return false;
         case 'RSSCloudRequestNotifyAction':
         case 'LoggingAggregatorAction':
            include_once(INSTALLDIR . '/plugins/RSSCloud/' . mb_substr($cls, 0, -6) . '.php');
            return false;
         default:
            return true;
        }
    }

    function onStartApiRss($action)
    {
        // XXX: Add RSS 1.0 user feeds

        if (get_class($action) == 'ApiTimelineUserAction') {

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

            $action->xw->endElement();
        }
    }

    /**
     * Add an RSSCloud queue item for each notice
     *
     * @param Notice $notice      the notice
     * @param array  &$transports the list of transports (queues)
     *
     * @return boolean hook return
     */
    function onStartEnqueueNotice($notice, &$transports)
    {
        array_push($transports, 'rsscloud');
        return true;
    }

    /**
     * broadcast the message when not using queuehandler
     *
     * @param Notice &$notice the notice
     * @param array  $queue   destination queue
     *
     * @return boolean hook return
     */
    function onUnqueueHandleNotice(&$notice, $queue)
    {
        if (($queue == 'rsscloud') && ($this->_isLocal($notice))) {

            common_debug('broadcasting rssCloud bound notice ' . $notice->id);

            $profile = $notice->getProfile();

            $notifier = new RSSCloudNotifier();
            $notifier->notify($profile);

            return false;
        }

        return true;
    }

    /**
     * Determine whether the notice was locally created
     *
     * @param Notice $notice
     *
     * @return boolean locality
     */
    function _isLocal($notice)
    {
        return ($notice->is_local == Notice::LOCAL_PUBLIC ||
                $notice->is_local == Notice::LOCAL_NONPUBLIC);
    }

    function onCheckSchema() {
        $schema = Schema::get();
        $schema->ensureTable('rsscloud_subscription',
                             array(new ColumnDef('subscribed', 'integer',
                                                 null, false, 'PRI'),
                                   new ColumnDef('url', 'varchar',
                                                 '255', false, 'PRI'),
                                   new ColumnDef('failures', 'integer',
                                                 null, false, null, 0),
                                   new ColumnDef('created', 'datetime',
                                                 null, false),
                                   new ColumnDef('modified', 'timestamp',
                                                 null, false, null,
                                                 'CURRENT_TIMESTAMP',
                                                 'on update CURRENT_TIMESTAMP')
                                  )
                            );
         return true;
    }

    function onGetValidDaemons($daemons)
    {
        array_push($daemons, INSTALLDIR .
                   '/plugins/RSSCloud/RSSCloudQueueHandler.php');
        return true;
    }

}

