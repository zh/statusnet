<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * common superclass for direct messages inbox and outbox
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
 * @category  Action
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */

if (!defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/personal.php';

define('MESSAGES_PER_PAGE', 20);

/**
 * common superclass for direct messages inbox and outbox
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 * @see      InboxAction
 * @see      OutboxAction
 */

class MailboxAction extends PersonalAction
{
    /**
     * output page based on arguments
     *
     * @param array $args HTTP arguments (from $_REQUEST)
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);

        $nickname = common_canonical_nickname($this->arg('nickname'));

        $user = User::staticGet('nickname', $nickname);

        if (!$user) {
            $this->client_error(_('No such user.'), 404);
            return;
        }

        $cur = common_current_user();

        if (!$cur || $cur->id != $user->id) {
            $this->client_error(_('Only the user can read their own mailboxes.'),
                                403);
            return;
        }

        $profile = $user->getProfile();

        if (!$profile) {
            $this->server_error(_('User has no profile.'));
            return;
        }

        $page = $this->trimmed('page');

        if (!$page) {
            $page = 1;
        }

        $this->show_page($user, $page);
    }

    /**
     * returns the title of the page
     *
     * @param User $user current user
     * @param int  $page current page
     *
     * @return string localised title of the page
     */

    function get_title($user, $page)
    {
        return '';
    }

    /**
     * instructions for using this page
     *
     * @return string localised instructions for using the page
     */

    function get_instructions()
    {
        return '';
    }

    /**
     * do structured output for the "instructions" are of the page
     *
     * @return void
     */

    function show_top()
    {
        $cur = common_current_user();

        common_message_form(null, $cur, null);

        $this->views_menu();
    }

    /**
     * show a full page of output
     *
     * @param User $user The current user
     * @param int  $page The page the user is on
     *
     * @return void
     */

    function show_page($user, $page)
    {
        common_show_header($this->get_title($user, $page),
                           null, null,
                           array($this, 'show_top'));

        $this->show_box($user, $page);

        common_show_footer();
    }

    /**
     * show the messages for a mailbox in list format
     *
     * Includes the pagination links (before, after).
     *
     * @param User $user The current user
     * @param int  $page The page the user is on
     *
     * @return void
     */

    function show_box($user, $page)
    {
        $message = $this->get_messages($user, $page);

        if ($message) {

            $cnt = 0;
            common_element_start('ul', array('id' => 'messages'));

            while ($message->fetch() && $cnt <= MESSAGES_PER_PAGE) {
                $cnt++;

                if ($cnt > MESSAGES_PER_PAGE) {
                    break;
                }

                $this->show_message($message);
            }

            common_element_end('ul');

            common_pagination($page > 1, $cnt > MESSAGES_PER_PAGE,
                              $page, $this->trimmed('action'),
                              array('nickname' => $user->nickname));

            $message->free();
            unset($message);
        }
    }

    /**
     * returns the profile we want to show with the message
     *
     * For inboxes, we show the sender; for outboxes, the recipient.
     *
     * @param Message $message The message to get the profile for
     *
     * @return Profile The profile that matches the message
     */

    function get_message_profile($message)
    {
        return null;
    }

    /**
     * show a single message in the list format
     *
     * @param Message $message the message to show
     *
     * @return void
     */

    function show_message($message)
    {
        common_element_start('li', array('class' => 'message_single',
                                         'id' => 'message-' . $message->id));

        $profile = $this->get_message_profile($message);

        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        common_element_start('a', array('href' => $profile->profileurl));
        common_element('img', array('src' => ($avatar) ?
                                    common_avatar_display_url($avatar) :
                                    common_default_avatar(AVATAR_STREAM_SIZE),
                                    'class' => 'avatar stream',
                                    'width' => AVATAR_STREAM_SIZE,
                                    'height' => AVATAR_STREAM_SIZE,
                                    'alt' =>
                                    ($profile->fullname) ? $profile->fullname :
                                    $profile->nickname));
        common_element_end('a');
        common_element('a', array('href' => $profile->profileurl,
                                  'class' => 'nickname'),
                       $profile->nickname);
        // FIXME: URL, image, video, audio
        common_element_start('p', array('class' => 'content'));
        common_raw($message->rendered);
        common_element_end('p');

        $messageurl = common_local_url('showmessage',
                                       array('message' => $message->id));

        // XXX: we need to figure this out better. Is this right?
        if (strcmp($message->uri, $messageurl) != 0 &&
            preg_match('/^http/', $message->uri)) {
            $messageurl = $message->uri;
        }
        common_element_start('p', 'time');
        common_element('a', array('class' => 'permalink',
                                  'href' => $messageurl,
                                  'title' => common_exact_date($message->created)),
                       common_date_string($message->created));
        if ($message->source) {
            common_text(_(' from '));
            $this->source_link($message->source);
        }

        common_element_end('p');

        common_element_end('li');
    }
}
