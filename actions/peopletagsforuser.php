<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * People tags for a user
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
 * @package   StatusNet
 * @author    Shashi Gowda <connect2shashi@gmail.com>
 * @copyright 2008-2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

require_once INSTALLDIR.'/lib/peopletaglist.php';

class PeopletagsforuserAction extends OwnerDesignAction
{
    var $page = null;
    var $tagged = null;

    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            return sprintf(_("People tags for %s"), $this->tagged->nickname);
        } else {
            return sprintf(_("People tags for %s, page %d"), $this->tagged->nickname, $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = array('nickname' => $nickname);
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('peopletagsforuser', $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->tagged = $this->user->getProfile();

        if (!$this->tagged) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;

        return true;
    }

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showAnonymousMessage()
    {
        $notice =
          sprintf(_('These are people tags for **%s**. ' .
                    'People tags are how you sort similar ' .
                    'people on %%%%site.name%%%%, a [micro-blogging]' .
                    '(http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                    'based on the Free Software [StatusNet](http://status.net/) tool. ' .
                    'You can easily keep track of what they ' .
                    'are doing by subscribing to the tag\'s timeline.' ), $this->tagged->nickname);
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($notice));
        $this->elementEnd('div');
    }

    function showPageNotice()
    {
        $this->elementStart('dl', 'filter_tags');
        $this->elementStart('dd', array('id' => 'filter_tags_for',
                                         'class' => 'child_1'));

        $user = common_current_user();
        $text = ($this->tagged->id == @$user->id) ? _('People tags by you') :
                sprintf(_('People tags by %s'), $this->tagged->nickname);
        $this->element('a',
                       array('href' =>
                             common_local_url('peopletagsbyuser',
                                              array('nickname' => $this->tagged->nickname))),
                           $text);
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }


    function showContent()
    {
        #TODO: controls here.

        $offset = ($this->page-1) * PEOPLETAGS_PER_PAGE;
        $limit  = PEOPLETAGS_PER_PAGE + 1;

        $ptags = $this->tagged->getOtherTags(common_current_user(), $offset, $limit);

        $pl = new PeopletagList($ptags, $this);
        $cnt = $pl->show();

        if ($cnt == 0) {
            $this->showEmptyListMessage();
        }
        $this->pagination($this->page > 1, $cnt > PEOPLETAGS_PER_PAGE,
                          $this->page, 'peopletagsforuser', array('nickname' => $this->tagged->id));
    }

    function showEmptyListMessage()
    {
        $message = sprintf(_('%s has not been [tagged](%%%%doc.tags%%%%) by anyone yet.'), $this->tagged->nickname);
        $this->elementStart('div', 'guide');
        $this->raw(common_markup_to_html($message));
        $this->elementEnd('div');
    }

    function showSections()
    {
        #TODO: tags with most subscribers
        #TODO: tags with most "members"
    }
}
