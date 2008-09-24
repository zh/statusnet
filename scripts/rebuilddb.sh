#!/bin/bash

export user=$1
export password=$2
export DB=$3
export SCR=$4

mysqldump -u $user --password=$password -c -t --hex-blob $DB > /tmp/$DB.sql
mysqladmin -u $user --password=$password -f drop $DB
mysqladmin -u $user --password=$password create $DB
mysql -u $user --password=$password $DB < $SCR
mysql -u $user --password=$password $DB < /tmp/$DB.sql


