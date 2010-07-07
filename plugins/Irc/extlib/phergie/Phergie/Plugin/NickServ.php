<?php

/**
 * Intercepts and responds to messages from the NickServ agent requesting that
 * the bot authenticate its identify.
 *
 * The password configuration setting should contain the password registered
 * with NickServ for the nick used by the bot.
 */
class Phergie_Plugin_NickServ extends Phergie_Plugin_Abstract {
    /**
     * The name of the nickserv bot
     *
     * @var string
     */
    protected $botNick;

    /**
    * Identify message
    */
    protected $identifyMessage;

    /**
     * Initializes instance variables.
     *
     * @return void
     */
    public function onLoad() {
        $this->getPluginHandler()->getPlugin('Command');

        // Get the name of the NickServ bot, defaults to NickServ
        $this->botNick = $this->config['nickserv.botnick'];
        if (!$this->botNick) $this->botNick = 'NickServ';

        // Get the identify message
        $this->identifyMessage = $this->config['nickserv.identify_message'];
        if (!$this->identifyMessage) $this->identifyMessage = 'This nickname is registered.';
    }

    /**
     * Checks for a notice from NickServ and responds accordingly if it is an
     * authentication request or a notice that a ghost connection has been
     * killed.
     *
     * @return void
     */
    public function onNotice() {
        $event = $this->event;
        if (strtolower($event->getNick()) == strtolower($this->botNick)) {
            $message = $event->getArgument(1);
            $nick = $this->connection->getNick();
            if (strpos($message, $this->identifyMessage) !== false) {
                $password = $this->config['nickserv.password'];
                if (!empty($password)) {
                    $this->doPrivmsg($this->botNick, 'IDENTIFY ' . $password);
                }
                unset($password);
            } elseif (preg_match('/^.*' . $nick . '.* has been killed/', $message)) {
                $this->doNick($nick);
            }
        }
    }

    /**
     * Checks to see if the original Nick has quit, if so, take the name back
     *
     * @return void
     */
    public function onQuit() {
        $eventnick = $this->event->getNick();
        $nick = $this->connection->getNick();
        if ($eventnick == $nick) {
            $this->doNick($nick);
        }
    }

    /**
     * Changes the in-memory configuration setting for the bot nick if it is
     * successfully changed.
     *
     * @return void
     */
    public function onNick() {
        $event = $this->event;
        $connection = $this->connection;
        if ($event->getNick() == $connection->getNick()) {
            $connection->setNick($event->getArgument(0));
        }
    }

    /**
     * Provides a command to terminate ghost connections.
     *
     * @return void
     */
    public function onDoGhostbust() {
        $event = $this->event;
        $user = $event->getNick();
        $conn = $this->connection;
        $nick = $conn->getNick();

        if ($nick != $this->config['connections'][$conn->getHost()]['nick']) {
            $password = $this->config['nickserv.password'];
            if (!empty($password)) {
                $this->doPrivmsg($this->event->getSource(), $user . ': Attempting to ghost ' . $nick .'.');
                $this->doPrivmsg(
                    $this->botNick,
                    'GHOST ' . $nick . ' ' . $password,
                    true
                );
            }
            unset($password);
        }
    }

    /**
     * Automatically send the GHOST command if the Nickname is in use
     *
     * @return void
     */
    public function onResponse() {
        if ($this->event->getCode() == Phergie_Event_Response::ERR_NICKNAMEINUSE) {
            $password = $this->config['nickserv.password'];
            if (!empty($password)) {
                $this->doPrivmsg(
                    $this->botNick,
                    'GHOST ' . $this->connection->getNick() . ' ' . $password,
                    true
                );
            }
            unset($password);
        }
    }

    /**
     * The server sent a KILL request, so quit the server
     *
     * @return void
     */
    public function onKill() {
        $this->doQuit($this->event->getArgument(1));
    }
}
