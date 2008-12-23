<?php
/*
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

if (!defined('LACONICA')) { exit(1); }

function theme_file($relative) {
    $theme = common_config('site', 'theme');
    return INSTALLDIR.'/theme/'.$theme.'/'.$relative;
}

function theme_path($relative) {
    $theme = common_config('site', 'theme');
    $server = common_config('theme', 'server');
    if ($server) {
        return 'http://'.$server.'/'.$theme.'/'.$relative;
    } else {
        return common_path('theme/'.$theme.'/'.$relative);
    }
}