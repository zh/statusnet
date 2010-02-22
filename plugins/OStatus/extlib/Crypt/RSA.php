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
 * @category   Encryption
 * @package    Crypt_RSA
 * @author     Alexander Valyalkin <valyala@gmail.com>
 * @copyright  2005, 2006 Alexander Valyalkin
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.2.0b
 * @link       http://pear.php.net/package/Crypt_RSA
 */

/**
 * RSA error handling facilities
 */
require_once 'Crypt/RSA/ErrorHandler.php';

/**
 * loader for math wrappers
 */
require_once 'Crypt/RSA/MathLoader.php';

/**
 * helper class for mange single key
 */
require_once 'Crypt/RSA/Key.php';

/**
 * helper class for manage key pair
 */
require_once 'Crypt/RSA/KeyPair.php';

/**
 * Crypt_RSA class, derived from Crypt_RSA_ErrorHandler
 *
 * Provides the following functions:
 *  - setParams($params) - sets parameters of current object
 *  - encrypt($plain_data, $key = null) - encrypts data
 *  - decrypt($enc_data, $key = null) - decrypts data
 *  - createSign($doc, $private_key = null) - signs document by private key
 *  - validateSign($doc, $signature, $public_key = null) - validates signature of document
 *
 * Example usage:
 *     // creating an error handler
 *     $error_handler = create_function('$obj', 'echo "error: ", $obj->getMessage(), "\n"');
 *
 *     // 1024-bit key pair generation
 *     $key_pair = new Crypt_RSA_KeyPair(1024);
 *
 *     // check consistence of Crypt_RSA_KeyPair object
 *     $error_handler($key_pair);
 *
 *     // creating Crypt_RSA object
 *     $rsa_obj = new Crypt_RSA;
 *
 *     // check consistence of Crypt_RSA object
 *     $error_handler($rsa_obj);
 *
 *     // set error handler on Crypt_RSA object ( see Crypt/RSA/ErrorHandler.php for details )
 *     $rsa_obj->setErrorHandler($error_handler);
 *
 *     // encryption (usually using public key)
 *     $enc_data = $rsa_obj->encrypt($plain_data, $key_pair->getPublicKey());
 *
 *     // decryption (usually using private key)
 *     $plain_data = $rsa_obj->decrypt($enc_data, $key_pair->getPrivateKey());
 *
 *     // signing
 *     $signature = $rsa_obj->createSign($document, $key_pair->getPrivateKey());
 *
 *     // signature checking
 *     $is_valid = $rsa_obj->validateSign($document, $signature, $key_pair->getPublicKey());
 *
 *     // signing many documents by one private key
 *     $rsa_obj = new Crypt_RSA(array('private_key' => $key_pair->getPrivateKey()));
 *     // check consistence of Crypt_RSA object
 *     $error_handler($rsa_obj);
 *     // set error handler ( see Crypt/RSA/ErrorHandler.php for details )
 *     $rsa_obj->setErrorHandler($error_handler);
 *     // sign many documents
 *     $sign_1 = $rsa_obj->sign($doc_1);
 *     $sign_2 = $rsa_obj->sign($doc_2);
 *     //...
 *     $sign_n = $rsa_obj->sign($doc_n);
 *
 *     // changing default hash function, which is used for sign
 *     // creating/validation
 *     $rsa_obj->setParams(array('hash_func' => 'md5'));
 *
 *     // using factory() method instead of constructor (it returns PEAR_Error object on failure)
 *     $rsa_obj = &Crypt_RSA::factory();
 *     if (PEAR::isError($rsa_obj)) {
 *         echo "error: ", $rsa_obj->getMessage(), "\n";
 *     }
 *
 * @category   Encryption
 * @package    Crypt_RSA
 * @author     Alexander Valyalkin <valyala@gmail.com>
 * @copyright  2005, 2006 Alexander Valyalkin
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @link       http://pear.php.net/package/Crypt_RSA
 * @version    @package_version@
 * @access     public
 */
class Crypt_RSA extends Crypt_RSA_ErrorHandler
{
    /**
     * Reference to math wrapper, which is used to
     * manipulate large integers in RSA algorithm.
     *
     * @var object of Crypt_RSA_Math_* class
     * @access private
     */
    var $_math_obj;

    /**
     * key for encryption, which is used by encrypt() method
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_enc_key;

    /**
     * key for decryption, which is used by decrypt() method
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_dec_key;

    /**
     * public key, which is used by validateSign() method
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_public_key;

    /**
     * private key, which is used by createSign() method
     *
     * @var object of Crypt_RSA_KEY class
     * @access private
     */
    var $_private_key;

    /**
     * name of hash function, which is used by validateSign()
     * and createSign() methods. Default hash function is SHA-1
     *
     * @var string
     * @access private
     */
    var $_hash_func = 'sha1';

    /**
     * Crypt_RSA constructor.
     *
     * @param array $params
     *        Optional associative array of parameters, such as:
     *        enc_key, dec_key, private_key, public_key, hash_func.
     *        See setParams() method for more detailed description of
     *        these parameters.
     * @param string $wrapper_name
     *        Name of math wrapper, which will be used to
     *        perform different operations with big integers.
     *        See contents of Crypt/RSA/Math folder for examples of wrappers.
     *        Read docs/Crypt_RSA/docs/math_wrappers.txt for details.
     * @param string $error_handler   name of error handler function
     *
     * @access public
     */
    function Crypt_RSA($params = null, $wrapper_name = 'default', $error_handler = '')
    {
        // set error handler
        $this->setErrorHandler($error_handler);
        // try to load math wrapper
        $obj = &Crypt_RSA_MathLoader::loadWrapper($wrapper_name);
        if ($this->isError($obj)) {
            // error during loading of math wrapper
            // Crypt_RSA object is partially constructed.
            $this->pushError($obj);
            return;
        }
        $this->_math_obj = &$obj;

        if (!is_null($params)) {
            if (!$this->setParams($params)) {
                // error in Crypt_RSA::setParams() function
                return;
            }
        }
    }

    /**
     * Crypt_RSA factory.
     *
     * @param array $params
     *        Optional associative array of parameters, such as:
     *        enc_key, dec_key, private_key, public_key, hash_func.
     *        See setParams() method for more detailed description of
     *        these parameters.
     * @param string $wrapper_name
     *        Name of math wrapper, which will be used to
     *        perform different operations with big integers.
     *        See contents of Crypt/RSA/Math folder for examples of wrappers.
     *        Read docs/Crypt_RSA/docs/math_wrappers.txt for details.
     * @param string $error_handler   name of error handler function
     *
     * @return object  new Crypt_RSA object on success or PEAR_Error object on failure
     * @access public
     */
    function &factory($params = null, $wrapper_name = 'default', $error_handler = '')
    {
        $obj = &new Crypt_RSA($params, $wrapper_name, $error_handler);
        if ($obj->isError()) {
            // error during creating a new object. Retrurn PEAR_Error object
            return $obj->getLastError();
        }
        // object created successfully. Return it
        return $obj;
    }

    /**
     * Accepts any combination of available parameters as associative array:
     *     enc_key - encryption key for encrypt() method
     *     dec_key - decryption key for decrypt() method
     *     public_key - key for validateSign() method
     *     private_key - key for createSign() method
     *     hash_func - name of hash function, which will be used to create and validate sign
     *
     * @param array $params
     *        associative array of permitted parameters (see above)
     *
     * @return bool   true on success or false on error
     * @access public
     */
    function setParams($params)
    {
        if (!is_array($params)) {
            $this->pushError('parameters must be passed to function as associative array', CRYPT_RSA_ERROR_WRONG_PARAMS);
            return false;
        }

        if (isset($params['enc_key'])) {
            if (Crypt_RSA_Key::isValid($params['enc_key'])) {
                $this->_enc_key = $params['enc_key'];
            }
            else {
                $this->pushError('wrong encryption key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
                return false;
            }
        }
        if (isset($params['dec_key'])) {
            if (Crypt_RSA_Key::isValid($params['dec_key'])) {
                $this->_dec_key = $params['dec_key'];
            }
            else {
                $this->pushError('wrong decryption key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
                return false;
            }
        }
        if (isset($params['private_key'])) {
            if (Crypt_RSA_Key::isValid($params['private_key'])) {
                if ($params['private_key']->getKeyType() != 'private') {
                    $this->pushError('private key must have "private" attribute', CRYPT_RSA_ERROR_WRONG_KEY_TYPE);
                    return false;
                }
                $this->_private_key = $params['private_key'];
            }
            else {
                $this->pushError('wrong private key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
                return false;
            }
        }
        if (isset($params['public_key'])) {
            if (Crypt_RSA_Key::isValid($params['public_key'])) {
                if ($params['public_key']->getKeyType() != 'public') {
                    $this->pushError('public key must have "public" attribute', CRYPT_RSA_ERROR_WRONG_KEY_TYPE);
                    return false;
                }
                $this->_public_key = $params['public_key'];
            }
            else {
                $this->pushError('wrong public key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
                return false;
            }
        }
        if (isset($params['hash_func'])) {
            if (!function_exists($params['hash_func'])) {
                $this->pushError('cannot find hash function with name [' . $params['hash_func'] . ']', CRYPT_RSA_ERROR_WRONG_HASH_FUNC);
                return false;
            }
            $this->_hash_func = $params['hash_func'];
        }
        return true; // all ok
    }

    /**
     * Ecnrypts $plain_data by the key $this->_enc_key or $key.
     *
     * @param string $plain_data  data, which must be encrypted
     * @param object $key         encryption key (object of Crypt_RSA_Key class)
     * @return mixed
     *         encrypted data as string on success or false on error
     *
     * @access public
     */
    function encrypt($plain_data, $key = null)
    {
        $enc_data = $this->encryptBinary($plain_data, $key);
        if ($enc_data !== false) {
            return base64_encode($enc_data);
        }
        // error during encripting data
        return false;
    }

    /**
     * Ecnrypts $plain_data by the key $this->_enc_key or $key.
     *
     * @param string $plain_data  data, which must be encrypted
     * @param object $key         encryption key (object of Crypt_RSA_Key class)
     * @return mixed
     *         encrypted data as binary string on success or false on error
     *
     * @access public
     */
    function encryptBinary($plain_data, $key = null)
    {
        if (is_null($key)) {
            // use current encryption key
            $key = $this->_enc_key;
        }
        else if (!Crypt_RSA_Key::isValid($key)) {
            $this->pushError('invalid encryption key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
            return false;
        }

        // append tail \x01 to plain data. It needs for correctly decrypting of data
        $plain_data .= "\x01";

        $plain_data = $this->_math_obj->bin2int($plain_data);
        $exp = $this->_math_obj->bin2int($key->getExponent());
        $modulus = $this->_math_obj->bin2int($key->getModulus());

        // divide plain data into chunks
        $data_len = $this->_math_obj->bitLen($plain_data);
        $chunk_len = $key->getKeyLength() - 1;
        $block_len = (int) ceil($chunk_len / 8);
        $curr_pos = 0;
        $enc_data = '';
        while ($curr_pos < $data_len) {
            $tmp = $this->_math_obj->subint($plain_data, $curr_pos, $chunk_len);
            $enc_data .= str_pad(
                $this->_math_obj->int2bin($this->_math_obj->powmod($tmp, $exp, $modulus)),
                $block_len,
                "\0"
            );
            $curr_pos += $chunk_len;
        }
        return $enc_data;
    }

    /**
     * Decrypts $enc_data by the key $this->_dec_key or $key.
     *
     * @param string $enc_data  encrypted data as string
     * @param object $key       decryption key (object of RSA_Crypt_Key class)
     * @return mixed
     *         decrypted data as string on success or false on error
     *
     * @access public
     */
    function decrypt($enc_data, $key = null)
    {
        $enc_data = base64_decode($enc_data);
        return $this->decryptBinary($enc_data, $key);
    }

    /**
     * Decrypts $enc_data by the key $this->_dec_key or $key.
     *
     * @param string $enc_data  encrypted data as binary string
     * @param object $key       decryption key (object of RSA_Crypt_Key class)
     * @return mixed
     *         decrypted data as string on success or false on error
     *
     * @access public
     */
    function decryptBinary($enc_data, $key = null)
    {
        if (is_null($key)) {
            // use current decryption key
            $key = $this->_dec_key;
        }
        else if (!Crypt_RSA_Key::isValid($key)) {
            $this->pushError('invalid decryption key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
            return false;
        }

        $exp = $this->_math_obj->bin2int($key->getExponent());
        $modulus = $this->_math_obj->bin2int($key->getModulus());

        $data_len = strlen($enc_data);
        $chunk_len = $key->getKeyLength() - 1;
        $block_len = (int) ceil($chunk_len / 8);
        $curr_pos = 0;
        $bit_pos = 0;
        $plain_data = $this->_math_obj->bin2int("\0");
        while ($curr_pos < $data_len) {
            $tmp = $this->_math_obj->bin2int(substr($enc_data, $curr_pos, $block_len));
            $tmp = $this->_math_obj->powmod($tmp, $exp, $modulus);
            $plain_data = $this->_math_obj->bitOr($plain_data, $tmp, $bit_pos);
            $bit_pos += $chunk_len;
            $curr_pos += $block_len;
        }
        $result = $this->_math_obj->int2bin($plain_data);

        // delete tail, containing of \x01
        $tail = ord($result{strlen($result) - 1});
        if ($tail != 1) {
            $this->pushError("Error tail of decrypted text = {$tail}. Expected 1", CRYPT_RSA_ERROR_WRONG_TAIL);
            return false;
        }
        return substr($result, 0, -1);
    }

    /**
     * Creates sign for document $document, using $this->_private_key or $private_key
     * as private key and $this->_hash_func or $hash_func as hash function.
     *
     * @param string $document     document, which must be signed
     * @param object $private_key  private key (object of Crypt_RSA_Key type)
     * @param string $hash_func    name of hash function, which will be used during signing
     * @return mixed
     *         signature of $document as string on success or false on error
     *
     * @access public
     */
    function createSign($document, $private_key = null, $hash_func = null)
    {
        // check private key
        if (is_null($private_key)) {
            $private_key = $this->_private_key;
        }
        else if (!Crypt_RSA_Key::isValid($private_key)) {
            $this->pushError('invalid private key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
            return false;
        }
        if ($private_key->getKeyType() != 'private') {
            $this->pushError('signing key must be private', CRYPT_RSA_ERROR_NEED_PRV_KEY);
            return false;
        }

        // check hash_func
        if (is_null($hash_func)) {
            $hash_func = $this->_hash_func;
        }
        if (!function_exists($hash_func)) {
            $this->pushError("cannot find hash function with name [$hash_func]", CRYPT_RSA_ERROR_WRONG_HASH_FUNC);
            return false;
        }

        return $this->encrypt($hash_func($document), $private_key);
    }

    /**
     * Validates $signature for document $document with public key $this->_public_key
     * or $public_key and hash function $this->_hash_func or $hash_func.
     *
     * @param string $document    document, signature of which must be validated
     * @param string $signature   signature, which must be validated
     * @param object $public_key  public key (object of Crypt_RSA_Key class)
     * @param string $hash_func   hash function, which will be used during validating signature
     * @return mixed
     *         true, if signature of document is valid
     *         false, if signature of document is invalid
     *         null on error
     *
     * @access public
     */
    function validateSign($document, $signature, $public_key = null, $hash_func = null)
    {
        // check public key
        if (is_null($public_key)) {
            $public_key = $this->_public_key;
        }
        else if (!Crypt_RSA_Key::isValid($public_key)) {
            $this->pushError('invalid public key. It must be an object of Crypt_RSA_Key class', CRYPT_RSA_ERROR_WRONG_KEY);
            return null;
        }
        if ($public_key->getKeyType() != 'public') {
            $this->pushError('validating key must be public', CRYPT_RSA_ERROR_NEED_PUB_KEY);
            return null;
        }

        // check hash_func
        if (is_null($hash_func)) {
            $hash_func = $this->_hash_func;
        }
        if (!function_exists($hash_func)) {
            $this->pushError("cannot find hash function with name [$hash_func]", CRYPT_RSA_ERROR_WRONG_HASH_FUNC);
            return null;
        }

        return $hash_func($document) == $this->decrypt($signature, $public_key);
    }
}

?>