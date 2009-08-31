#!/bin/bash
#
# ******************************* WARNING *********************************
# Do not run this script until you have read and understood the information
# below, AND backed up your database. Failure to observe these instructions
# may result in losing all the data in your database.
#
# This script is used to upgrade StatusNet's PostgreSQL database to the
# latest version. It does the following:
# 
#  1. Dumps the existing data to /tmp/rebuilddb_psql.sql
#  2. Clears out the objects (tables, etc) in the database schema
#  3. Reconstructs the database schema using the latest script
#  4. Restores the data dumped in step 1
#
# You MUST run this script as the 'postgres' user.
# You MUST be able to write to /tmp/rebuilddb_psql.sql
# You MUST specify the statusnet database user and database name on the
# command line, e.g. ./rebuilddb_psql.sh myuser mydbname
#

user=$1
DB=$2

cd `dirname $0`

pg_dump -a -D --disable-trigger $DB > /tmp/rebuilddb_psql.sql
psql -c "drop schema public cascade; create schema public;" $DB
psql -c "grant all privileges on schema public to $user;" $DB
psql $DB < ../db/statusnet_pg.sql
psql $DB < /tmp/rebuilddb_psql.sql
for tab in `psql -c '\dts' $DB -tA | cut -d\| -f2`; do
  psql -c "ALTER TABLE \"$tab\" OWNER TO $user;" $DB
done
