<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */

require_once 'Crypt/RSA.php';

class Magicsig extends Memcached_DataObject
{

    const PUBLICKEYREL = 'magic-public-key';
    
    public $__table = 'magicsig';

    public $user_id;
    public $keypair;
    public $alg;
    
    private $publicKey;
    private $privateKey;
    
    public function __construct($alg = 'RSA-SHA256')
    {
        $this->alg = $alg;
    }
    
    public /*static*/ function staticGet($k, $v=null)
    {
        $obj =  parent::staticGet(__CLASS__, $k, $v);
        if (!empty($obj)) {
            return Magicsig::fromString($obj->keypair);
        }

        return $obj;
    }


    function table()
    {
        return array(
            'user_id' => DB_DATAOBJECT_INT,
            'keypair' => DB_DATAOBJECT_STR + DB_DATAOBJECT_NOTNULL,
            'alg'     => DB_DATAOBJECT_STR
        );
    }

    static function schemaDef()
    {
        return array(new ColumnDef('user_id', 'integer',
                                   null, false, 'PRI'),
                     new ColumnDef('keypair', 'varchar',
                                   255, false),
                     new ColumnDef('alg', 'varchar',
                                   64, false));
    }


    function keys()
    {
        return array_keys($this->keyTypes());
    }

    function keyTypes()
    {
        return array('user_id' => 'K');
    }

    function sequenceKey() {
        return array(false, false, false);
    }

    function insert()
    {
        $this->keypair = $this->toString();

        return parent::insert();
    }

    public function generate($user_id, $key_length = 512)
    {
        // @fixme new key generation
        $this->user_id = $user_id;
        $this->insert();
    }


    public function toString($full_pair = true)
    {
        $public_key = $this->_rsa->_public_key;
        $private_key = $this->_rsa->_private_key;

        $mod = base64_url_encode($public_key->getModulus());
        $exp = base64_url_encode($public_key->getExponent());
        $private_exp = '';
        if ($full_pair && $private_key->getExponent()) {
            $private_exp = '.' . base64_url_encode($private_key->getExponent());
        }

        return 'RSA.' . $mod . '.' . $exp . $private_exp; 
    }
    
    public static function fromString($text)
    {
        $magic_sig = new Magicsig();
        
        // remove whitespace
        $text = preg_replace('/\s+/', '', $text);

        // parse components
        if (!preg_match('/RSA\.([^\.]+)\.([^\.]+)(.([^\.]+))?/', $text, $matches)) {
            return false;
        }
        
        $mod = $matches[1];
        $exp = $matches[2];
        if (!empty($matches[4])) {
            $private_exp = $matches[4];
        } else {
            $private_exp = false;
        }

        $magic_sig->loadKey($mod, $exp, 'public');
        if ($private_exp) {
            $magic_sig->loadKey($mod, $private_exp, 'private');
        }

        return $magic_sig;
    }

    public function loadKey($mod, $exp, $type = 'public')
    {
        common_log(LOG_DEBUG, "Adding ".$type." key: (".$mod .', '. $exp .")");

        $rsa = new Crypt_RSA();
        $rsa->signatureMode = CRYPT_RSA_SIGNATURE_PKCS1;
        $rsa->setHash('sha256');
        $rsa->modulus = new Math_BigInteger(base64_url_decode($mod), 256);
        $rsa->k = strlen($rsa->modulus->toBytes());
        $rsa->exponent = new Math_BigInteger(base64_url_decode($exp), 256);

        if ($type == 'private') {
            $this->privateKey = $rsa;
        } else {
            $this->publicKey = $rsa;
        }
    }
    
    public function getName()
    {
        return $this->alg;
    }

    public function getHash()
    {
        switch ($this->alg) {

        case 'RSA-SHA256':
            return 'magicsig_sha256';
        }

    }
    
    public function sign($bytes)
    {
        $sig = $this->privateKey->sign($bytes);
        return base64_url_encode($sig);
    }

    public function verify($signed_bytes, $signature)
    {
        $signature = base64_url_decode($signature);
        return $this->publicKey->verify($signed_bytes, $signature);
    }
        
}

function base64_url_encode($input)
{
    return strtr(base64_encode($input), '+/', '-_');
}

function base64_url_decode($input)
{
    return base64_decode(strtr($input, '-_', '+/'));
}
