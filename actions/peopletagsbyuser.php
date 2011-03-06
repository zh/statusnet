<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * People tags by a user
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

class PeopletagsbyuserAction extends OwnerDesignAction
{
    var $page = null;
    var $tagger = null;
    var $tags = null;

    function isReadOnly($args)
    {
        return true;
    }

    function title()
    {
        if ($this->page == 1) {
            if ($this->isOwner()) {
                if ($this->arg('private')) {
                    return _('Private people tags by you');
                } else if ($this->arg('public')) {
                    return _('Public people tags by you');
                }
                return _('People tags by you');
            }
            return sprintf(_("People tags by %s"), $this->tagger->nickname);
        } else {
            return sprintf(_("People tags by %s, page %d"), $this->tagger->nickname, $this->page);
        }
    }

    function prepare($args)
    {
        parent::prepare($args);

        if ($this->arg('public') && $this->arg('private')) {
            $this->args['public'] = $this->args['private'] = false;
        }

        $nickname_arg = $this->arg('nickname');
        $nickname = common_canonical_nickname($nickname_arg);

        // Permanent redirect on non-canonical nickname

        if ($nickname_arg != $nickname) {
            $args = $this->getSelfUrlArgs();
            if ($this->arg('page') && $this->arg('page') != 1) {
                $args['page'] = $this->arg['page'];
            }
            common_redirect(common_local_url('peopletagsbyuser', $args), 301);
            return false;
        }

        $this->user = User::staticGet('nickname', $nickname);

        if (!$this->user) {
            $this->clientError(_('No such user.'), 404);
            return false;
        }

        $this->tagger = $this->user->getProfile();

        if (!$this->tagger) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;


        $offset = ($this->page-1) * PEOPLETAGS_PER_PAGE;
        $limit  = PEOPLETAGS_PER_PAGE + 1;

        $user = common_current_user();
        if ($this->arg('public')) {
            $this->tags = $this->tagger->getOwnedTags(false, $offset, $limit);
        } else if ($this->arg('private')) {
            if (empty($user)) {
                $this->clientError(_('Not logged in'), 403);
            }

            if ($this->isOwner()) {
                $this->tags = $this->tagger->getPrivateTags($offset, $limit);
            } else {
                $this->clientError(_('You cannot view others\' private people tags'), 403);
            }
        } else {
            $this->tags = $this->tagger->getOwnedTags(common_current_user(), $offset, $limit);
        }
        return true;
    }

    function handle($args)
    {
        parent::handle($args);

		# Post from the tag dropdown; redirect to a GET

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		    common_redirect(common_local_url('peopletagsbyuser', $this->getSelfUrlArgs()), 303);
            return;
		}

        $this->showPage();
    }

    function showModeSelector()
    {
        $this->elementStart('dl', array('id'=>'filter_tags'));
        $this->element('dt', null, _('Mode'));
        $this->elementStart('dd');
        $this->elementStart('ul');
        $this->elementStart('li', array('id' => 'filter_tags_for',
                                         'class' => 'child_1'));
        $this->element('a',
                       array('href' =>
                             common_local_url('peopletagsforuser',
                                              array('nickname' => $this->user->nickname))),
                       sprintf(_('People tags for %s'), $this->tagger->nickname));
        $this->elementEnd('li');

        if ($this->isOwner()) {
            $this->elementStart('li', array('id'=>'filter_tags_item'));
            $this->elementStart('form', array('name' => 'modeselector',
                                               'id' => 'form_filter_bymode',
                                               'action' => common_local_url('peopletagsbyuser',
                                                    array('nickname' => $this->tagger->nickname)),
                                               'method' => 'post'));
            $this->elementStart('fieldset');
            $this->element('legend', null, _('Select tag to filter'));

            $priv = $this->arg('private');
            $pub  = $this->arg('public');

            if (!$priv && !$pub) {
                $priv = $pub = true;
            }
            $this->checkbox('private', _m('Private'), $priv,
                                _m('Show private tags'));
            $this->checkbox('public', _m('Public'), $pub,
                                _m('Show public tags'));
            $this->hidden('nickname', $this->user->nickname);
            $this->submit('submit', _('Go'));
            $this->elementEnd('fieldset');
            $this->elementEnd('form');
            $this->elementEnd('li');
        }
        $this->elementEnd('ul');
        $this->elementEnd('dd');
        $this->elementEnd('dl');
    }
    function showLocalNav()
    {
        $nav = new PersonalGroupNav($this);
        $nav->show();
    }

    function showAnonymousMessage()
    {
        $notice =
          sprintf(_('These are people tags created by **%s**. ' .
                    'People tags are how you sort similar ' .
                    'people on %%%%site.name%%%%, a [micro-blogging]' .
                    '(http://en.wikipedia.org/wiki/Micro-blogging) service ' .
                    'based on the Free Software [StatusNet](http://status.net/) tool. ' .
                    'You can easily keep track of what they ' .
                    'are doing by subscribing to the tag\'s timeline.' ), $this->tagger->nickname);
        $this->elementStart('div', array('id' => 'anon_notice'));
        $this->raw(common_markup_to_html($notice));
        $this->elementEnd('div');
    }

    function showPageNotice()
    {
        $this->elementStart('div', 'instructions');
        $this->showModeSelector();
        $this->elementEnd('div');
    }

    function showContent()
    {
        #TODO: controls here.

        $pl = new PeopletagList($this->tags, $this);
        $cnt = $pl->show();

        if ($cnt == 0) {
            $this->showEmptyListMessage();
        }
        $this->pagination($this->page > 1, $cnt > PEOPLETAGS_PER_PAGE,
                          $this->page, 'peopletagsbyuser', $this->getSelfUrlArgs());
    }

    function getSelfUrlArgs()
    {
        $args = array();
        if ($this->arg('private')) {
            $args['private'] = 1;
        } else if ($this->arg('public')) {
            $args['public'] = 1;
        }
        $args['nickname'] = $this->trimmed('nickname');

        return $args;
    }

    function isOwner()
    {
        $user = common_current_user();
        return !empty($user) && $user->id == $this->tagger->id;
    }

    function showEmptyListMessage()
    {
        $message = sprintf(_('%s has not created any [people tags](%%%%doc.tags%%%%) yet.'), $this->tagger->nickname);
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
