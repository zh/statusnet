<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A plugin to add titles to notices
 *
 * PHP version 5
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
 *
 * @category  NoticeTitle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

define('NOTICE_TITLE_PLUGIN_VERSION', '0.1');

/**
 * NoticeTitle plugin to add an optional title to notices.
 *
 * Stores notice titles in a secondary table, notice_title.
 *
 * @category  NoticeTitle
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

class NoticeTitlePlugin extends Plugin
{
    /**
     * Database schema setup
     *
     * Add the notice_title helper table
     *
     * @see Schema
     * @see ColumnDef
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onCheckSchema()
    {
        $schema = Schema::get();

        // For storing titles for notices

        $schema->ensureTable('notice_title',
                             array(new ColumnDef('notice_id', 'integer', null, true, 'PRI'),
                                   new ColumnDef('title', 'varchar', Notice_title::MAXCHARS, false)));

        return true;
    }

    /**
     * Load related modules when needed
     *
     * @param string $cls Name of the class to be loaded
     *
     * @return boolean hook value; true means continue processing, false means stop.
     */

    function onAutoload($cls)
    {
        $dir = dirname(__FILE__);

        switch ($cls)
        {
        case 'Notice_title':
            include_once $dir . '/'.$cls.'.php';
            return false;
        default:
            return true;
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'NoticeTitle',
                            'version' => NOTICE_TITLE_PLUGIN_VERSION,
                            'author' => 'Evan Prodromou',
                            'homepage' => 'http://status.net/wiki/Plugin:NoticeTitle',
                            'rawdescription' =>
                            _m('Adds optional titles to notices'));
        return true;
    }

    function onStartShowNoticeFormData($form)
    {
        $form->out->element('input', array('type' => 'text',
                                           'id' => 'notice_title',
                                           'name' => 'notice_title',
                                           'size' => 40,
                                           'maxlength' => Notice_title::MAXCHARS,
                                           'value' => _m('Title'),
                                           'style' => 'color: 333333',
                                           'onFocus' => 'this.value = ""; this.style = \'color: black\';'));
        return true;
    }

    function onStartNoticeSaveWeb($action, &$authorId, &$text, &$options)
    {
        $title = $action->trimmed('notice_title');
        if (!empty($title)) {
            if (mb_strlen($title) > Notice_title::MAXCHARS) {
                throw new Exception(sprintf(_m("Notice title too long (max %d chars)", Notice_title::MAXCHARS)));
            }
        }
        return true;
    }

    function onEndNoticeSaveWeb($action, $notice)
    {
        if (!empty($notice)) {

            $title = $action->trimmed('notice_title');

            if (!empty($title)) {

                $nt = new Notice_title();

                $nt->notice_id = $notice->id;
                $nt->title     = $title;

                $nt->insert();
            }
        }

        return true;
    }

    function onStartShowNoticeItem($nli)
    {
        $title = Notice_title::fromNotice($nli->notice);

        if (!empty($title)) {
            $nli->out->element('h4', array('class' => 'notice_title'), $title);
        }

        return true;
    }
}

