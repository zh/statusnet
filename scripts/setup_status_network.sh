#!/bin/bash

source /etc/laconica/setup.cfg

export nickname=$1
export sitename=$2

export password=`$PWDGEN`
export database=$nickname$DBBASE
export username=$nickname$USERBASE

# Create the db

mysqladmin -h $DBHOST -u $ADMIN --password=$ADMINPASS create $database

for f in laconica.sql innodb.sql sms_carrier.sql foreign_services.sql notice_source.sql; do
    mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $database < ../db/$f;
done

mysql -h $DBHOST -u $ADMIN --password=$ADMINPASS $SITEDB << ENDOFCOMMANDS

GRANT INSERT,SELECT,UPDATE,DELETE ON $database.* TO '$username'@'localhost' IDENTIFIED BY '$password';
GRANT INSERT,SELECT,UPDATE,DELETE ON $database.* TO '$username'@'%' IDENTIFIED BY '$password';
INSERT INTO status_network (nickname, dbhost, dbuser, dbpass, dbname, sitename, created)
VALUES ('$nickname', '$DBHOSTNAME', '$username', '$password', '$database', '$sitename', now());

ENDOFCOMMANDS

for top in $AVATARBASE $FILEBASE $BACKGROUNDBASE; do
    mkdir $top/$nickname
    chmod a+w $top/$nickname
done
