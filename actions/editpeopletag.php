<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Edit an existing group
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
 * @category  Group
 * @package   StatusNet
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Add a new group
 *
 * This is the form for adding a new group
 *
 * @category Group
 * @package  StatusNet
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link     http://status.net/
 */

class EditpeopletagAction extends OwnerDesignAction
{

    var $msg, $confirm, $confirm_args=array();

    function title()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && $this->boolean('delete')) {
            return sprintf(_('Delete %s people tag'), $this->peopletag->tag);
        }
        return sprintf(_('Edit people tag %s'), $this->peopletag->tag);
    }

    /**
     * Prepare to run
     */

    function prepare($args)
    {
        parent::prepare($args);

        if (!common_logged_in()) {
            $this->clientError(_('Not logged in.'));
            return false;
        }

        $id = $this->arg('id');
        $tagger_arg = $this->arg('tagger');
        $tag_arg = $this->arg('tag');

        $tagger = common_canonical_nickname($tagger_arg);
        $tag = common_canonical_tag($tag_arg);

        $current = common_current_user();

        // Permanent redirect on non-canonical tag

        if ($tagger_arg != $tagger || $tag_arg != $tag) {
            $args = array('tagger' => $tagger, 'tag' => $tag);
            common_redirect(common_local_url('editpeopletag', $args), 301);
            return false;
        }

        $user = null;
        if ($id) {
            $this->peopletag = Profile_list::staticGet('id', $id);
            if (!empty($this->peopletag)) {
                $user = User::staticGet('id', $this->peopletag->tagger);
            }
        } else {
            if (!$tagger) {
                $this->clientError(_('No tagger or ID.'), 404);
                return false;
            }

            $user = User::staticGet('nickname', $tagger);
            $this->peopletag = Profile_list::pkeyGet(array('tagger' => $user->id, 'tag' => $tag));
        }

        if (!$this->peopletag) {
            $this->clientError(_('No such peopletag.'), 404);
            return false;
        }

        if (!$user) {
            // This should not be happening
            $this->clientError(_('Not a local user.'), 404);
            return false;
        }

        if ($current->id != $user->id) {
            $this->clientError(_('You must be the creator of the tag to edit it.'), 404);
            return false;
        }

        $this->tagger = $user->getProfile();

        return true;
    }

    /**
     * Handle the request
     *
     * On GET, show the form. On POST, try to save the group.
     *
     * @param array $args unused
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->trySave();
        } else {
            $this->showForm();
        }
    }

    function showConfirm($msg=null, $fwd=null)
    {
        $this->confirm = $msg;
        $this->confirm_args = $fwd;
        $this->showPage();
    }

    function showConfirmForm()
    {
        $this->elementStart('form', array('id' => 'form_peopletag_edit_confirm',
                                          'class' => 'form_settings',
                                          'method' => 'post',
                                          'action' => common_local_url('editpeopletag',
                                              array('tagger' => $this->tagger->nickname,
                                                    'tag' => $this->peopletag->tag))));
        $this->elementStart('fieldset');
        $this->hidden('token', common_session_token());
        $this->hidden('id', $this->arg('id'));

        foreach ($this->confirm_args as $key => $val) {
            $this->hidden($key, $val);
        }

        $this->submit('form_action-no',
                      _m('BUTTON','No'),
                      'submit form_action-primary',
                      'cancel');
        $this->submit('form_action-yes',
                      _m('BUTTON','Yes'),
                      'submit form_action-secondary',
                      'confirm');
        $this->elementEnd('fieldset');
        $this->elementEnd('form');
    }

    function showForm($msg=null)
    {
        $this->msg = $msg;
        $this->showPage();
    }

    function showLocalNav()
    {
        $nav = new PeopletagGroupNav($this, $this->peopletag);
        $nav->show();
    }

    function showContent()
    {
        if ($this->confirm) {
            $this->showConfirmForm();
            return;
        }

        $form = new PeopletagEditForm($this, $this->peopletag);
        $form->show();

        $form->showProfileList();
    }

    function showPageNotice()
    {
        if ($this->msg) {
            $this->element('p', 'error', $this->msg);
        } else if ($this->confirm) {
            $this->element('p', 'instructions', $this->confirm);
        } else {
            $this->element('p', 'instructions',
                           _('Use this form to edit the people tag.'));
        }
    }

    function showScripts()
    {
        parent::showScripts();
        $this->autofocus('tag');
    }

    function trySave()
    {
        $tag         = common_canonical_tag($this->trimmed('tag'));
        $description = $this->trimmed('description');
        $private     = $this->boolean('private');
        $delete      = $this->arg('delete');
        $confirm     = $this->arg('confirm');
        $cancel      = $this->arg('cancel');

        if ($delete && $cancel) {
            $this->showForm(_('Delete aborted.'));
            return;
        }

        $set_private = $private && $this->peopletag->private != $private;

        if ($delete && !$confirm) {
            $this->showConfirm(_('Deleting this tag will permanantly remove ' .
                                 'all its subscription and membership records. ' .
                                 'Do you still want to continue?'), array('delete' => 1));
            return;
        } else if (common_valid_tag($tag)) {
            $this->showForm(_('Invalid tag.'));
            return;
        } else if ($tag != $this->peopletag->tag && $this->tagExists($tag)) {
            $this->showForm(sprintf(_('You already have a tag named %s.'), $tag));
            return;
        } else if (Profile_list::descriptionTooLong($description)) {
            $this->showForm(sprintf(_('description is too long (max %d chars).'), Profile_list::maxDescription()));
            return;
        } else if ($set_private && !$confirm && !$cancel) {
            $fwd = array('tag' => $tag,
                         'description' => $description,
                         'private' => (int) $private);

            $this->showConfirm(_('Setting a public tag as private will ' .
                                 'permanently remove all the existing ' .
                                 'subscriptions to it. Do you still want to continue?'), $fwd);
            return;
        }

        $this->peopletag->query('BEGIN');

        $orig = clone($this->peopletag);

        $this->peopletag->tag         = $tag;
        $this->peopletag->description = $description;
        if (!$set_private || $confirm) {
            $this->peopletag->private     = $private;
        }

        $result = $this->peopletag->update($orig);

        if (!$result) {
            common_log_db_error($this->group, 'UPDATE', __FILE__);
            $this->serverError(_('Could not update peopletag.'));
        }

        $this->peopletag->query('COMMIT');

        if ($set_private && $confirm) {
            Profile_tag_subscription::cleanup($this->peopletag);
        }

        if ($delete) {
            // This might take quite a bit of time.
            $this->peopletag->delete();
            // send home.
            common_redirect(common_local_url('all',
                                         array('nickname' => $this->tagger->nickname)),
                            303);
        }

        if ($tag != $orig->tag) {
            common_redirect(common_local_url('editpeopletag',
                                             array('tagger' => $this->tagger->nickname,
                                                   'tag'    => $tag)),
                            303);
        } else {
            $this->showForm(_('Options saved.'));
        }
    }

    function tagExists($tag)
    {
        $args = array('tagger' => $this->tagger->id, 'tag' => $tag);
        $ptag = Profile_list::pkeyGet($args);

        return !empty($ptag);
    }
}
