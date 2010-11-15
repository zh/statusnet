<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class RemoteProfileAction extends ShowstreamAction
{
    function prepare($args)
    {
        OwnerDesignAction::prepare($args); // skip the ProfileAction code and replace it...

        $id = $this->arg('id');
        $this->user = false;
        $this->profile = Profile::staticGet('id', $id);

        if (!$this->profile) {
            $this->serverError(_('User has no profile.'));
            return false;
        }

        $this->tag = $this->trimmed('tag');
        $this->page = ($this->arg('page')) ? ($this->arg('page')+0) : 1;
        common_set_returnto($this->selfUrl());
        return true;
    }

    function handle($args)
    {
        // skip yadis thingy
        $this->showPage();
    }

    function title()
    {
        // maybe fixed in 0.9.x
        if (!empty($this->profile->fullname)) {
            $base = $this->profile->fullname . ' (' . $this->profile->nickname . ') ';
        } else {
            $base = $this->profile->nickname;
        }
    }

    function showContent()
    {
        $this->showProfile();
        // don't show notices
    }

    function getFeeds()
    {
        // none
    }

    function extraHead()
    {
        // none
    }
    function showLocalNav()
    {
        // none...?
    }
    function showSections()
    {
        ProfileAction::showSections();
        // skip tag cloud
    }

}