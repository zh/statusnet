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

if (!defined('LACONICA')) { exit(1); }

require_once(INSTALLDIR.'/classes/User_openid.php');

require_once('Auth/OpenID.php');
require_once('Auth/OpenID/Consumer.php');
require_once('Auth/OpenID/SReg.php');
require_once('Auth/OpenID/MySQLStore.php');

# About one year cookie expiry

define('OPENID_COOKIE_EXPIRY', round(365.25 * 24 * 60 * 60));
define('OPENID_COOKIE_KEY', 'lastusedopenid');

function oid_store()
{
    static $store = null;
    if (!$store) {
        # Can't be called statically
        $user = new User();
        $conn = $user->getDatabaseConnection();
        $store = new Auth_OpenID_MySQLStore($conn);
    }
    return $store;
}

function oid_consumer()
{
    $store = oid_store();
    $consumer = new Auth_OpenID_Consumer($store);
    return $consumer;
}

function oid_clear_last()
{
    oid_set_last('');
}

function oid_set_last($openid_url)
{
    common_set_cookie(OPENID_COOKIE_KEY,
                     $openid_url,
                     time() + OPENID_COOKIE_EXPIRY);
}

function oid_get_last()
{
    if (empty($_COOKIE[OPENID_COOKIE_KEY])) {
        return null;
    }
    $openid_url = $_COOKIE[OPENID_COOKIE_KEY];
    if ($openid_url && strlen($openid_url) > 0) {
        return $openid_url;
    } else {
        return null;
    }
}

function oid_link_user($id, $canonical, $display)
{

    $oid = new User_openid();
    $oid->user_id = $id;
    $oid->canonical = $canonical;
    $oid->display = $display;
    $oid->created = DB_DataObject_Cast::dateTime();

    if (!$oid->insert()) {
        $err = PEAR::getStaticProperty('DB_DataObject','lastError');
        common_debug('DB error ' . $err->code . ': ' . $err->message, __FILE__);
        return false;
    }

    return true;
}

function oid_get_user($openid_url)
{
    $user = null;
    $oid = User_openid::staticGet('canonical', $openid_url);
    if ($oid) {
        $user = User::staticGet('id', $oid->user_id);
    }
    return $user;
}

function oid_check_immediate($openid_url, $backto=null)
{
    if (!$backto) {
        $action = $_REQUEST['action'];
        $args = common_copy_args($_GET);
        unset($args['action']);
        $backto = common_local_url($action, $args);
    }
    common_debug('going back to "' . $backto . '"', __FILE__);

    common_ensure_session();

    $_SESSION['openid_immediate_backto'] = $backto;
    common_debug('passed-in variable is "' . $backto . '"', __FILE__);
    common_debug('session variable is "' . $_SESSION['openid_immediate_backto'] . '"', __FILE__);

    oid_authenticate($openid_url,
                     'finishimmediate',
                     true);
}

function oid_authenticate($openid_url, $returnto, $immediate=false)
{

    $consumer = oid_consumer();

    if (!$consumer) {
        common_server_error(_('Cannot instantiate OpenID consumer object.'));
        return false;
    }

    common_ensure_session();

    $auth_request = $consumer->begin($openid_url);

    // Handle failure status return values.
    if (!$auth_request) {
        return _('Not a valid OpenID.');
    } else if (Auth_OpenID::isFailure($auth_request)) {
        return sprintf(_('OpenID failure: %s'), $auth_request->message);
    }

    $sreg_request = Auth_OpenID_SRegRequest::build(// Required
                                                   array(),
                                                   // Optional
                                                   array('nickname',
                                                         'email',
                                                         'fullname',
                                                         'language',
                                                         'timezone',
                                                         'postcode',
                                                         'country'));

    if ($sreg_request) {
        $auth_request->addExtension($sreg_request);
    }

    $trust_root = common_root_url(true);
    $process_url = common_local_url($returnto);

    if ($auth_request->shouldSendRedirect()) {
        $redirect_url = $auth_request->redirectURL($trust_root,
                                                   $process_url,
                                                   $immediate);
        if (!$redirect_url) {
        } else if (Auth_OpenID::isFailure($redirect_url)) {
            return sprintf(_('Could not redirect to server: %s'), $redirect_url->message);
        } else {
            common_redirect($redirect_url, 303);
        }
    } else {
        // Generate form markup and render it.
        $form_id = 'openid_message';
        $form_html = $auth_request->formMarkup($trust_root, $process_url,
                                               $immediate, array('id' => $form_id));

        # XXX: This is cheap, but things choke if we don't escape ampersands
        # in the HTML attributes

        $form_html = preg_replace('/&/', '&amp;', $form_html);

        // Display an error if the form markup couldn't be generated;
        // otherwise, render the HTML.
        if (Auth_OpenID::isFailure($form_html)) {
            common_server_error(sprintf(_('Could not create OpenID form: %s'), $form_html->message));
        } else {
            $action = new AutosubmitAction(); // see below
            $action->form_html = $form_html;
            $action->form_id = $form_id;
            $action->prepare(array('action' => 'autosubmit'));
            $action->handle(array('action' => 'autosubmit'));
        }
    }
}

# Half-assed attempt at a module-private function

function _oid_print_instructions()
{
    common_element('div', 'instructions',
                   _('This form should automatically submit itself. '.
                      'If not, click the submit button to go to your '.
                      'OpenID provider.'));
}

# update a user from sreg parameters

function oid_update_user(&$user, &$sreg)
{

    $profile = $user->getProfile();

    $orig_profile = clone($profile);

    if ($sreg['fullname'] && strlen($sreg['fullname']) <= 255) {
        $profile->fullname = $sreg['fullname'];
    }

    if ($sreg['country']) {
        if ($sreg['postcode']) {
            # XXX: use postcode to get city and region
            # XXX: also, store postcode somewhere -- it's valuable!
            $profile->location = $sreg['postcode'] . ', ' . $sreg['country'];
        } else {
            $profile->location = $sreg['country'];
        }
    }

    # XXX save language if it's passed
    # XXX save timezone if it's passed

    if (!$profile->update($orig_profile)) {
        common_server_error(_('Error saving the profile.'));
        return false;
    }

    $orig_user = clone($user);

    if ($sreg['email'] && Validate::email($sreg['email'], true)) {
        $user->email = $sreg['email'];
    }

    if (!$user->update($orig_user)) {
        common_server_error(_('Error saving the user.'));
        return false;
    }

    return true;
}

class AutosubmitAction extends Action
{
    var $form_html = null;
    var $form_id = null;

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function title()
    {
        return _('OpenID Auto-Submit');
    }

    function showContent()
    {
        $this->raw($this->form_html);
        $this->element('script', null,
                       '$(document).ready(function() { ' .
                       '    $(\'#'. $this->form_id .'\').submit(); '.
                       '});');
    }
}
