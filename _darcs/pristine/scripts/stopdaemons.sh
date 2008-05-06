#!/bin/sh

# Laconica - a distributed open-source microblogging tool

# Copyright (C) 2008, Controlez-Vous, Inc.
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# This program tries to stop the daemons for Laconica that were
# previously started by startdaemons.sh

SDIR=`dirname $0`
DIR=`php $SDIR/getpiddir.php`

for f in jabberhandler ombhandler publichandler smshandler \
	 xmppconfirmhandler xmppdaemon; do

	FILES="$DIR/$f.*.pid"
	for ff in "$FILES" ; do

	 	echo -n "Stopping $f..."
	 	PID=`cat $ff`
		kill -3 $PID
		if kill -9 $PID ; then
			echo "DONE."
		else
			echo "FAILED."
		fi
		rm -f $ff
	done
done

