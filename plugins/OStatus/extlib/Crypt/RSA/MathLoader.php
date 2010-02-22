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
 * @copyright Alexander Valyalkin 2005
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   CVS: $Id: MathLoader.php,v 1.5 2009/01/05 08:30:29 clockwerx Exp $
 * @link      http://pear.php.net/package/Crypt_RSA
 */

/**
 * RSA error handling facilities
 */
require_once 'Crypt/RSA/ErrorHandler.php';

/**
 * Crypt_RSA_MathLoader class.
 *
 * Provides static function:
 *  - loadWrapper($wrapper_name) - loads RSA math wrapper with name $wrapper_name
 *                                 or most suitable wrapper if $wrapper_name == 'default'
 *
 * Example usage:
 *    // load BigInt wrapper
 *    $big_int_wrapper = Crypt_RSA_MathLoader::loadWrapper('BigInt');
 * 
 *    // load BCMath wrapper
 *    $bcmath_wrapper = Crypt_RSA_MathLoader::loadWrapper('BCMath');
 * 
 *    // load the most suitable wrapper
 *    $bcmath_wrapper = Crypt_RSA_MathLoader::loadWrapper();
 * 
 * @category  Encryption
 * @package   Crypt_RSA
 * @author    Alexander Valyalkin <valyala@gmail.com>
 * @copyright Alexander Valyalkin 2005
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/Crypt_RSA
 * @access    public
 */
class Crypt_RSA_MathLoader
{
    /**
     * Loads RSA math wrapper with name $wrapper_name.
     * Implemented wrappers can be found at Crypt/RSA/Math folder.
     * Read docs/Crypt_RSA/docs/math_wrappers.txt for details
     *
     * This is a static function:
     *    // load BigInt wrapper
     *    $big_int_wrapper = &Crypt_RSA_MathLoader::loadWrapper('BigInt');
     *
     *    // load BCMath wrapper
     *    $bcmath_wrapper = &Crypt_RSA_MathLoader::loadWrapper('BCMath');
     *
     * @param string $wrapper_name Name of wrapper
     *
     * @return object
     *         Reference to object of wrapper with name $wrapper_name on success
     *         or PEAR_Error object on error
     *
     * @access public
     */
    function loadWrapper($wrapper_name = 'default')
    {
        static $math_objects = array();
        // ordered by performance. GMP is the fastest math library, BCMath - the slowest.
        static $math_wrappers = array('GMP', 'BigInt', 'BCMath',);

        if (isset($math_objects[$wrapper_name])) {
            /*
                wrapper with name $wrapper_name is already loaded and created.
                Return reference to existing copy of wrapper
            */
            return $math_objects[$wrapper_name];
        }

        $err_handler = new Crypt_RSA_ErrorHandler();

        if ($wrapper_name === 'default') {
            // try to load the most suitable wrapper
            $n = sizeof($math_wrappers);
            for ($i = 0; $i < $n; $i++) {
                $obj = Crypt_RSA_MathLoader::loadWrapper($math_wrappers[$i]);
                if (!$err_handler->isError($obj)) {
                    // wrapper for $math_wrappers[$i] successfully loaded
                    // register it as default wrapper and return reference to it
                    return $math_objects['default'] = $obj;
                }
            }
            // can't load any wrapper
            $err_handler->pushError("can't load any wrapper for existing math libraries", CRYPT_RSA_ERROR_NO_WRAPPERS);
            return $err_handler->getLastError();
        }

        $class_name = 'Crypt_RSA_Math_' . $wrapper_name;
        $class_filename = dirname(__FILE__) . '/Math/' . $wrapper_name . '.php';

        if (!is_file($class_filename)) {
            $err_handler->pushError("can't find file [{$class_filename}] for RSA math wrapper [{$wrapper_name}]", CRYPT_RSA_ERROR_NO_FILE);
            return $err_handler->getLastError();
        }

        include_once $class_filename;
        if (!class_exists($class_name)) {
            $err_handler->pushError("can't find class [{$class_name}] in file [{$class_filename}]", CRYPT_RSA_ERROR_NO_CLASS);
            return $err_handler->getLastError();
        }

        // create and return wrapper object on success or PEAR_Error object on error
        $obj = new $class_name;
        if ($obj->errstr) {
            // cannot load required extension for math wrapper
            $err_handler->pushError($obj->errstr, CRYPT_RSA_ERROR_NO_EXT);
            return $err_handler->getLastError();
        }
        return $math_objects[$wrapper_name] = $obj;
    }
}

?>
