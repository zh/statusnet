#!/bin/bash

if [[ $1 = "start" ]]
then
	echo "Stopping any running daemons..."
	/usr/local/bin/searchd --config /usr/local/etc/sphinx.conf --stop 2> /dev/null
	echo "Starting sphinx search daemon..."
	/usr/local/bin/searchd --config /usr/local/etc/sphinx.conf 2> /dev/null
fi

if [[ $1 = "stop" ]]
then
	echo "Stopping sphinx search daemon..."
	/usr/local/bin/searchd --config /usr/local/etc/sphinx.conf --stop 2> /dev/null
fi
