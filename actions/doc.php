<?php

/**
 * Documentation action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Documentation class.
 *
 * @category Action
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @author   Robin Millette <millette@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */
class DocAction extends Action
{
    var $filename;
    var $title;

    /**
     * Class handler.
     *
     * @param array $args array of arguments
     *
     * @return nothing
     */
    function handle($args)
    {
        parent::handle($args);
        $this->title    = $this->trimmed('title');
        $this->filename = INSTALLDIR.'/doc-src/'.$this->title;
        if (!file_exists($this->filename)) {
            $this->clientError(_('No such document.'));
            return;
        }
        $this->showPage();
    }

    // overrrided to add entry-title class
    function showPageTitle() {
        $this->element('h1', array('class' => 'entry-title'), $this->title());
    }

    // overrided to add hentry, and content-inner classes
    function showContentBlock()
     {
         $this->elementStart('div', array('id' => 'content', 'class' => 'hentry'));
         $this->showPageTitle();
         $this->showPageNoticeBlock();
         $this->elementStart('div', array('id' => 'content_inner',
             'class' => 'entry-content'));
         // show the actual content (forms, lists, whatever)
         $this->showContent();
         $this->elementEnd('div');
         $this->elementEnd('div');
     }

    /**
     * Display content.
     *
     * @return nothing
     */
    function showContent()
    {
        $c      = file_get_contents($this->filename);
        $output = common_markup_to_html($c);
        $this->raw($output);
    }

    /**
     * Page title.
     *
     * @return page title
     */
    function title()
    {
        return ucfirst($this->title);
    }

    function isReadOnly($args)
    {
        return true;
    }
}
