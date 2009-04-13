<?php
/**
 * Laconica, the distributed open-source microblogging tool
 *
 * Show a single message
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
 * @category  Personal
 * @package   Laconica
 * @author    Evan Prodromou <evan@controlyourself.ca>
 * @copyright 2008-2009 Control Yourself, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://laconi.ca/
 */
if (!defined('LACONICA')) { 
    exit(1); 
}

require_once INSTALLDIR.'/lib/mailbox.php';

/**
 * Show a single message
 *
 * // XXX: It is totally weird how this works!
 * 
 * @category Personal
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://laconi.ca/
 */

class ShowmessageAction extends MailboxAction
{
    /**
     * Message object to show
     */

    var $message = null;
    
    /**
     * The current user
     */
    
    var $user = null;

    /**
     * Load attributes based on database arguments
     *
     * Loads all the DB stuff
     *
     * @param array $args $_REQUEST array
     *
     * @return success flag
     */

    function prepare($args)
    {
        parent::prepare($args);
        
        $this->page = 1;
        
        $id            = $this->trimmed('message');
        $this->message = Message::staticGet('id', $id);

        if (!$this->message) {
            $this->clientError(_('No such message.'), 404);
            return false;
        }

        $this->user = common_current_user();

        return true;
    }

    function handle($args)
    {
        Action::handle($args);
                        
        if ($this->user && ($this->user->id == $this->message->from_profile || 
            $this->user->id == $this->message->to_profile)) {
                $this->showPage();
        } else {
            $this->clientError(_('Only the sender and recipient ' .
                'may read this message.'), 403);
            return;
        }
    }
    
    function title()
    {                        
        if ($this->user->id == $this->message->from_profile) {
            $to = $this->message->getTo();
            return sprintf(_("Message to %1\$s on %2\$s"),
                             $to->nickname,
                             common_exact_date($this->message->created));
        } else if ($this->user->id == $this->message->to_profile) {
            $from = $this->message->getFrom();
            return sprintf(_("Message from %1\$s on %2\$s"),
                             $from->nickname,
                             common_exact_date($this->message->created));
        }
    }
        
    function getMessages() 
    {    
        $message     = new Message();
        $message->id = $this->message->id;
        $message->find();
        return $message;
    }
    
    function getMessageProfile()
    {
        if ($this->user->id == $this->message->from_profile) {
            return $this->message->getTo();
        } else if ($this->user->id == $this->message->to_profile) {
            return $this->message->getFrom();
        } else {
            // This shouldn't happen
            return null;
        }
    }
    
    /**
     * Don't show local navigation
     *
     * @return void
     */

    function showLocalNavBlock()
    {
    }
    
    /**
     * Don't show page notice
     *
     * @return void
     */

    function showPageNoticeBlock()
    {
    }

    /**
     * Don't show aside
     *
     * @return void
     */

    function showAside() 
    {
    }
 
    /**
     * Don't show any instructions
     *
     * @return string
     */
     
    function getInstructions()
    {
        return '';
    }

    function isReadOnly($args)
    {
        return true;
    }
}
