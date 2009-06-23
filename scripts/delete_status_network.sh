#!/bin/bash

source /etc/laconica/setup.cfg

export nickname=$1

export database=$nickname$DBBASE

# Create the db

mysqladmin -h $DBHOST -u $ADMIN --password=$ADMINPASS -f drop $database

mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $SITEDB << ENDOFCOMMANDS

delete from status_network where nickname = '$nickname';

ENDOFCOMMANDS

for top in $AVATARBASE $FILEBASE $BACKGROUNDBASE; do
    rm -Rf $top/$nickname
done
