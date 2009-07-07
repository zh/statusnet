#!/usr/bin/env php
<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

define('INSTALLDIR', realpath(dirname(__FILE__) . '/..'));

$helptext = <<<END_OF_HELP
Script for converting mail messages into notices. Takes message body
as STDIN.

END_OF_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

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
        list($from, $to, $msg, $attachments) = $this->parse_message($fname);
        if (!$from || !$to || !$msg) {
            $this->error(null, _('Could not parse message.'));
        }
        common_log(LOG_INFO, "Mail from $from to $to with ".count($attachments) .' attachment(s): ' .substr($msg, 0, 20));
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
        $msg = common_shorten_links($msg);
        if (mb_strlen($msg) > 140) {
            $this->error($from,_('That\'s too long. '.
                'Max notice size is 140 chars.'));
        }
        $fileRecords = array();
        foreach($attachments as $attachment){
            $mimetype = $this->getUploadedFileType($attachment);
            $stream  = stream_get_meta_data($attachment);
            if (!$this->isRespectsQuota($user,filesize($stream['uri']))) {
                die('error() should trigger an exception before reaching here.');
            }
            $filename = $this->saveFile($user, $attachment,$mimetype);
            
            fclose($attachment);
            
            if (empty($filename)) {
                $this->error($from,_('Couldn\'t save file.'));
            }

            $fileRecord = $this->storeFile($filename, $mimetype);
            $fileRecords[] = $fileRecord;
            $fileurl = common_local_url('attachment',
                array('attachment' => $fileRecord->id));

            // not sure this is necessary -- Zach
            $this->maybeAddRedir($fileRecord->id, $fileurl);

            $short_fileurl = common_shorten_url($fileurl);
            $msg .= ' ' . $short_fileurl;

            if (mb_strlen($msg) > 140) {
                $this->deleteFile($filename);
                $this->error($from,_('Max notice size is 140 chars, including attachment URL.'));
            }

            // Also, not sure this is necessary -- Zach
            $this->maybeAddRedir($fileRecord->id, $short_fileurl);
        }

        $err = $this->add_notice($user, $msg, $fileRecords);
        if (is_string($err)) {
            $this->error($from, $err);
            return false;
        } else {
            return true;
        }
    }

    function saveFile($user, $attachment, $mimetype) {

        $filename = File::filename($user->getProfile(), "email", $mimetype);

        $filepath = File::path($filename);

        $stream  = stream_get_meta_data($attachment);
        if (copy($stream['uri'], $filepath) && chmod($filepath,0664)) {
            return $filename;
        } else {   
            $this->error(null,_('File could not be moved to destination directory.' . $stream['uri'] . ' ' . $filepath));
        }
    }

    function storeFile($filename, $mimetype) {

        $file = new File;
        $file->filename = $filename;

        $file->url = File::url($filename);

        $filepath = File::path($filename);

        $file->size = filesize($filepath);
        $file->date = time();
        $file->mimetype = $mimetype;

        $file_id = $file->insert();

        if (!$file_id) {
            common_log_db_error($file, "INSERT", __FILE__);
            $this->error(null,_('There was a database error while saving your file. Please try again.'));
        }

        return $file;
    }

    function maybeAddRedir($file_id, $url)
    {   
        $file_redir = File_redirection::staticGet('url', $url);

        if (empty($file_redir)) {
            $file_redir = new File_redirection;
            $file_redir->url = $url;
            $file_redir->file_id = $file_id;

            $result = $file_redir->insert();

            if (!$result) {
                common_log_db_error($file_redir, "INSERT", __FILE__);
                $this->error(null,_('There was a database error while saving your file. Please try again.'));
            }
        }
    }

    function getUploadedFileType($fileHandle) {
        require_once 'MIME/Type.php';

        $cmd = &PEAR::getStaticProperty('MIME_Type', 'fileCmd');
        $cmd = common_config('attachments', 'filecommand');

        $stream  = stream_get_meta_data($fileHandle);
        $filetype = MIME_Type::autoDetect($stream['uri']);
        if (in_array($filetype, common_config('attachments', 'supported'))) {
            return $filetype;
        }
        $media = MIME_Type::getMedia($filetype);
        if ('application' !== $media) {
            $hint = sprintf(_(' Try using another %s format.'), $media);
        } else {
            $hint = '';
        }
        $this->error(null,sprintf(
            _('%s is not a supported filetype on this server.'), $filetype) . $hint);
    }

    function isRespectsQuota($user,$fileSize) {
        $file = new File;
        $ret = $file->isRespectsQuota($user,$fileSize);
        if (true === $ret) return true;
        $this->error(null,$ret);
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

    function add_notice($user, $msg, $fileRecords)
    {
        $notice = Notice::saveNew($user->id, $msg, 'mail');
        if (is_string($notice)) {
            $this->log(LOG_ERR, $notice);
            return $notice;
        }
        foreach($fileRecords as $fileRecord){
            $this->attachFile($notice, $fileRecord);
        }
        common_broadcast_notice($notice);
        $this->log(LOG_INFO,
                   'Added notice ' . $notice->id . ' from user ' . $user->nickname);
        return true;
    }

    function attachFile($notice, $filerec)
    {   
        File_to_post::processNew($filerec->id, $notice->id);

        $this->maybeAddRedir($filerec->id,
            common_local_url('file', array('notice' => $notice->id)));
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

        $attachments = array();

        if ($parsed->ctype_primary == 'multipart') {
            foreach ($parsed->parts as $part) {
                if ($part->ctype_primary == 'text' &&
                    $part->ctype_secondary == 'plain') {
                    $msg = $part->body;
                }else{
                    if ($part->body) {
			$attachment = tmpfile();
			fwrite($attachment, $part->body);
                        $attachments[] = $attachment;
                    }
                }
            }
        } else if ($type == 'text/plain') {
            $msg = $parsed->body;
        } else {
            $this->unsupported_type($type);
        }
        return array($from, $to, $msg, $attachments);
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
