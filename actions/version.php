<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
 *
 * Show version information for this software and plugins
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
 * @category Info
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Version info page
 *
 * A page that shows version information for this site. Helpful for
 * debugging, for giving credit to authors, and for linking to more
 * complete documentation for admins.
 *
 * @category Info
 * @package  StatusNet
 * @author   Evan Prodromou <evan@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPLv3
 * @link     http://status.net/
 */

class VersionAction extends Action
{
    var $pluginVersions = array();

    /**
     * Return true since we're read-only.
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */

    function isReadOnly($args)
    {
        return true;
    }

    /**
     * Returns the page title
     *
     * @return string page title
     */

    function title()
    {
        return sprintf(_("StatusNet %s"), STATUSNET_VERSION);
    }

    /**
     * Prepare to run
     *
     * Fire off an event to let plugins report their
     * versions.
     *
     * @param array $args array misc. arguments
     *
     * @return boolean true
     */

    function prepare($args)
    {
        parent::prepare($args);

        Event::handle('PluginVersion', array(&$this->pluginVersions));

        return true;
    }

    /**
     * Execute the action
     *
     * Shows a page with the version information in the
     * content area.
     *
     * @param array $args ignored.
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
        $this->showPage();
    }

    /**
     * Show version information
     *
     * @return void
     */

    function showContent()
    {
        $this->elementStart('p');

        $this->raw(sprintf(_('This site is powered by %s version %s, '.
                             'Copyright 2008-2010 StatusNet, Inc. '.
                             'and contributors.'),
                           XMLStringer::estring('a', array('href' => 'http://status.net/'),
                                                _('StatusNet')),
                           STATUSNET_VERSION));
        $this->elementEnd('p');

        $this->element('h2', null, _('Contributors'));

        $this->element('p', null, implode(', ', $this->contributors));

        $this->element('h2', null, _('License'));

        $this->element('p', null,
                       _('StatusNet is free software: you can redistribute it and/or modify '.
                         'it under the terms of the GNU Affero General Public License as published by '.
                         'the Free Software Foundation, either version 3 of the License, or '.
                         '(at your option) any later version. '));

        $this->element('p', null,
                       _('This program is distributed in the hope that it will be useful, '.
                         'but WITHOUT ANY WARRANTY; without even the implied warranty of '.
                         'MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the '.
                         'GNU Affero General Public License for more details. '));

        $this->elementStart('p');
        $this->raw(sprintf(_('You should have received a copy of the GNU Affero General Public License '.
                             'along with this program.  If not, see %s.'),
                           XMLStringer::estring('a', array('href' => 'http://www.gnu.org/licenses/agpl.html'),
                                                'http://www.gnu.org/licenses/agpl.html')));
        $this->elementEnd('p');

        // XXX: Theme information?

        if (count($this->pluginVersions)) {
            $this->element('h2', null, _('Plugins'));

            foreach ($this->pluginVersions as $plugin) {
                $this->elementStart('dl');
                $this->element('dt', null, _('Name'));
                if (array_key_exists('homepage', $plugin)) {
                    $this->elementStart('dd');
                    $this->element('a', array('href' => $plugin['homepage']),
                                   $plugin['name']);
                    $this->elementEnd('dd');
                } else {
                    $this->element('dd', null, $plugin['name']);
                }
                $this->element('dt', null, _('Version'));
                $this->element('dd', null, $plugin['version']);
                if (array_key_exists('author', $plugin)) {
                    $this->element('dt', null, _('Author(s)'));
                    $this->element('dd', null, $plugin['author']);
                }
                if (array_key_exists('rawdescription', $plugin)) {
                    $this->element('dt', null, _('Description'));
                    $this->elementStart('dd');
                    $this->raw($plugin['rawdescription']);
                    $this->elementEnd('dd');
                } else if (array_key_exists('description', $plugin)) {
                    $this->element('dt', null, _('Description'));
                    $this->element('dd', null, $plugin['description']);
                }
                $this->elementEnd('dl');
            }
        }

    }

    var $contributors = array('Evan Prodromou (StatusNet)',
                              'Zach Copley (StatusNet)',
                              'Earle Martin (StatusNet)',
                              'Marie-Claude Doyon (StatusNet)',
                              'Sarven Capadisli (StatusNet)',
                              'Robin Millette (StatusNet)',
                              'Ciaran Gultnieks',
                              'Michael Landers',
                              'Ori Avtalion',
                              'Garret Buell',
                              'Mike Cochrane',
                              'Matthew Gregg',
                              'Florian Biree',
                              'Erik Stambaugh',
                              'drry',
                              'Gina Haeussge',
                              'Tryggvi Björgvinsson',
                              'Adrian Lang',
                              'Meitar Moscovitz',
                              'Sean Murphy',
                              'Leslie Michael Orchard',
                              'Eric Helgeson',
                              'Ken Sedgwick',
                              'Brian Hendrickson',
                              'Tobias Diekershoff',
                              'Dan Moore',
                              'Fil',
                              'Jeff Mitchell',
                              'Brenda Wallace',
                              'Jeffery To',
                              'Federico Marani',
                              'Craig Andrews',
                              'mEDI',
                              'Brett Taylor');
}
