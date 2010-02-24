<?php
/**
 * Crypt_RSA allows to do following operations:
 *     - key pair generation
 *     - encryption and decryption
 *     - signing and sign validation
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Encryption
 * @package   Crypt_RSA
 * @author    Alexander Valyalkin <valyala@gmail.com>
 * @copyright 2005 Alexander Valyalkin
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   CVS: $Id: KeyPair.php,v 1.7 2009/01/05 08:30:29 clockwerx Exp $
 * @link      http://pear.php.net/package/Crypt_RSA
 */

/**
 * RSA error handling facilities
 */
require_once 'Crypt/RSA/ErrorHandler.php';

/**
 * loader for RSA math wrappers
 */
require_once 'Crypt/RSA/MathLoader.php';

/**
 * helper class for single key managing
 */
require_once 'Crypt/RSA/Key.php';

/**
 * Crypt_RSA_KeyPair class, derived from Crypt_RSA_ErrorHandler
 *
 * Provides the following functions:
 *  - generate($key) - generates new key pair
 *  - getPublicKey() - returns public key
 *  - getPrivateKey() - returns private key
 *  - getKeyLength() - returns bit key length
 *  - setRandomGenerator($func_name) - sets random generator to $func_name
 *  - fromPEMString($str) - retrieves keypair from PEM-encoded string
 *  - toPEMString() - stores keypair to PEM-encoded string
 *  - isEqual($keypair2) - compares current keypair to $keypair2
 *
 * Example usage:
 *    // create new 1024-bit key pair
 *    $key_pair = new Crypt_RSA_KeyPair(1024);
 *
 *    // error check
 *    if ($key_pair->isError()) {
 *        echo "error while initializing Crypt_RSA_KeyPair object:\n";
 *        $erorr = $key_pair->getLastError();
 *        echo $error->getMessage(), "\n";
 *    }
 *
 *    // get public key
 *    $public_key = $key_pair->getPublicKey();
 * 
 *    // get private key
 *    $private_key = $key_pair->getPrivateKey();
 * 
 *    // generate new 512-bit key pair
 *    $key_pair->generate(512);
 *
 *    // error check
 *    if ($key_pair->isError()) {
 *        echo "error while generating key pair:\n";
 *        $erorr = $key_pair->getLastError();
 *        echo $error->getMessage(), "\n";
 *    }
 *
 *    // get key pair length
 *    $length = $key_pair->getKeyLength();
 *
 *    // set random generator to $func_name, where $func_name
 *    // consists name of random generator function. See comments
 *    // before setRandomGenerator() method for details
 *    $key_pair->setRandomGenerator($func_name);
 *
 *    // error check
 *    if ($key_pair->isError()) {
 *        echo "error while changing random generator:\n";
 *        $erorr = $key_pair->getLastError();
 *        echo $error->getMessage(), "\n";
 *    }
 *
 *    // using factory() method instead of constructor (it returns PEAR_Error object on failure)
 *    $rsa_obj = &Crypt_RSA_KeyPair::factory($key_len);
 *    if (PEAR::isError($rsa_obj)) {
 *        echo "error: ", $rsa_obj->getMessage(), "\n";
 *    }
 *
 *    // read key pair from PEM-encoded string:
 *    $str = "-----BEGIN RSA PRIVATE KEY-----"
 *         . "MCsCAQACBHr5LDkCAwEAAQIEBc6jbQIDAOCfAgMAjCcCAk3pAgJMawIDAL41"
 *         . "-----END RSA PRIVATE KEY-----";
 *    $keypair = Crypt_RSA_KeyPair::fromPEMString($str);
 *
 *    // read key pair from .pem file 'private.pem':
 *    $str = file_get_contents('private.pem');
 *    $keypair = Crypt_RSA_KeyPair::fromPEMString($str);
 *
 *    // generate and write 1024-bit key pair to .pem file 'private_new.pem'
 *    $keypair = new Crypt_RSA_KeyPair(1024);
 *    $str = $keypair->toPEMString();
 *    file_put_contents('private_new.pem', $str);
 *
 *    // compare $keypair1 to $keypair2
 *    if ($keypair1->isEqual($keypair2)) {
 *        echo "keypair1 = keypair2\n";
 *    }
 *    else {
 *        echo "keypair1 != keypair2\n";
 *    }
 *
 * @category  Encryption
 * @package   Crypt_RSA
 * @author    Alexander Valyalkin <valyala@gmail.com>
 * @copyright 2005 Alexander Valyalkin
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Crypt_RSA
 * @access    public
 */
class Crypt_RSA_KeyPair extends Crypt_RSA_ErrorHandler
{
    /**
     * Reference to math wrapper object, which is used to
     * manipulate large integers in RSA algorithm.
     *
     * @var object of Crypt_RSA_Math_* class
     * @access private
     */
    var $_math_obj;

    /**
     * length of each key in the key pair
     *
     * @var int
     * @access private
     */
    var $_key_len;

    /**
     * public key
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_public_key;

    /**
     * private key
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_private_key;

    /**
     * name of function, which is used as random generator
     *
     * @var string
     * @access private
     */
    var $_random_generator;

    /**
     * RSA keypair attributes [version, n, e, d, p, q, dmp1, dmq1, iqmp] as associative array
     *
     * @var array
     * @access private
     */
    var $_attrs;

    /**
     * Returns names of keypair attributes from $this->_attrs array
     *
     * @return array  Array of keypair attributes names
     * @access private
     */
    function _get_attr_names() 
    {
        return array('version', 'n', 'e', 'd', 'p', 'q', 'dmp1', 'dmq1', 'iqmp');
    }

    /**
     * Parses ASN.1 string [$str] starting form position [$pos].
     * Returns tag and string value of parsed object.
     *
     * @param string                 $str
     * @param int                    &$pos
     * @param Crypt_RSA_ErrorHandler &$err_handler
     *
     * @return mixed    Array('tag' => ..., 'str' => ...) on success, false on error
     * @access private
     */
    function _ASN1Parse($str, &$pos, &$err_handler)
    {
        $max_pos = strlen($str);
        if ($max_pos < 2) {
            $err_handler->pushError("ASN.1 string too short");
            return false;
        }

        // get ASN.1 tag value
        $tag = ord($str[$pos++]) & 0x1f;
        if ($tag == 0x1f) {
            $tag = 0;
            do {
                $n = ord($str[$pos++]);
                $tag <<= 7;
                $tag |= $n & 0x7f;
            } while (($n & 0x80) && $pos < $max_pos);
        }
        if ($pos >= $max_pos) {
            $err_handler->pushError("ASN.1 string too short");
            return false;
        }

        // get ASN.1 object length
        $len = ord($str[$pos++]);
        if ($len & 0x80) {
            $n = $len & 0x1f;
            $len = 0;
            while ($n-- && $pos < $max_pos) {
                $len <<= 8;
                $len |= ord($str[$pos++]);
            }
        }
        if ($pos >= $max_pos || $len > $max_pos - $pos) {
            $err_handler->pushError("ASN.1 string too short");
            return false;
        }

        // get string value of ASN.1 object
        $str = substr($str, $pos, $len);

        return array(
            'tag' => $tag,
            'str' => $str,
        );
    }

    /**
     * Parses ASN.1 sting [$str] starting from position [$pos].
     * Returns string representation of number, which can be passed
     * in bin2int() function of math wrapper.
     *
     * @param string                 $str
     * @param int                    &$pos
     * @param Crypt_RSA_ErrorHandler &$err_handler
     *
     * @return mixed   string representation of parsed number on success, false on error
     * @access private
     */
    function _ASN1ParseInt($str, &$pos, &$err_handler)
    {
        $tmp = Crypt_RSA_KeyPair::_ASN1Parse($str, $pos, $err_handler);
        if ($err_handler->isError()) {
            return false;
        }
        if ($tmp['tag'] != 0x02) {
            $errstr = sprintf("wrong ASN tag value: 0x%02x. Expected 0x02 (INTEGER)", $tmp['tag']);
            $err_handler->pushError($errstr);
            return false;
        }
        $pos += strlen($tmp['str']);

        return strrev($tmp['str']);
    }

    /**
     * Constructs ASN.1 string from tag $tag and object $str
     *
     * @param string $str            ASN.1 object string
     * @param int    $tag            ASN.1 tag value
     * @param bool   $is_constructed 
     * @param bool   $is_private 
     *
     * @return ASN.1-encoded string
     * @access private
     */
    function _ASN1Store($str, $tag, $is_constructed = false, $is_private = false)
    {
        $out = '';

        // encode ASN.1 tag value
        $tag_ext = ($is_constructed ? 0x20 : 0) | ($is_private ? 0xc0 : 0);
        if ($tag < 0x1f) {
            $out .= chr($tag | $tag_ext);
        } else {
            $out .= chr($tag_ext | 0x1f);
            $tmp = chr($tag & 0x7f);
            $tag >>= 7;
            while ($tag) {
                $tmp .= chr(($tag & 0x7f) | 0x80);
                $tag >>= 7;
            }
            $out .= strrev($tmp);
        }

        // encode ASN.1 object length
        $len = strlen($str);
        if ($len < 0x7f) {
            $out .= chr($len);
        } else {
            $tmp = '';
            $n = 0;
            while ($len) {
                $tmp .= chr($len & 0xff);
                $len >>= 8;
                $n++;
            }
            $out .= chr($n | 0x80);
            $out .= strrev($tmp);
        }

        return $out . $str;
    }

    /**
     * Constructs ASN.1 string from binary representation of big integer
     *
     * @param string $str binary representation of big integer
     *
     * @return ASN.1-encoded string
     * @access private
     */
    function _ASN1StoreInt($str)
    {
        $str = strrev($str);
        return Crypt_RSA_KeyPair::_ASN1Store($str, 0x02);
    }

    /**
     * Crypt_RSA_KeyPair constructor.
     *
     * Wrapper: name of math wrapper, which will be used to
     *        perform different operations with big integers.
     *        See contents of Crypt/RSA/Math folder for examples of wrappers.
     *        Read docs/Crypt_RSA/docs/math_wrappers.txt for details.
     *
     * @param int      $key_len          bit length of key pair, which will be generated in constructor
     * @param string   $wrapper_name     wrapper name
     * @param string   $error_handler    name of error handler function
     * @param callback $random_generator function which will be used as random generator
     *
     * @access public
     */
    function Crypt_RSA_KeyPair($key_len, $wrapper_name = 'default', $error_handler = '', $random_generator = null)
    {
        // set error handler
        $this->setErrorHandler($error_handler);
        // try to load math wrapper
        $obj = &Crypt_RSA_MathLoader::loadWrapper($wrapper_name);
        if ($this->isError($obj)) {
            // error during loading of math wrapper
            $this->pushError($obj);
            return;
        }
        $this->_math_obj = &$obj;

        // set random generator
        if (!$this->setRandomGenerator($random_generator)) {
            // error in setRandomGenerator() function
            return;
        }

        if (is_array($key_len)) {
            // ugly BC hack - it is possible to pass RSA private key attributes [version, n, e, d, p, q, dmp1, dmq1, iqmp]
            // as associative array instead of key length to Crypt_RSA_KeyPair constructor
            $rsa_attrs = $key_len;

            // convert attributes to big integers
            $attr_names = $this->_get_attr_names();
            foreach ($attr_names as $attr) {
                if (!isset($rsa_attrs[$attr])) {
                    $this->pushError("missing required RSA attribute [$attr]");
                    return;
                }
                ${$attr} = $this->_math_obj->bin2int($rsa_attrs[$attr]);
            }

            // check primality of p and q
            if (!$this->_math_obj->isPrime($p)) {
                $this->pushError("[p] must be prime");
                return;
            }
            if (!$this->_math_obj->isPrime($q)) {
                $this->pushError("[q] must be prime");
                return;
            }

            // check n = p * q
            $n1 = $this->_math_obj->mul($p, $q);
            if ($this->_math_obj->cmpAbs($n, $n1)) {
                $this->pushError("n != p * q");
                return;
            }

            // check e * d = 1 mod (p-1) * (q-1)
            $p1 = $this->_math_obj->dec($p);
            $q1 = $this->_math_obj->dec($q);
            $p1q1 = $this->_math_obj->mul($p1, $q1);
            $ed = $this->_math_obj->mul($e, $d);
            $one = $this->_math_obj->mod($ed, $p1q1);
            if (!$this->_math_obj->isOne($one)) {
                $this->pushError("e * d != 1 mod (p-1)*(q-1)");
                return;
            }

            // check dmp1 = d mod (p-1)
            $dmp = $this->_math_obj->mod($d, $p1);
            if ($this->_math_obj->cmpAbs($dmp, $dmp1)) {
                $this->pushError("dmp1 != d mod (p-1)");
                return;
            }

            // check dmq1 = d mod (q-1)
            $dmq = $this->_math_obj->mod($d, $q1);
            if ($this->_math_obj->cmpAbs($dmq, $dmq1)) {
                $this->pushError("dmq1 != d mod (q-1)");
                return;
            }

            // check iqmp = 1/q mod p
            $q1 = $this->_math_obj->invmod($iqmp, $p);
            if ($this->_math_obj->cmpAbs($q, $q1)) {
                $this->pushError("iqmp != 1/q mod p");
                return;
            }

            // try to create public key object
            $public_key = &new Crypt_RSA_Key($rsa_attrs['n'], $rsa_attrs['e'], 'public', $wrapper_name, $error_handler);
            if ($public_key->isError()) {
                // error during creating public object
                $this->pushError($public_key->getLastError());
                return;
            }

            // try to create private key object
            $private_key = &new Crypt_RSA_Key($rsa_attrs['n'], $rsa_attrs['d'], 'private', $wrapper_name, $error_handler);
            if ($private_key->isError()) {
                // error during creating private key object
                $this->pushError($private_key->getLastError());
                return;
            }

            $this->_public_key = $public_key;
            $this->_private_key = $private_key;
            $this->_key_len = $public_key->getKeyLength();
            $this->_attrs = $rsa_attrs;
        } else {
            // generate key pair
            if (!$this->generate($key_len)) {
                // error during generating key pair
                return;
            }
        }
    }

    /**
     * Crypt_RSA_KeyPair factory.
     *
     * Wrapper - Name of math wrapper, which will be used to
     *        perform different operations with big integers.
     *        See contents of Crypt/RSA/Math folder for examples of wrappers.
     *        Read docs/Crypt_RSA/docs/math_wrappers.txt for details.
     *
     * @param int      $key_len          bit length of key pair, which will be generated in constructor
     * @param string   $wrapper_name     wrapper name
     * @param string   $error_handler    name of error handler function
     * @param callback $random_generator function which will be used as random generator
     *
     * @return object   new Crypt_RSA_KeyPair object on success or PEAR_Error object on failure
     * @access public
     */
    function &factory($key_len, $wrapper_name = 'default', $error_handler = '', $random_generator = null)
    {
        $obj = &new Crypt_RSA_KeyPair($key_len, $wrapper_name, $error_handler, $random_generator);
        if ($obj->isError()) {
            // error during creating a new object. Return PEAR_Error object
            return $obj->getLastError();
        }
        // object created successfully. Return it
        return $obj;
    }

    /**
     * Generates new Crypt_RSA key pair with length $key_len.
     * If $key_len is missed, use an old key length from $this->_key_len
     *
     * @param int $key_len bit length of key pair, which will be generated
     *
     * @return bool         true on success or false on error
     * @access public
     */
    function generate($key_len = null)
    {
        if (is_null($key_len)) {
            // use an old key length
            $key_len = $this->_key_len;
            if (is_null($key_len)) {
                $this->pushError('missing key_len parameter', CRYPT_RSA_ERROR_MISSING_KEY_LEN);
                return false;
            }
        }

        // minimal key length is 8 bit ;)
        if ($key_len < 8) {
            $key_len = 8;
        }
        // store key length in the _key_len property
        $this->_key_len = $key_len;

        // set [e] to 0x10001 (65537)
        $e = $this->_math_obj->bin2int("\x01\x00\x01");

        // generate [p], [q] and [n]
        $p_len = intval(($key_len + 1) / 2);
        $q_len = $key_len - $p_len;
        $p1 = $q1 = 0;
        do {
            // generate prime number [$p] with length [$p_len] with the following condition:
            // GCD($e, $p - 1) = 1
            do {
                $p = $this->_math_obj->getPrime($p_len, $this->_random_generator);
                $p1 = $this->_math_obj->dec($p);
                $tmp = $this->_math_obj->GCD($e, $p1);
            } while (!$this->_math_obj->isOne($tmp));
            // generate prime number [$q] with length [$q_len] with the following conditions:
            // GCD($e, $q - 1) = 1
            // $q != $p
            do {
                $q = $this->_math_obj->getPrime($q_len, $this->_random_generator);
                $q1 = $this->_math_obj->dec($q);
                $tmp = $this->_math_obj->GCD($e, $q1);
            } while (!$this->_math_obj->isOne($tmp) && !$this->_math_obj->cmpAbs($q, $p));
            // if (p < q), then exchange them
            if ($this->_math_obj->cmpAbs($p, $q) < 0) {
                $tmp = $p;
                $p = $q;
                $q = $tmp;
                $tmp = $p1;
                $p1 = $q1;
                $q1 = $tmp;
            }
            // calculate n = p * q
            $n = $this->_math_obj->mul($p, $q);
        } while ($this->_math_obj->bitLen($n) != $key_len);

        // calculate d = 1/e mod (p - 1) * (q - 1)
        $pq = $this->_math_obj->mul($p1, $q1);
        $d = $this->_math_obj->invmod($e, $pq);

        // calculate dmp1 = d mod (p - 1)
        $dmp1 = $this->_math_obj->mod($d, $p1);

        // calculate dmq1 = d mod (q - 1)
        $dmq1 = $this->_math_obj->mod($d, $q1);

        // calculate iqmp = 1/q mod p
        $iqmp = $this->_math_obj->invmod($q, $p);

        // store RSA keypair attributes
        $this->_attrs = array(
            'version' => "\x00",
            'n' => $this->_math_obj->int2bin($n),
            'e' => $this->_math_obj->int2bin($e),
            'd' => $this->_math_obj->int2bin($d),
            'p' => $this->_math_obj->int2bin($p),
            'q' => $this->_math_obj->int2bin($q),
            'dmp1' => $this->_math_obj->int2bin($dmp1),
            'dmq1' => $this->_math_obj->int2bin($dmq1),
            'iqmp' => $this->_math_obj->int2bin($iqmp),
        );

        $n = $this->_attrs['n'];
        $e = $this->_attrs['e'];
        $d = $this->_attrs['d'];

        // try to create public key object
        $obj = &new Crypt_RSA_Key($n, $e, 'public', $this->_math_obj->getWrapperName(), $this->_error_handler);
        if ($obj->isError()) {
            // error during creating public object
            $this->pushError($obj->getLastError());
            return false;
        }
        $this->_public_key = &$obj;

        // try to create private key object
        $obj = &new Crypt_RSA_Key($n, $d, 'private', $this->_math_obj->getWrapperName(), $this->_error_handler);
        if ($obj->isError()) {
            // error during creating private key object
            $this->pushError($obj->getLastError());
            return false;
        }
        $this->_private_key = &$obj;

        return true; // key pair successfully generated
    }

    /**
     * Returns public key from the pair
     *
     * @return object  public key object of class Crypt_RSA_Key
     * @access public
     */
    function getPublicKey()
    {
        return $this->_public_key;
    }

    /**
     * Returns private key from the pair
     *
     * @return object   private key object of class Crypt_RSA_Key
     * @access public
     */
    function getPrivateKey()
    {
        return $this->_private_key;
    }

    /**
     * Sets name of random generator function for key generation.
     * If parameter is skipped, then sets to default random generator.
     *
     * Random generator function must return integer with at least 8 lower
     * significant bits, which will be used as random values.
     *
     * @param string $random_generator name of random generator function
     *
     * @return bool                     true on success or false on error
     * @access public
     */
    function setRandomGenerator($random_generator = null)
    {
        static $default_random_generator = null;

        if (is_string($random_generator)) {
            // set user's random generator
            if (!function_exists($random_generator)) {
                $this->pushError("can't find random generator function with name [{$random_generator}]");
                return false;
            }
            $this->_random_generator = $random_generator;
        } else {
            // set default random generator
            $this->_random_generator = is_null($default_random_generator) ?
                ($default_random_generator = create_function('', '$a=explode(" ",microtime());return(int)($a[0]*1000000);')) :
                $default_random_generator;
        }
        return true;
    }

    /**
     * Returns length of each key in the key pair
     *
     * @return int  bit length of each key in key pair
     * @access public
     */
    function getKeyLength()
    {
        return $this->_key_len;
    }

    /**
     * Retrieves RSA keypair from PEM-encoded string, containing RSA private key.
     * Example of such string:
     * -----BEGIN RSA PRIVATE KEY-----
     * MCsCAQACBHtvbSECAwEAAQIEeYrk3QIDAOF3AgMAjCcCAmdnAgJMawIDALEk
     * -----END RSA PRIVATE KEY-----
     *
     * Wrapper: Name of math wrapper, which will be used to
     * perform different operations with big integers.
     * See contents of Crypt/RSA/Math folder for examples of wrappers.
     * Read docs/Crypt_RSA/docs/math_wrappers.txt for details.
     *
     * @param string $str           PEM-encoded string
     * @param string $wrapper_name  Wrapper name
     * @param string $error_handler name of error handler function
     *
     * @return Crypt_RSA_KeyPair object on success, PEAR_Error object on error
     * @access public
     * @static
     */
    function &fromPEMString($str, $wrapper_name = 'default', $error_handler = '')
    {
        if (isset($this)) {
            if ($wrapper_name == 'default') {
                $wrapper_name = $this->_math_obj->getWrapperName();
            }
            if ($error_handler == '') {
                $error_handler = $this->_error_handler;
            }
        }
        $err_handler = &new Crypt_RSA_ErrorHandler;
        $err_handler->setErrorHandler($error_handler);

        // search for base64-encoded private key
        if (!preg_match('/-----BEGIN RSA PRIVATE KEY-----([^-]+)-----END RSA PRIVATE KEY-----/', $str, $matches)) {
            $err_handler->pushError("can't find RSA private key in the string [{$str}]");
            return $err_handler->getLastError();
        }

        // parse private key. It is ASN.1-encoded
        $str = base64_decode($matches[1]);
        $pos = 0;
        $tmp = Crypt_RSA_KeyPair::_ASN1Parse($str, $pos, $err_handler);
        if ($err_handler->isError()) {
            return $err_handler->getLastError();
        }
        if ($tmp['tag'] != 0x10) {
            $errstr = sprintf("wrong ASN tag value: 0x%02x. Expected 0x10 (SEQUENCE)", $tmp['tag']);
            $err_handler->pushError($errstr);
            return $err_handler->getLastError();
        }

        // parse ASN.1 SEQUENCE for RSA private key
        $attr_names = Crypt_RSA_KeyPair::_get_attr_names();
        $n = sizeof($attr_names);
        $rsa_attrs = array();
        for ($i = 0; $i < $n; $i++) {
            $tmp = Crypt_RSA_KeyPair::_ASN1ParseInt($str, $pos, $err_handler);
            if ($err_handler->isError()) {
                return $err_handler->getLastError();
            }
            $attr = $attr_names[$i];
            $rsa_attrs[$attr] = $tmp;
        }

        // create Crypt_RSA_KeyPair object.
        $keypair = &new Crypt_RSA_KeyPair($rsa_attrs, $wrapper_name, $error_handler);
        if ($keypair->isError()) {
            return $keypair->getLastError();
        }

        return $keypair;
    }

    /**
     * converts keypair to PEM-encoded string, which can be stroed in 
     * .pem compatible files, contianing RSA private key.
     *
     * @return string PEM-encoded keypair on success, false on error
     * @access public
     */
    function toPEMString()
    {
        // store RSA private key attributes into ASN.1 string
        $str = '';
        $attr_names = $this->_get_attr_names();
        $n = sizeof($attr_names);
        $rsa_attrs = $this->_attrs;
        for ($i = 0; $i < $n; $i++) {
            $attr = $attr_names[$i];
            if (!isset($rsa_attrs[$attr])) {
                $this->pushError("Cannot find value for ASN.1 attribute [$attr]");
                return false;
            }
            $tmp = $rsa_attrs[$attr];
            $str .= Crypt_RSA_KeyPair::_ASN1StoreInt($tmp);
        }

        // prepend $str by ASN.1 SEQUENCE (0x10) header
        $str = Crypt_RSA_KeyPair::_ASN1Store($str, 0x10, true);

        // encode and format PEM string
        $str = base64_encode($str);
        $str = chunk_split($str, 64, "\n");
        return "-----BEGIN RSA PRIVATE KEY-----\n$str-----END RSA PRIVATE KEY-----\n";
    }

    /**
     * Compares keypairs in Crypt_RSA_KeyPair objects $this and $key_pair
     *
     * @param Crypt_RSA_KeyPair $key_pair  keypair to compare
     *
     * @return bool  true, if keypair stored in $this equal to keypair stored in $key_pair
     * @access public
     */
    function isEqual($key_pair)
    {
        $attr_names = $this->_get_attr_names();
        foreach ($attr_names as $attr) {
            if ($this->_attrs[$attr] != $key_pair->_attrs[$attr]) {
                return false;
            }
        }
        return true;
    }
}

?>
