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
 * @author    Craig Andrews <candrews@integralblue.com>
 * @author    Evan Prodromou <evan@status.net>
 * @author    Jeffery To <jeffery.to@gmail.com>
 * @author    Tom Blankenship <mac65@mac65.com>
 * @author    Mike Cochrane <mikec@mikenz.geek.nz>
 * @author    Robin Millette <robin@millette.info>
 * @author    Zach Copley <zach@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/apiauth.php';
require_once INSTALLDIR . '/lib/mediafile.php';

/**
 * Updates the authenticating user's status (posts a notice).
 *
 * @category API
 * @package  StatusNet
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@status.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Tom Blankenship <mac65@mac65.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <robin@millette.info>
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApiStatusesUpdateAction extends ApiAuthAction
{
    var $source                = null;
    var $status                = null;
    var $in_reply_to_status_id = null;
    var $lat                   = null;
    var $lon                   = null;

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

        $this->user   = $this->auth_user;
        $this->status = $this->trimmed('status');
        $this->source = $this->trimmed('source');
        $this->lat    = $this->trimmed('lat');
        $this->lon    = $this->trimmed('long');

        if (empty($this->source) || in_array($this->source, self::$reserved_sources)) {
            $this->source = 'api';
        }

        $this->in_reply_to_status_id
            = intval($this->trimmed('in_reply_to_status_id'));

        return true;
    }

    /**
     * Handle the request
     *
     * Make a new notice for the update, save it, and show it
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

        // Workaround for PHP returning empty $_POST and $_FILES when POST
        // length > post_max_size in php.ini

        if (empty($_FILES)
            && empty($_POST)
            && ($_SERVER['CONTENT_LENGTH'] > 0)
        ) {
             $msg = _('The server was unable to handle that much POST ' .
                    'data (%s bytes) due to its current configuration.');

            $this->clientError(sprintf($msg, $_SERVER['CONTENT_LENGTH']));
            return;
        }

        if (empty($this->status)) {
            $this->clientError(
                'Client must provide a \'status\' parameter with a value.',
                400,
                $this->format
            );
            return;
        }

        if (empty($this->user)) {
            $this->clientError(_('No such user.'), 404, $this->format);
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

            $upload = null;

            try {
                $upload = MediaFile::fromUpload('media', $this->user);
            } catch (ClientException $ce) {
                $this->clientError($ce->getMessage());
                return;
            }

            if (isset($upload)) {
                $status_shortened .= ' ' . $upload->shortUrl();

                if (Notice::contentTooLong($status_shortened)) {
                    $upload->delete();
                    $msg = _(
                        'Max notice size is %d chars, ' .
                        'including attachment URL.'
                    );
                    $this->clientError(sprintf($msg, Notice::maxContent()));
                }
            }

            $content = html_entity_decode($status_shortened, ENT_NOQUOTES, 'UTF-8');

            $options = array('reply_to' => $reply_to);

            if ($this->user->shareLocation()) {

                $locOptions = Notice::locationOptions($this->lat,
                                                      $this->lon,
                                                      null,
                                                      null,
                                                      $this->user->getProfile());

                $options = array_merge($options, $locOptions);
            }

            $this->notice =
              Notice::saveNew($this->user->id,
                              $content,
                              $this->source,
                              $options);

            if (isset($upload)) {
                $upload->attachToNotice($this->notice);
            }


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
                $this->showSingleXmlStatus($this->notice);
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
