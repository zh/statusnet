<?php
/**
 * Table Definition for oauth_application_user
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Oauth_application_user extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'oauth_application_user';          // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $application_id;                  // int(4)  primary_key not_null
    public $access_type;                     // tinyint(1)
    public $token;                           // varchar(255)
    public $created;                         // datetime   not_null
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) {
        return Memcached_DataObject::staticGet('Oauth_application_user',$k,$v);
    }
    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getByKeys($user, $app)
    {
        if (empty($user) || empty($app)) {
            return null;
        }

        $oau = new Oauth_application_user();

        $oau->profile_id     = $user->id;
        $oau->application_id = $app->id;
        $oau->limit(1);

        $result = $oau->find(true);

        return empty($result) ? null : $oau;
    }

}
