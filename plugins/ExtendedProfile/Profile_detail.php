<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class Profile_detail extends Memcached_DataObject
{
    public $id;
    public $profile_id;
    public $field;
    public $index; // relative ordering of multiple values in the same field
    public $value; // primary text value
    public $rel; // detail for some field types; eg "home", "mobile", "work" for phones or "aim", "irc", "xmpp" for IM
    public $ref_profile; // for people types, allows pointing to a known profile in the system

}