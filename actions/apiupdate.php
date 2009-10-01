<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Post a notice (update your status) through the API
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
 * @category  API
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/apibareauth.php';

/**
 * Updates the authenticating user's status (posts a notice).
 *
 * @category API
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiUpdateAction extends ApiAuthAction
{

    var $user                  = null;
    var $source                = null;
    var $status                = null;
    var $in_reply_to_status_id = null;
    var $format                = null;

    static $reserved_sources = array('web', 'omb', 'mail', 'xmpp', 'api');

    /**
     * Take arguments for running
     *
     * @param array $args $_REQUEST args
     *
     * @return boolean success flag
     *
     */

    function prepare($args)
    {
        parent::prepare($args);

        if ($this->requiresAuth()) {
            if ($this->checkBasicAuthUser() == false) {
                return false;
            }
        }

        $this->user = $this->auth_user;

        if (empty($this->user)) {
            $this->clientError(_('No such user!'), 404, $this->format);
            return false;
        }

        $this->status = $this->trimmed('status');

        if (empty($this->status)) {
            $this->clientError(
                'Client must provide a \'status\' parameter with a value.',
                400,
                $this->format
            );

            return false;
        }

        $this->source = $this->trimmed('source');

        if (empty($this->source) || in_array($source, $this->reserved_sources)) {
            $this->source = 'api';
        }

        $this->format = $this->arg('format');

        $this->in_reply_to_status_id
            = intval($this->trimmed('in_reply_to_status_id'));

        return true;
    }

    /**
     * Handle the request
     *
     * Just show the notices
     *
     * @param array $args $_REQUEST data (unused)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(
                _('This method requires a POST.'),
                400, $this->format
            );
            return;
        }

        $status_shortened = common_shorten_links($this->status);

        if (Notice::contentTooLong($status_shortened)) {

            // Note: Twitter truncates anything over 140, flags the status
            // as "truncated."

            $this->clientError(
                sprintf(
                    _('That\'s too long. Max notice size is %d chars.'),
                    Notice::maxContent()
                ),
                406,
                $this->format
            );

            return;
        }

        // Check for commands

        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($this->user, $status_shortened);

        if ($cmd) {

            if ($this->supported($cmd)) {
                $cmd->execute(new Channel());
            }

            // Cmd not supported?  Twitter just returns your latest status.
            // And, it returns your last status whether the cmd was successful
            // or not!

            $this->notice = $this->user->getCurrentNotice();

        } else {

            $reply_to = null;

            if (!empty($this->in_reply_to_status_id)) {

                // Check whether notice actually exists

                $reply = Notice::staticGet($this->in_reply_to_status_id);

                if ($reply) {
                    $reply_to = $this->in_reply_to_status_id;
                } else {
                    $this->clientError(
                        _('Not found'),
                        $code = 404,
                        $this->format
                    );
                    return;
                }
            }

            $this->notice = Notice::saveNew(
                $this->user->id,
                html_entity_decode($this->status, ENT_NOQUOTES, 'UTF-8'),
                $this->source,
                1,
                $reply_to
            );

            common_broadcast_notice($this->notice);
        }

        $this->showNotice();
    }

    /**
     * Show the resulting notice
     *
     * @return void
     */

    function showNotice()
    {
        if (!empty($this->notice)) {
            if ($this->format == 'xml') {
                $this->show_single_xml_status($this->notice);
            } elseif ($this->format == 'json') {
                $this->show_single_json_status($this->notice);
            }
        }
    }

    /**
     * Is this command supported when doing an update from the API?
     *
     * @param string $cmd the command to check for
     *
     * @return boolean true or false
     */

    function supported($cmd)
    {
        static $cmdlist = array('MessageCommand', 'SubCommand', 'UnsubCommand',
            'FavCommand', 'OnCommand', 'OffCommand');

        if (in_array(get_class($cmd), $cmdlist)) {
            return true;
        }

        return false;
    }

}
