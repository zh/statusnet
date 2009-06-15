<?php
/**
 * Table Definition for status_network
 */

class Status_network extends DB_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'status_network';                  // table name
    public $nickname;                        // varchar(64)  primary_key not_null
    public $hostname;                        // varchar(255)  unique_key
    public $pathname;                        // varchar(255)  unique_key
    public $dbhost;                          // varchar(255)
    public $dbuser;                          // varchar(255)
    public $dbpass;                          // varchar(255)
    public $dbname;                          // varchar(255)
    public $sitename;                        // varchar(255)
    public $theme;                           // varchar(255)
    public $logo;                            // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Status_network',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function setupDB($dbhost, $dbuser, $dbpass, $dbname)
    {
        global $config;

        $config['db']['database_'.$dbname] = "mysqli://$dbuser:$dbpass@$dbhost/$dbname";
        $config['db']['ini_'.$dbname] = INSTALLDIR.'/classes/statusnet.ini';
        $config['db']['table_status_network'] = $dbname;

        return true;
    }

    static function setupSite($servername, $pathname, $wildcard)
    {
        global $config;

        // XXX I18N, probably not crucial for hostnames
        // XXX This probably needs a tune up

        if (0 == strncasecmp(strrev($wildcard), strrev($servername), strlen($wildcard))) {
            $parts = explode('.', $servername);
            $sn = Status_network::staticGet('nickname', strtolower($parts[0]));
        } else {
            $sn = Status_network::staticGet('hostname', strtolower($servername));
        }

        if (!empty($sn)) {
            $dbhost = (empty($sn->dbhost)) ? 'localhost' : $sn->dbhost;
            $dbuser = (empty($sn->dbuser)) ? $sn->nickname : $sn->dbuser;
            $dbpass = $sn->dbpass;
            $dbname = (empty($sn->dbname)) ? $sn->nickname : $sn->dbname;

            $config['db']['database'] = "mysqli://$dbuser:$dbpass@$dbhost/$dbname";

            $config['site']['name'] = $sn->sitename;

            if (!empty($sn->theme)) {
                $config['site']['theme'] = $sn->theme;
            }
            if (!empty($sn->logo)) {
                $config['site']['logo'] = $sn->logo;
            }

            return true;
        } else {
            return false;
        }
    }
}
