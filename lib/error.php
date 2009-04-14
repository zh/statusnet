<?php

/**
 * Error action.
 *
 * PHP version 5
 *
 * @category Action
 * @package  Laconica
 * @author   Evan Prodromou <evan@controlyourself.ca>
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 *
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 * Base class for displaying HTTP errors
 *
 * @category Action
 * @package  Laconica
 * @author   Zach Copley <zach@controlyourself.ca>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://laconi.ca/
 */
class ErrorAction extends Action
{
    var $code    = null;
    var $message = null;
    var $status  = null;
    var $default = null;

    function __construct($message, $code, $output='php://output', $indent=true)
    {
        parent::__construct($output, $indent);

        $this->code = $code;
        $this->message = $message;

        // XXX: hack alert: usually we aren't going to
        // call this page directly, but because it's
        // an action it needs an args array anyway
        $this->prepare($_REQUEST);
    }

    /**
     *  To specify additional HTTP headers for the action
     *
     *  @return void
     */
    function extraHeaders()
    {
        $status_string = $this->status[$this->code];
        header('HTTP/1.1 '.$this->code.' '.$status_string);
    }
    
    /**
     * Display content.
     *
     * @return nothing
     */
    function showContent()
    {
        $this->element('div', array('class' => 'error'), $this->message);
    }

    /**
     * Page title.
     *
     * @return page title
     */
    function title()
    {
        return $this->message;
    }

    function isReadOnly($args)
    {
        return true;
    }
    
    function showPage() 
    {
        parent::showPage();
        
        // We don't want to have any more output after this
        exit();
    }

    // Overload a bunch of stuff so the page isn't too bloated

    function showBody()
    {
        $this->elementStart('body', array('id' => 'error'));
        $this->elementStart('div', array('id' => 'wrap'));
        $this->showHeader();
        $this->showCore();
        $this->showFooter();
        $this->elementEnd('div');
        $this->elementEnd('body');
    }

    function showCore()
    {
        $this->elementStart('div', array('id' => 'core'));
        $this->showContentBlock();
        $this->elementEnd('div');
    }

    function showHeader()
    {
        $this->elementStart('div', array('id' => 'header'));
        $this->showLogo();
        $this->showPrimaryNav();
        $this->elementEnd('div');
    }

}
