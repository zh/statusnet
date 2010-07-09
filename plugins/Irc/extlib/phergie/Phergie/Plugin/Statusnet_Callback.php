<?php
/**
 * StatusNet - the distributed open-source microblogging tool
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
 * Calls the given Statusnet IM architecture enqueuing method to enqueue
 * a new incoming message
 *
 * @category  Phergie
 * @package   Phergie_Plugin_Statusnet_Callback
 * @author    Luke Fitzgerald <lw.fitzgerald@googlemail.com
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class Phergie_Plugin_Statusnet_Callback extends Phergie_Plugin_Abstract {
    /**
    * Callback details
    *
    * @var array
    */
    protected $callback;

    /**
    * Load callback from config
    */
    public function onLoad() {
        $callback = $this->config['statusnet_callback.callback'];
        if (is_callable($callback)) {
            $this->callback = $callback;
        } else {
            $this->callback = NULL;
        }
    }

    /**
     * Passes incoming messages to StatusNet
     *
     * @return void
     */
    public function onPrivmsg() {
        if ($this->callback !== NULL) {
            $event = $this->getEvent();
            $source = $event->getSource();
            $message = trim($event->getText());

            call_user_func($this->callback, array('sender' => $source, 'message' => $message);
        }
    }
}
