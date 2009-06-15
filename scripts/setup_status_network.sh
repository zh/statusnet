#!/bin/bash

source ./setup.cfg

export nickname=$1
export sitename=$2

export password=`pwgen 20`
export database=$nickname$DBBASE
export username=$nickname$USERBASE

# Create the db

mysqladmin -u $ADMIN --password=$ADMINPASS create $database

for f in laconica.sql sms_carrier.sql foreign_services.sql notice_source.sql; do
    mysql -u $ADMIN --password=$ADMINPASS $database < ../db/$f;
done

mysql -u $ADMIN --password=$ADMINPASS $SITEDB << ENDOFCOMMANDS

GRANT INSERT,SELECT,UPDATE,DELETE ON $database.* TO '$username'@'localhost' IDENTIFIED BY '$password';
GRANT INSERT,SELECT,UPDATE,DELETE ON $database.* TO '$username'@'%' IDENTIFIED BY '$password';
INSERT INTO status_network (nickname, dbhost, dbuser, dbpass, dbname, sitename, created)
VALUES ('$nickname', '$DBHOST', '$username', '$password', '$database', '$sitename', now());

ENDOFCOMMANDS

mkdir $AVATARBASE/$nickname
chmod a+w $AVATARBASE/$nickname
