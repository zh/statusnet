<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * utility functions for Jabber/GTalk/XMPP messages
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
 * @category  Network
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2008 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once 'XMPPHP/XMPP.php';

/**
 * Splits a Jabber ID (JID) into node, domain, and resource portions.
 * 
 * Based on validation routine submitted by:
 * @copyright 2009 Patrick Georgi <patrick@georgi-clan.de>
 * @license Licensed under ISC-L, which is compatible with everything else that keeps the copyright notice intact. 
 *
 * @param string $jid string to check
 *
 * @return array with "node", "domain", and "resource" indices
 * @throws Exception if input is not valid
 */

function jabber_split_jid($jid)
{
    $chars = '';
    /* the following definitions come from stringprep, Appendix C,
       which is used in its entirety by nodeprop, Chapter 5, "Prohibited Output" */
    /* C1.1 ASCII space characters */
    $chars .= "\x{20}";
    /* C1.2 Non-ASCII space characters */
    $chars .= "\x{a0}\x{1680}\x{2000}-\x{200b}\x{202f}\x{205f}\x{3000a}";
    /* C2.1 ASCII control characters */
    $chars .= "\x{00}-\x{1f}\x{7f}";
    /* C2.2 Non-ASCII control characters */
    $chars .= "\x{80}-\x{9f}\x{6dd}\x{70f}\x{180e}\x{200c}\x{200d}\x{2028}\x{2029}\x{2060}-\x{2063}\x{206a}-\x{206f}\x{feff}\x{fff9}-\x{fffc}\x{1d173}-\x{1d17a}";
    /* C3 - Private Use */
    $chars .= "\x{e000}-\x{f8ff}\x{f0000}-\x{ffffd}\x{100000}-\x{10fffd}";
    /* C4 - Non-character code points */
    $chars .= "\x{fdd0}-\x{fdef}\x{fffe}\x{ffff}\x{1fffe}\x{1ffff}\x{2fffe}\x{2ffff}\x{3fffe}\x{3ffff}\x{4fffe}\x{4ffff}\x{5fffe}\x{5ffff}\x{6fffe}\x{6ffff}\x{7fffe}\x{7ffff}\x{8fffe}\x{8ffff}\x{9fffe}\x{9ffff}\x{afffe}\x{affff}\x{bfffe}\x{bffff}\x{cfffe}\x{cffff}\x{dfffe}\x{dffff}\x{efffe}\x{effff}\x{ffffe}\x{fffff}\x{10fffe}\x{10ffff}";
    /* C5 - Surrogate codes */
    $chars .= "\x{d800}-\x{dfff}";
    /* C6 - Inappropriate for plain text */
    $chars .= "\x{fff9}-\x{fffd}";
    /* C7 - Inappropriate for canonical representation */
    $chars .= "\x{2ff0}-\x{2ffb}";
    /* C8 - Change display properties or are deprecated */
    $chars .= "\x{340}\x{341}\x{200e}\x{200f}\x{202a}-\x{202e}\x{206a}-\x{206f}";
    /* C9 - Tagging characters */
    $chars .= "\x{e0001}\x{e0020}-\x{e007f}";

    /* Nodeprep forbids some more characters */
    $nodeprepchars = $chars;
    $nodeprepchars .= "\x{22}\x{26}\x{27}\x{2f}\x{3a}\x{3c}\x{3e}\x{40}";

    $parts = explode("/", $jid, 2);
    if (count($parts) > 1) {
        $resource = $parts[1];
        if ($resource == '') {
            // Warning: empty resource isn't legit.
            // But if we're normalizing, we may as well take it...
        }
    } else {
        $resource = null;
    }

    $node = explode("@", $parts[0]);
    if ((count($node) > 2) || (count($node) == 0)) {
        throw new Exception("Invalid JID: too many @s");
    } else if (count($node) == 1) {
        $domain = $node[0];
        $node = null;
    } else {
        $domain = $node[1];
        $node = $node[0];
        if ($node == '') {
            throw new Exception("Invalid JID: @ but no node");
        }
    }

    // Length limits per http://xmpp.org/rfcs/rfc3920.html#addressing
    if ($node !== null) {
        if (strlen($node) > 1023) {
            throw new Exception("Invalid JID: node too long.");
        }
        if (preg_match("/[".$nodeprepchars."]/u", $node)) {
            throw new Exception("Invalid JID node '$node'");
        }
    }

    if (strlen($domain) > 1023) {
        throw new Exception("Invalid JID: domain too long.");
    }
    if (!common_valid_domain($domain)) {
        throw new Exception("Invalid JID domain name '$domain'");
    }

    if ($resource !== null) {
        if (strlen($resource) > 1023) {
            throw new Exception("Invalid JID: resource too long.");
        }
        if (preg_match("/[".$chars."]/u", $resource)) {
            throw new Exception("Invalid JID resource '$resource'");
        }
    }

    return array('node' => is_null($node) ? null : mb_strtolower($node),
                 'domain' => is_null($domain) ? null : mb_strtolower($domain),
                 'resource' => $resource);
}

/**
 * Checks whether a string is a syntactically valid Jabber ID (JID),
 * either with or without a resource.
 * 
 * Note that a bare domain can be a valid JID.
 * 
 * @param string $jid string to check
 * @param bool $check_domain whether we should validate that domain...
 *
 * @return     boolean whether the string is a valid JID
 */
function jabber_valid_full_jid($jid, $check_domain=false)
{
    try {
        $parts = jabber_split_jid($jid);
        if ($check_domain) {
            if (!jabber_check_domain($parts['domain'])) {
                return false;
            }
        }
        return $parts['resource'] !== ''; // missing or present; empty ain't kosher
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks whether a string is a syntactically valid base Jabber ID (JID).
 * A base JID won't include a resource specifier on the end; since we
 * take it off when reading input we can't really use them reliably
 * to direct outgoing messages yet (sorry guys!)
 * 
 * Note that a bare domain can be a valid JID.
 * 
 * @param string $jid string to check
 * @param bool $check_domain whether we should validate that domain...
 *
 * @return     boolean whether the string is a valid JID
 */
function jabber_valid_base_jid($jid, $check_domain=false)
{
    try {
        $parts = jabber_split_jid($jid);
        if ($check_domain) {
            if (!jabber_check_domain($parts['domain'])) {
                return false;
            }
        }
        return ($parts['resource'] === null); // missing; empty ain't kosher
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Normalizes a Jabber ID for comparison, dropping the resource component if any.
 *
 * @param string $jid JID to check
 * @param bool $check_domain if true, reject if the domain isn't findable
 *
 * @return string an equivalent JID in normalized (lowercase) form
 */

function jabber_normalize_jid($jid)
{
    try {
        $parts = jabber_split_jid($jid);
        if ($parts['node'] !== null) {
            return $parts['node'] . '@' . $parts['domain'];
        } else {
            return $parts['domain'];
        }
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if this domain's got some legit DNS record
 */
function jabber_check_domain($domain)
{
    if (checkdnsrr("_xmpp-server._tcp." . $domain, "SRV")) {
        return true;
    }
    if (checkdnsrr($domain, "ANY")) {
        return true;
    }
    return false;
}

/**
 * the JID of the Jabber daemon for this StatusNet instance
 *
 * @return string JID of the Jabber daemon
 */

function jabber_daemon_address()
{
    return common_config('xmpp', 'user') . '@' . common_config('xmpp', 'server');
}

class Sharing_XMPP extends XMPPHP_XMPP
{
    function getSocket()
    {
        return $this->socket;
    }
}

/**
 * Build an XMPP proxy connection that'll save outgoing messages
 * to the 'xmppout' queue to be picked up by xmppdaemon later.
 *
 * If queueing is disabled, we'll grab a live connection.
 *
 * @return XMPPHP
 */
function jabber_proxy()
{
    if (common_config('queue', 'enabled')) {
	    $proxy = new Queued_XMPP(common_config('xmpp', 'host') ?
                                 common_config('xmpp', 'host') :
                                 common_config('xmpp', 'server'),
                                 common_config('xmpp', 'port'),
                                 common_config('xmpp', 'user'),
                                 common_config('xmpp', 'password'),
                                 common_config('xmpp', 'resource') . 'daemon',
                                 common_config('xmpp', 'server'),
                                 common_config('xmpp', 'debug') ?
                                 true : false,
                                 common_config('xmpp', 'debug') ?
                                 XMPPHP_Log::LEVEL_VERBOSE :  null);
        return $proxy;
    } else {
        return jabber_connect();
    }
}

/**
 * Lazy-connect the configured Jabber account to the configured server;
 * if already opened, the same connection will be returned.
 *
 * In a multi-site background process, each site configuration
 * will get its own connection.
 *
 * @param string $resource Resource to connect (defaults to configured resource)
 *
 * @return XMPPHP connection to the configured server
 */

function jabber_connect($resource=null)
{
    static $connections = array();
    $site = common_config('site', 'server');
    if (empty($connections[$site])) {
        if (empty($resource)) {
            $resource = common_config('xmpp', 'resource');
        }
        $conn = new Sharing_XMPP(common_config('xmpp', 'host') ?
                                common_config('xmpp', 'host') :
                                common_config('xmpp', 'server'),
                                common_config('xmpp', 'port'),
                                common_config('xmpp', 'user'),
                                common_config('xmpp', 'password'),
                                $resource,
                                common_config('xmpp', 'server'),
                                common_config('xmpp', 'debug') ?
                                true : false,
                                common_config('xmpp', 'debug') ?
                                XMPPHP_Log::LEVEL_VERBOSE :  null
                                );

        if (!$conn) {
            return false;
        }
        $connections[$site] = $conn;

        $conn->autoSubscribe();
        $conn->useEncryption(common_config('xmpp', 'encryption'));

        try {
            common_log(LOG_INFO, __METHOD__ . ": connecting " .
                common_config('xmpp', 'user') . '/' . $resource);
            //$conn->connect(true); // true = persistent connection
            $conn->connect(); // persistent connections break multisite
        } catch (XMPPHP_Exception $e) {
            common_log(LOG_ERR, $e->getMessage());
            return false;
        }

        $conn->processUntil('session_start');
    }
    return $connections[$site];
}

/**
 * Queue send for a single notice to a given Jabber address
 *
 * @param string $to     JID to send the notice to
 * @param Notice $notice notice to send
 *
 * @return boolean success value
 */

function jabber_send_notice($to, $notice)
{
    $conn = jabber_proxy();
    $profile = Profile::staticGet($notice->profile_id);
    if (!$profile) {
        common_log(LOG_WARNING, 'Refusing to send notice with ' .
                   'unknown profile ' . common_log_objstring($notice),
                   __FILE__);
        return false;
    }
    $msg   = jabber_format_notice($profile, $notice);
    $entry = jabber_format_entry($profile, $notice);
    $conn->message($to, $msg, 'chat', null, $entry);
    $profile->free();
    return true;
}

/**
 * extra information for XMPP messages, as defined by Twitter
 *
 * @param Profile $profile Profile of the sending user
 * @param Notice  $notice  Notice being sent
 *
 * @return string Extra information (Atom, HTML, addresses) in string format
 */

function jabber_format_entry($profile, $notice)
{
    $entry = $notice->asAtomEntry(true, true);

    $xs = new XMLStringer();
    $xs->elementStart('html', array('xmlns' => 'http://jabber.org/protocol/xhtml-im'));
    $xs->elementStart('body', array('xmlns' => 'http://www.w3.org/1999/xhtml'));
    $xs->element('a', array('href' => $profile->profileurl),
                 $profile->nickname);
    $xs->text(": ");
    if (!empty($notice->rendered)) {
        $xs->raw($notice->rendered);
    } else {
        $xs->raw(common_render_content($notice->content, $notice));
    }
    $xs->text(" ");
    $xs->element('a', array(
        'href'=>common_local_url('conversation',
            array('id' => $notice->conversation)).'#notice-'.$notice->id
         ),sprintf(_('[%s]'),$notice->id));
    $xs->elementEnd('body');
    $xs->elementEnd('html');

    $html = $xs->getString();

    return $html . ' ' . $entry;
}

/**
 * sends a single text message to a given JID
 *
 * @param string $to      JID to send the message to
 * @param string $body    body of the message
 * @param string $type    type of the message
 * @param string $subject subject of the message
 *
 * @return boolean success flag
 */

function jabber_send_message($to, $body, $type='chat', $subject=null)
{
    $conn = jabber_proxy();
    $conn->message($to, $body, $type, $subject);
    return true;
}

/**
 * sends a presence stanza on the Jabber network
 *
 * @param string $status   current status, free-form string
 * @param string $show     structured status value
 * @param string $to       recipient of presence, null for general
 * @param string $type     type of status message, related to $show
 * @param int    $priority priority of the presence
 *
 * @return boolean success value
 */

function jabber_send_presence($status, $show='available', $to=null,
                              $type = 'available', $priority=null)
{
    $conn = jabber_connect();
    if (!$conn) {
        return false;
    }
    $conn->presence($status, $show, $to, $type, $priority);
    return true;
}

/**
 * sends a confirmation request to a JID
 *
 * @param string $code     confirmation code for confirmation URL
 * @param string $nickname nickname of confirming user
 * @param string $address  JID to send confirmation to
 *
 * @return boolean success flag
 */

function jabber_confirm_address($code, $nickname, $address)
{
    $body = 'User "' . $nickname . '" on ' . common_config('site', 'name') . ' ' .
      'has said that your Jabber ID belongs to them. ' .
      'If that\'s true, you can confirm by clicking on this URL: ' .
      common_local_url('confirmaddress', array('code' => $code)) .
      ' . (If you cannot click it, copy-and-paste it into the ' .
      'address bar of your browser). If that user isn\'t you, ' .
      'or if you didn\'t request this confirmation, just ignore this message.';

    return jabber_send_message($address, $body);
}

/**
 * sends a "special" presence stanza on the Jabber network
 *
 * @param string $type   Type of presence
 * @param string $to     JID to send presence to
 * @param string $show   show value for presence
 * @param string $status status value for presence
 *
 * @return boolean success flag
 *
 * @see jabber_send_presence()
 */

function jabber_special_presence($type, $to=null, $show=null, $status=null)
{
    // FIXME: why use this instead of jabber_send_presence()?
    $conn = jabber_connect();

    $to     = htmlspecialchars($to);
    $status = htmlspecialchars($status);

    $out = "<presence";
    if ($to) {
        $out .= " to='$to'";
    }
    if ($type) {
        $out .= " type='$type'";
    }
    if ($show == 'available' and !$status) {
        $out .= "/>";
    } else {
        $out .= ">";
        if ($show && ($show != 'available')) {
            $out .= "<show>$show</show>";
        }
        if ($status) {
            $out .= "<status>$status</status>";
        }
        $out .= "</presence>";
    }
    $conn->send($out);
}

/**
 * Queue broadcast of a notice to all subscribers and reply recipients
 *
 * This function will send a notice to all subscribers on the local server
 * who have Jabber addresses, and have Jabber notification enabled, and
 * have this subscription enabled for Jabber. It also sends the notice to
 * all recipients of @-replies who have Jabber addresses and Jabber notification
 * enabled. This is really the heart of Jabber distribution in StatusNet.
 *
 * @param Notice $notice The notice to broadcast
 *
 * @return boolean success flag
 */

function jabber_broadcast_notice($notice)
{
    if (!common_config('xmpp', 'enabled')) {
        return true;
    }
    $profile = Profile::staticGet($notice->profile_id);

    if (!$profile) {
        common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
                   'unknown profile ' . common_log_objstring($notice),
                   __FILE__);
        return true; // not recoverable; discard.
    }

    $msg   = jabber_format_notice($profile, $notice);
    $entry = jabber_format_entry($profile, $notice);

    $profile->free();
    unset($profile);

    $sent_to = array();

    $conn = jabber_proxy();

    $ni = $notice->whoGets();

    foreach ($ni as $user_id => $reason) {
        $user = User::staticGet($user_id);
        if (empty($user) ||
            empty($user->jabber) ||
            !$user->jabbernotify) {
            // either not a local user, or just not found
            continue;
        }
        switch ($reason) {
        case NOTICE_INBOX_SOURCE_REPLY:
            if (!$user->jabberreplies) {
                continue 2;
            }
            break;
        case NOTICE_INBOX_SOURCE_SUB:
            $sub = Subscription::pkeyGet(array('subscriber' => $user->id,
                                               'subscribed' => $notice->profile_id));
            if (empty($sub) || !$sub->jabber) {
                continue 2;
            }
            break;
        case NOTICE_INBOX_SOURCE_GROUP:
            break;
        default:
            throw new Exception(sprintf(_("Unknown inbox source %d."), $reason));
        }

        common_log(LOG_INFO,
                   'Sending notice ' . $notice->id . ' to ' . $user->jabber,
                   __FILE__);
        $conn->message($user->jabber, $msg, 'chat', null, $entry);
    }

    return true;
}

/**
 * Queue send of a notice to all public listeners
 *
 * For notices that are generated on the local system (by users), we can optionally
 * forward them to remote listeners by XMPP.
 *
 * @param Notice $notice notice to broadcast
 *
 * @return boolean success flag
 */

function jabber_public_notice($notice)
{
    // Now, users who want everything

    $public = common_config('xmpp', 'public');

    // FIXME PRIV don't send out private messages here
    // XXX: should we send out non-local messages if public,localonly
    // = false? I think not

    if ($public && $notice->is_local == Notice::LOCAL_PUBLIC) {
        $profile = Profile::staticGet($notice->profile_id);

        if (!$profile) {
            common_log(LOG_WARNING, 'Refusing to broadcast notice with ' .
                       'unknown profile ' . common_log_objstring($notice),
                       __FILE__);
            return true; // not recoverable; discard.
        }

        $msg   = jabber_format_notice($profile, $notice);
        $entry = jabber_format_entry($profile, $notice);

        $conn = jabber_proxy();

        foreach ($public as $address) {
            common_log(LOG_INFO,
                       'Sending notice ' . $notice->id .
                       ' to public listener ' . $address,
                       __FILE__);
            $conn->message($address, $msg, 'chat', null, $entry);
        }
        $profile->free();
    }

    return true;
}

/**
 * makes a plain-text formatted version of a notice, suitable for Jabber distribution
 *
 * @param Profile &$profile profile of the sending user
 * @param Notice  &$notice  notice being sent
 *
 * @return string plain-text version of the notice, with user nickname prefixed
 */

function jabber_format_notice(&$profile, &$notice)
{
    return $profile->nickname . ': ' . $notice->content . ' [' . $notice->id . ']';
}
