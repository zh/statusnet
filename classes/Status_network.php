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

        $sn = null;

        // XXX I18N, probably not crucial for hostnames
        // XXX This probably needs a tune up

        if (0 == strncasecmp(strrev($wildcard), strrev($servername), strlen($wildcard))) {
            // special case for exact match
            if (0 == strcasecmp($servername, $wildcard)) {
                $sn = Status_network::staticGet('nickname', '');
            } else {
                $parts = explode('.', $servername);
                $sn = Status_network::staticGet('nickname', strtolower($parts[0]));
            }
        } else {
            $sn = Status_network::staticGet('hostname', strtolower($servername));
        }

        if (!empty($sn)) {
            if (!empty($sn->hostname) && 0 != strcasecmp($sn->hostname, $servername)) {
                $sn->redirectToHostname();
            }
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

            return $sn;
        } else {
            return null;
        }
    }

    // Code partially mooked from http://www.richler.de/en/php-redirect/
    // (C) 2006 by Heiko Richler  http://www.richler.de/
    // LGPL

    function redirectToHostname()
    {
        $destination = 'http://'.$this->hostname;
        $destination .= $_SERVER['REQUEST_URI'];

        $args = $_GET;

        if (isset($args['p'])) {
            unset($args['p']);
        }

        $query = http_build_query($args);

        if (strlen($query) > 0) {
            $destination .= '?' . $query;
        }

        $old = 'http'.
          (($_SERVER['HTTPS'] == 'on') ? 'S' : '').
          '://'.
          $_SERVER['HTTP_HOST'].
          $_SERVER['REQUEST_URI'].
          $_SERVER['QUERY_STRING'];
        if ($old == $destination) { // this would be a loop!
            // error_log(...) ?
            return false;
        }

        header('HTTP/1.1 301 Moved Permanently');
        header("Location: $destination");

        print "<a href='$destination'>$destination</a>\n";

        exit;
    }
}
