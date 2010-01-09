#!/usr/bin/env php
<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/../..'));

$shortoptions = 'fi::';
$longoptions = array('id::', 'foreground');

$helptext = <<<END_OF_IMAP_HELP
Daemon script for receiving new notices from users via a mail box (IMAP, POP3, etc)

    -i --id           Identity (default none)
    -f --foreground   Stay in the foreground (default background)

END_OF_IMAP_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once INSTALLDIR . '/lib/common.php';
require_once INSTALLDIR . '/lib/daemon.php';
require_once INSTALLDIR.'/lib/mailhandler.php';

class IMAPDaemon extends Daemon
{   
    function __construct($resource=null, $daemonize=true, $attrs)
    {
        parent::__construct($daemonize);

        foreach ($attrs as $attr=>$value)
        {
            $this->$attr = $value;
        }

        $this->log(LOG_INFO, "INITIALIZE IMAPDaemon {" . $this->name() . "}");
    }

    function name()
    {
        return strtolower('imapdaemon.'.$this->user.'.'.crc32($this->mailbox));
    }

    function run()
    {
        $this->connect();
        while(true)
        {
            if(imap_ping($this->conn) || $this->connect())
            {
                $this->check_mailbox();
            }
            sleep($this->poll_frequency);
        }
    }

    function check_mailbox()
    {
        $count = imap_num_msg($this->conn);
        $this->log(LOG_INFO, "Found $count messages");
        if($count > 0){
            $handler = new IMAPMailHandler();
            for($i=1; $i <= $count; $i++)
            {
                $rawmessage = imap_fetchheader($this->conn, $count, FT_PREFETCHTEXT) . imap_body($this->conn, $i);
                $handler->handle_message($rawmessage);
                imap_delete($this->conn, $i);
            }
            imap_expunge($this->conn);
            $this->log(LOG_INFO, "Finished processing messages");
        }
    }

    function log($level, $msg)
    {
        $text = $this->name() . ': '.$msg;
        common_log($level, $text);
        if (!$this->daemonize)
        {
            $line = common_log_line($level, $text);
            echo $line;
            echo "\n";
        }
    }

    function connect()
    {
        $this->conn = imap_open($this->mailbox, $this->user, $this->password);
        if($this->conn){
            $this->log(LOG_INFO, "Connected");
            return true;
        }else{
            $this->log(LOG_INFO, "Failed to connect: " . imap_last_error());
            return false;
        }
    }
}

class IMAPMailHandler extends MailHandler
{
    function error($from, $msg)
    {
        $this->log(LOG_INFO, "Error: $from $msg");
        $headers['To'] = $from;
        $headers['Subject'] = "Error";

        return mail_send(array($from), $headers, $msg);
    }
}

if (have_option('i', 'id')) {
    $id = get_option_value('i', 'id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$foreground = have_option('f', 'foreground');

foreach(ImapPlugin::$instances as $pluginInstance){

    $daemon = new IMAPDaemon($id, !$foreground, array(
        'mailbox' => $pluginInstance->mailbox,
        'user' => $pluginInstance->user,
        'password' => $pluginInstance->password,
        'poll_frequency' => $pluginInstance->poll_frequency
    ));

    $daemon->runOnce();

}
