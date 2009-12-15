<?php
/*
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

/**
 * PHP 5.3 implementation of function currying, using native closures.
 * On 5.2 and lower we use the fallback implementation in util.php
 *
 * @param callback $fn
 * @param ... any remaining arguments will be appended to call-time params
 * @return callback
 */
function curry($fn) {
    $extra_args = func_get_args();
    array_shift($extra_args);
    return function() use ($fn, $extra_args) {
        $args = func_get_args();
        return call_user_func_array($fn,
            array_merge($args, $extra_args));
    };
}
