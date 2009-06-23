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

$shortoptions = 'i::';
$longoptions = array('id::');

$helptext = <<<END_OF_ENJIT_HELP
Daemon script for watching new notices and posting to enjit.

    -i --id           Identity (default none)

END_OF_ENJIT_HELP;

require_once INSTALLDIR.'/scripts/commandline.inc';

require_once INSTALLDIR . '/lib/mail.php';
require_once INSTALLDIR . '/lib/queuehandler.php';

set_error_handler('common_error_handler');

class EnjitQueueHandler extends QueueHandler
{
    function transport()
    {
        return 'enjit';
    }

    function start()
    {
                $this->log(LOG_INFO, "Starting EnjitQueueHandler");
                $this->log(LOG_INFO, "Broadcasting to ".common_config('enjit', 'apiurl'));
        return true;
    }

    function handle_notice($notice)
    {

        $profile = Profile::staticGet($notice->profile_id);

                $this->log(LOG_INFO, "Posting Notice ".$notice->id." from ".$profile->nickname);

                if ( ! $notice->is_local ) {
                    $this->log(LOG_INFO, "Skipping remote notice");
                    return "skipped";
                }

                #
                # Build an Atom message from the notice
                #
        $noticeurl = common_local_url('shownotice', array('notice' => $notice->id));
        $msg = $profile->nickname . ': ' . $notice->content;

        $atom  = "<entry xmlns='http://www.w3.org/2005/Atom'>\n";
        $atom .= "<apisource>".common_config('enjit','source')."</apisource>\n";
        $atom .= "<source>\n";
        $atom .= "<title>" . $profile->nickname . " - " . common_config('site', 'name') . "</title>\n";
        $atom .= "<link href='" . $profile->profileurl . "'/>\n";
        $atom .= "<link rel='self' type='application/rss+xml' href='" . common_local_url('userrss', array('nickname' => $profile->nickname)) . "'/>\n";
        $atom .= "<author><name>" . $profile->nickname . "</name></author>\n";
        $atom .= "<icon>" . $profile->avatarUrl(AVATAR_PROFILE_SIZE) . "</icon>\n";
        $atom .= "</source>\n";
        $atom .= "<title>" . htmlspecialchars($msg) . "</title>\n";
        $atom .= "<summary>" . htmlspecialchars($msg) . "</summary>\n";
        $atom .= "<link rel='alternate' href='" . $noticeurl . "' />\n";
        $atom .= "<id>". $notice->uri . "</id>\n";
        $atom .= "<published>".common_date_w3dtf($notice->created)."</published>\n";
        $atom .= "<updated>".common_date_w3dtf($notice->modified)."</updated>\n";
        $atom .= "</entry>\n";

                $url  = common_config('enjit', 'apiurl') . "/submit/". common_config('enjit','apikey');
                $data = "msg=$atom";

                #
                # POST the message to $config['enjit']['apiurl']
                #
        $ch   = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1) ;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

                # SSL and Debugging options
                #
        # curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        # curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                # curl_setopt($ch, CURLOPT_VERBOSE, 1);

        $result = curl_exec($ch);

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE );

                $this->log(LOG_INFO, "Response Code: $code");

        curl_close($ch);

                return $code;
    }

}

if (have_option('-i')) {
    $id = get_option_value('-i');
} else if (have_option('--id')) {
    $id = get_option_value('--id');
} else if (count($args) > 0) {
    $id = $args[0];
} else {
    $id = null;
}

$handler = new EnjitQueueHandler($id);

if ($handler->start()) {
    $handler->handle_queue();
}

$handler->finish();
