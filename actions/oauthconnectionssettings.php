<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * List a user's OAuth connected applications
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
 * @category  Settings
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR . '/lib/connectsettingsaction.php';
require_once INSTALLDIR . '/lib/applicationlist.php';

/**
 * Show connected OAuth applications
 *
 * @category Settings
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 *
 * @see      SettingsAction
 */

class OauthconnectionssettingsAction extends ConnectSettingsAction
{

    var $page = null;

    function prepare($args)
    {
        parent::prepare($args);
        $this->page = ($this->arg('page')) ? ($this->arg('page') + 0) : 1;
        return true;
    }

    /**
     * Title of the page
     *
     * @return string Title of the page
     */

    function title()
    {
        return _('Connected Applications');
    }

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Instructions for use
     *
     * @return instructions for use
     */

    function getInstructions()
    {
        return _('You have allowed the following applications to access you account.');
    }

    /**
     * Content area of the page
     *
     * @return void
     */

    function showContent()
    {
        $user    = common_current_user();
        $profile = $user->getProfile();

        $offset = ($this->page - 1) * APPS_PER_PAGE;
        $limit  =  APPS_PER_PAGE + 1;

        $application = $profile->getApplications($offset, $limit);

	$cnt == 0;

	if (!empty($application)) {
	    $al = new ApplicationList($application, $user, $this, true);
	    $cnt = $al->show();
	}

	if ($cnt == 0) {
	    $this->showEmptyListMessage();
	}

        $this->pagination($this->page > 1, $cnt > APPS_PER_PAGE,
                          $this->page, 'connectionssettings',
                          array('nickname' => $this->user->nickname));
    }

    /**
     * Handle posts to this form
     *
     * Based on the button that was pressed, muxes out to other functions
     * to do the actual task requested.
     *
     * All sub-functions reload the form with a message -- success or failure.
     *
     * @return void
     */

    function handlePost()
    {
        // CSRF protection

        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->showForm(_('There was a problem with your session token. '.
                              'Try again, please.'));
            return;
        }

    }

    function showEmptyListMessage()
    {
        $message = sprintf(_('You have not authorized any applications to use your account.'));

        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showSections()
    {
       $cur = common_current_user();

       $this->element('h2', null, 'Developers');
       $this->elementStart('p');
       $this->raw(_('Developers can edit the registration settings for their applications '));
       $this->element('a',
           array('href' => common_local_url('apps', array('nickname' => $cur->nickname))),
               'here.');
       $this->elementEnd('p');
    }

}
