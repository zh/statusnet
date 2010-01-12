<?php

/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Widget to show a list of OAuth applications
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
 * @category  Application
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/widget.php';

define('APPS_PER_PAGE', 20);

/**
 * Widget to show a list of OAuth applications
 *
 * @category Application
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class ApplicationList extends Widget
{
    /** Current application, application query */
    var $application = null;

    /** Owner of this list */
    var $owner = null;

    /** Action object using us. */
    var $action = null;

    function __construct($application, $owner=null, $action=null, $connections = false)
    {
        parent::__construct($action);

        $this->application = $application;
        $this->owner       = $owner;
        $this->action      = $action;
	$this->connections = $connections;
    }

    function show()
    {
        $this->out->elementStart('ul', 'applications');

        $cnt = 0;

        while ($this->application->fetch()) {
            $cnt++;
            if($cnt > APPS_PER_PAGE) {
                break;
            }
            $this->showapplication();
        }

        $this->out->elementEnd('ul');

        return $cnt;
    }

    function showApplication()
    {

        $user = common_current_user();

        $this->out->elementStart('li', array('class' => 'application',
                                             'id' => 'oauthclient-' . $this->application->id));

        if (!empty($this->application->icon)) {
            $this->out->element('img', array('src' => $this->application->icon));
        }

        if (!$this->connections) {
            $this->out->elementStart('a',
                            array('href' => common_local_url('showapplication',
                                                array('nickname' => $user->nickname,
                                                      'id' => $this->application->id)),
                                  'class' => 'url'));

            $this->out->raw($this->application->name);
            $this->out->elementEnd('a');
        } else {
            $this->out->elementStart('a', array('href' =>  $this->application->source_url,
                                                'class' => 'url'));
            $this->out->raw($this->application->name);
            $this->out->elementEnd('a');
        }

        $this->out->raw(' by ');

        $this->out->elementStart('a', array('href' => $this->application->homepage,
                                            'class' => 'url'));
        $this->out->raw($this->application->organization);
        $this->out->elementEnd('a');

        $this->out->elementStart('p', 'note');
        $this->out->raw($this->application->description);
        $this->out->elementEnd('p');
        $this->out->elementEnd('li');

        if ($this->connections) {
            $appUser = Oauth_application_user::getByKeys($this->owner, $this->application);

            if (empty($appUser)) {
                common_debug("empty appUser!");
            }

            $this->out->elementStart('li');

            $access = ($this->application->access_type & Oauth_application::$writeAccess)
              ? 'read-write' : 'read-only';

            $txt = 'Approved ' . common_exact_date($appUser->modified) .
              " $access for access.";

            $this->out->raw($txt);
            $this->out->elementEnd('li');

            // XXX: Add revoke access button
        }
    }

    /* Override this in subclasses. */

    function showOwnerControls()
    {
        return;
    }

    function highlight($text)
    {
        return htmlspecialchars($text);
    }
}
