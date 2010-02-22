<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
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

/**
 * @package OStatusPlugin
 * @maintainer James Walker <james@status.net>
 */

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class WebfingerAction extends Action
{

    public $uri;

    function prepare($args)
    {
        parent::prepare($args);

        $this->uri = $this->trimmed('uri');

        return true;
    }

    function handle()
    {
        $acct = Webfinger::normalize($this->uri);

        $xrd = new XRD();

        list($nick, $domain) = explode('@', urldecode($acct));
        $nick = common_canonical_nickname($nick);

        $this->user = User::staticGet('nickname', $nick);
        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $xrd->subject = $this->uri;
        $xrd->alias[] = common_profile_url($nick);
        $xrd->links[] = array('rel' => Webfinger::PROFILEPAGE,
                              'type' => 'text/html',
                              'href' => common_profile_url($nick));

        $xrd->links[] = array('rel' => Webfinger::UPDATESFROM,
                              'href' => common_local_url('ApiTimelineUser',
                                                         array('id' => $this->user->id,
                                                               'format' => 'atom')),
                              'type' => 'application/atom+xml');

        $salmon_url = common_local_url('salmon',
                                       array('id' => $this->user->id));

        $xrd->links[] = array('rel' => 'salmon',
                              'href' => $salmon_url);

        // TODO - finalize where the redirect should go on the publisher
        $url = common_local_url('ostatussub') . '?profile={uri}';
        $xrd->links[] = array('rel' => 'http://ostatus.org/schema/1.0/subscribe',
                              'template' => $url );

        header('Content-type: text/xml');
        print $xrd->toXML();
    }

}
