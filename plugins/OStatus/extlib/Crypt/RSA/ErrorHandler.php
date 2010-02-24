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
 * @version   CVS: $Id: ErrorHandler.php,v 1.4 2009/01/05 08:30:29 clockwerx Exp $
 * @link      http://pear.php.net/package/Crypt_RSA
 */

/**
 * uses PEAR's error handling
 */
require_once 'PEAR.php';

/**
 * cannot load required extension for math wrapper
 */
define('CRYPT_RSA_ERROR_NO_EXT', 1);

/**
 * cannot load any math wrappers.
 * Possible reasons:
 *  - there is no any wrappers (they must exist in Crypt/RSA/Math folder )
 *  - all available wrappers are incorrect (read docs/Crypt_RSA/docs/math_wrappers.txt )
 *  - cannot load any extension, required by available wrappers
 */
define('CRYPT_RSA_ERROR_NO_WRAPPERS', 2);

/**
 * cannot find file, containing requested math wrapper
 */
define('CRYPT_RSA_ERROR_NO_FILE', 3);

/**
 * cannot find math wrapper class in the math wrapper file
 */
define('CRYPT_RSA_ERROR_NO_CLASS', 4);

/**
 * invalid key type passed to function (it must be 'public' or 'private')
 */
define('CRYPT_RSA_ERROR_WRONG_KEY_TYPE', 5);

/**
 * key modulus must be greater than key exponent
 */
define('CRYPT_RSA_ERROR_EXP_GE_MOD', 6);

/**
 * missing $key_len parameter in Crypt_RSA_KeyPair::generate($key_len) function
 */
define('CRYPT_RSA_ERROR_MISSING_KEY_LEN', 7);

/**
 * wrong key object passed to function (it must be an object of Crypt_RSA_Key class)
 */
define('CRYPT_RSA_ERROR_WRONG_KEY', 8);

/**
 * wrong name of hash function passed to Crypt_RSA::setParams() function
 */
define('CRYPT_RSA_ERROR_WRONG_HASH_FUNC', 9);

/**
 * key, used for signing, must be private
 */
define('CRYPT_RSA_ERROR_NEED_PRV_KEY', 10);

/**
 * key, used for sign validating, must be public
 */
define('CRYPT_RSA_ERROR_NEED_PUB_KEY', 11);

/**
 * parameters must be passed to function as associative array
 */
define('CRYPT_RSA_ERROR_WRONG_PARAMS', 12);

/**
 * error tail of decrypted text. Maybe, wrong decryption key?
 */
define('CRYPT_RSA_ERROR_WRONG_TAIL', 13);

/**
 * Crypt_RSA_ErrorHandler class.
 *
 * This class is used as base for Crypt_RSA, Crypt_RSA_Key
 * and Crypt_RSA_KeyPair classes.
 *
 * It provides following functions:
 *   - isError() - returns true, if list contains errors, else returns false
 *   - getErrorList() - returns error list
 *   - getLastError() - returns last error from error list or false, if list is empty
 *   - pushError($errstr) - pushes $errstr into the error list
 *   - setErrorHandler($new_error_handler) - sets error handler function
 *   - getErrorHandler() - returns name of error handler function
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
class Crypt_RSA_ErrorHandler
{
    /**
     * array of error objects, pushed by $this->pushError()
     *
     * @var array
     * @access private
     */
    var $_errors = array();

    /**
     * name of error handler - function, which calls on $this->pushError() call
     *
     * @var string
     * @access private
     */
    var $_error_handler = '';

    /**
     * Returns true if list of errors is not empty, else returns false
     *
     * @param mixed $err Check if the object is an error
     *
     * @return bool    true, if list of errors is not empty or $err is PEAR_Error object, else false
     * @access public
     */
    function isError($err = null)
    {
        return is_null($err) ? (sizeof($this->_errors) > 0) : PEAR::isError($err);
    }

    /**
     * Returns list of all errors, pushed to error list by $this->pushError()
     *
     * @return array    list of errors (usually it contains objects of PEAR_Error class)
     * @access public
     */
    function getErrorList()
    {
        return $this->_errors;
    }

    /**
     * Returns last error from errors list or false, if list is empty
     *
     * @return mixed
     *         last error from errors list (usually it is PEAR_Error object)
     *         or false, if list is empty.
     *
     * @access public
     */
    function getLastError()
    {
        $len = sizeof($this->_errors);
        return $len ? $this->_errors[$len - 1] : false;
    }

    /**
     * pushes error object $error to the error list
     *
     * @param string $errstr error string
     * @param int    $errno  error number
     *
     * @return bool          true on success, false on error
     * @access public
     */
    function pushError($errstr, $errno = 0)
    {
        $this->_errors[] = PEAR::raiseError($errstr, $errno);

        if ($this->_error_handler != '') {
            // call user defined error handler
            $func = $this->_error_handler;
            $func($this);
        }
        return true;
    }

    /**
     * sets error handler to function with name $func_name.
     * Function $func_name must accept one parameter - current
     * object, which triggered error.
     *
     * @param string $func_name name of error handler function
     *
     * @return bool             true on success, false on error
     * @access public
     */
    function setErrorHandler($func_name = '')
    {
        if ($func_name == '') {
            $this->_error_handler = '';
        }
        if (!function_exists($func_name)) {
            return false;
        }
        $this->_error_handler = $func_name;
        return true;
    }

    /**
     * returns name of current error handler, or null if there is no error handler
     *
     * @return mixed  error handler name as string or null, if there is no error handler
     * @access public
     */
    function getErrorHandler()
    {
        return $this->_error_handler;
    }
}

?>
