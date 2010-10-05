<?php
/**
 * StatusNet, the distributed open-source microblogging tool
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
 * @author    Brion Vibber <brion@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class OAuthData
{
    public $consumer_key, $consumer_secret, $token, $token_secret;
}

/**
 *
 */
abstract class JsonStreamReader
{
    const CRLF = "\r\n";

    public $id;
    protected $socket = null;
    protected $state = 'init'; // 'init', 'connecting', 'waiting', 'headers', 'active'

    public function __construct()
    {
        $this->id = get_class($this) . '.' . substr(md5(mt_rand()), 0, 8);
    }

    /**
     * Starts asynchronous connect operation...
     *
     * @param <type> $url
     */
    public function connect($url)
    {
        common_log(LOG_DEBUG, "$this->id opening connection to $url");

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme == 'http') {
            $rawScheme = 'tcp';
        } else if ($scheme == 'https') {
            $rawScheme = 'ssl';
        } else {
            throw new ServerException('Invalid URL scheme for HTTP stream reader');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);
        if (!$port) {
            if ($scheme == 'https') {
                $port = 443;
            } else {
                $port = 80;
            }
        }

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            $path .= '?' . $query;
        }

        $errno = $errstr = null;
        $timeout = 5;
        //$flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $flags = STREAM_CLIENT_CONNECT;
        // @fixme add SSL params
        $this->socket = stream_socket_client("$rawScheme://$host:$port", $errno, $errstr, $timeout, $flags);

        $this->send($this->httpOpen($host, $path));

        stream_set_blocking($this->socket, false);
        $this->state = 'waiting';
    }

    /**
     * Send some fun data off to the server.
     *
     * @param string $buffer
     */
    function send($buffer)
    {
        fwrite($this->socket, $buffer);
    }

    /**
     * Read next packet of data from the socket.
     *
     * @return string
     */
    function read()
    {
        $buffer = fread($this->socket, 65536);
        return $buffer;
    }

    protected function httpOpen($host, $path)
    {
        $lines = array(
            "GET $path HTTP/1.1",
            "Host: $host",
            "User-Agent: StatusNet/" . STATUSNET_VERSION . " (TwitterBridgePlugin)",
            "Connection: close",
            "",
            ""
        );
        return implode(self::CRLF, $lines);
    }

    /**
     * Close the current connection, if open.
     */
    public function close()
    {
        if ($this->isConnected()) {
            common_log(LOG_DEBUG, "$this->id closing connection.");
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Are we currently connected?
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->socket !== null;
    }

    /**
     * Send any sockets we're listening on to the IO manager
     * to wait for input.
     *
     * @return array of resources
     */
    public function getSockets()
    {
        if ($this->isConnected()) {
            return array($this->socket);
        }
        return array();
    }

    /**
     * Take a chunk of input over the horn and go go go! :D
     * @param string $buffer
     */
    function handleInput($socket)
    {
        if ($this->socket !== $socket) {
            throw new Exception('Got input from unexpected socket!');
        }

        $buffer = $this->read();
        switch ($this->state)
        {
            case 'waiting':
                $this->handleInputWaiting($buffer);
                break;
            case 'headers':
                $this->handleInputHeaders($buffer);
                break;
            case 'active':
                $this->handleInputActive($buffer);
                break;
            default:
                throw new Exception('Invalid state in handleInput: ' . $this->state);
        }
    }

    function handleInputWaiting($buffer)
    {
        common_log(LOG_DEBUG, "$this->id Does this happen? " . $buffer);
        $this->state = 'headers';
        $this->handleInputHeaders($buffer);
    }

    function handleInputHeaders($buffer)
    {
        $lines = explode(self::CRLF, $buffer);
        foreach ($lines as $line) {
            if ($this->state == 'headers') {
                if ($line == '') {
                    $this->state = 'active';
                    common_log(LOG_DEBUG, "$this->id connection is active!");
                } else {
                    common_log(LOG_DEBUG, "$this->id read HTTP header: $line");
                    $this->responseHeaders[] = $line;
                }
            } else if ($this->state == 'active') {
                $this->handleLineActive($line);
            }
        }
    }

    function handleInputActive($buffer)
    {
        // One JSON object on each line...
        // Will we always deliver on packet boundaries?
        $lines = explode("\n", $buffer);
        foreach ($lines as $line) {
            $this->handleLineActive($line);
        }
    }

    function handleLineActive($line)
    {
        if ($line == '') {
            // Server sends empty lines as keepalive.
            return;
        }
        $data = json_decode($line, true);
        if ($data) {
            $this->handleJson($data);
        } else {
            common_log(LOG_ERR, "$this->id received bogus JSON data: " . $line);
        }
    }

    abstract function handleJson(array $data);
}
