#!/usr/bin/env php
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

# Abort if called from a web server
if (isset($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER)) {
    print "This script must be run from the command line\n";
    exit();
}

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));
define('LACONICA', true);

require_once(INSTALLDIR . '/lib/common.php');
require_once(INSTALLDIR . '/lib/mail.php');
require_once('Mail/mimeDecode.php');

# FIXME: we use both Mail_mimeDecode and mailparse
# Need to move everything to mailparse

class MailerDaemon
{

    function __construct()
    {
    }

    function handle_message($fname='php://stdin')
    {
        list($from, $to, $msg) = $this->parse_message($fname);
        if (!$from || !$to || !$msg) {
            $this->error(null, _('Could not parse message.'));
        }
        common_log(LOG_INFO, "Mail from $from to $to: " .substr($msg, 0, 20));
        $user = $this->user_from($from);
        if (!$user) {
            $this->error($from, _('Not a registered user.'));
            return false;
        }
        if (!$this->user_match_to($user, $to)) {
            $this->error($from, _('Sorry, that is not your incoming email address.'));
            return false;
        }
        if (!$user->emailpost) {
            $this->error($from, _('Sorry, no incoming email allowed.'));
            return false;
        }
        $response = $this->handle_command($user, $from, $msg);
        if ($response) {
            return true;
        }
        $msg = $this->cleanup_msg($msg);
        $err = $this->add_notice($user, $msg);
        if (is_string($err)) {
            $this->error($from, $err);
            return false;
        } else {
            return true;
        }
    }

    function error($from, $msg)
    {
        file_put_contents("php://stderr", $msg . "\n");
        exit(1);
    }

    function user_from($from_hdr)
    {
        $froms = mailparse_rfc822_parse_addresses($from_hdr);
        if (!$froms) {
            return null;
        }
        $from = $froms[0];
        $addr = common_canonical_email($from['address']);
        $user = User::staticGet('email', $addr);
        if (!$user) {
            $user = User::staticGet('smsemail', $addr);
        }
        return $user;
    }

    function user_match_to($user, $to_hdr)
    {
        $incoming = $user->incomingemail;
        $tos = mailparse_rfc822_parse_addresses($to_hdr);
        foreach ($tos as $to) {
            if (strcasecmp($incoming, $to['address']) == 0) {
                return true;
            }
        }
        return false;
    }

    function handle_command($user, $from, $msg)
    {
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $msg);
        if ($cmd) {
            $cmd->execute(new MailChannel($from));
            return true;
        }
        return false;
    }

    function respond($from, $to, $response)
    {

        $headers['From'] = $to;
        $headers['To'] = $from;
        $headers['Subject'] = "Command complete";

        return mail_send(array($from), $headers, $response);
    }

    function log($level, $msg)
    {
        common_log($level, 'MailDaemon: '.$msg);
    }

    function add_notice($user, $msg)
    {
        $notice = Notice::saveNew($user->id, $msg, 'mail');
        if (is_string($notice)) {
            $this->log(LOG_ERR, $notice);
            return $notice;
        }
        common_broadcast_notice($notice);
        $this->log(LOG_INFO,
                   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
        return true;
    }

    function parse_message($fname)
    {
        $contents = file_get_contents($fname);
        $parsed = Mail_mimeDecode::decode(array('input' => $contents,
                                                'include_bodies' => true,
                                                'decode_headers' => true,
                                                'decode_bodies' => true));
        if (!$parsed) {
            return null;
        }

        $from = $parsed->headers['from'];

        $to = $parsed->headers['to'];

        $type = $parsed->ctype_primary . '/' . $parsed->ctype_secondary;

        if ($parsed->ctype_primary == 'multipart') {
            foreach ($parsed->parts as $part) {
                if ($part->ctype_primary == 'text' &&
                    $part->ctype_secondary == 'plain') {
                    $msg = $part->body;
                    break;
                }
            }
        } else if ($type == 'text/plain') {
            $msg = $parsed->body;
        } else {
            $this->unsupported_type($type);
        }

        return array($from, $to, $msg);
    }

    function unsupported_type($type)
    {
        $this->error(null, "Unsupported message type: " . $type);
    }

    function cleanup_msg($msg)
    {
        $lines = explode("\n", $msg);

        $output = '';

        foreach ($lines as $line) {
            // skip quotes
            if (preg_match('/^\s*>.*$/', $line)) {
                continue;
            }
            // skip start of quote
            if (preg_match('/^\s*On.*wrote:\s*$/', $line)) {
                continue;
            }
            // probably interesting to someone, not us
            if (preg_match('/^\s*Sent via/', $line)) {
                continue;
            }
            // skip everything after a sig
            if (preg_match('/^\s*--+\s*$/', $line) ||
                preg_match('/^\s*__+\s*$/', $line))
            {
                break;
            }
            // skip everything after Outlook quote
            if (preg_match('/^\s*-+\s*Original Message\s*-+\s*$/', $line)) {
                break;
            }
            // skip everything after weird forward
            if (preg_match('/^\s*Begin\s+forward/', $line)) {
                break;
            }

            $output .= ' ' . $line;
        }

        preg_replace('/\s+/', ' ', $output);
        return trim($output);
    }
}

$md = new MailerDaemon();
$md->handle_message('php://stdin');
