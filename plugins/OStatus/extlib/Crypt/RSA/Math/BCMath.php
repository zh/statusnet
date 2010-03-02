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
 * @copyright  2006 Alexander Valyalkin
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.2.0b
 * @link       http://pear.php.net/package/Crypt_RSA
 */

/**
 * Crypt_RSA_Math_BCMath class.
 *
 * Provides set of math functions, which are used by Crypt_RSA package
 * This class is a wrapper for PHP BCMath extension.
 * See http://php.net/manual/en/ref.bc.php for details.
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
class Crypt_RSA_Math_BCMath
{
    /**
     * error description
     *
     * @var string
     * @access public
     */
    var $errstr = '';

    /**
     * Performs Miller-Rabin primality test for number $num 
     * with base $base. Returns true, if $num is strong pseudoprime
     * by base $base. Else returns false.
     *
     * @param string $num
     * @param string $base
     * @return bool
     * @access private
     */
    function _millerTest($num, $base)
    {
        if (!bccomp($num, '1')) {
            // 1 is not prime ;)
            return false;
        }
        $tmp = bcsub($num, '1');

        $zero_bits = 0;
        while (!bccomp(bcmod($tmp, '2'), '0')) {
            $zero_bits++;
            $tmp = bcdiv($tmp, '2');
        }

        $tmp = $this->powmod($base, $tmp, $num);
        if (!bccomp($tmp, '1')) {
            // $num is probably prime
            return true;
        }

        while ($zero_bits--) {
            if (!bccomp(bcadd($tmp, '1'), $num)) {
                // $num is probably prime
                return true;
            }
            $tmp = $this->powmod($tmp, '2', $num);
        }
        // $num is composite
        return false;
    }

    /**
     * Crypt_RSA_Math_BCMath constructor.
     * Checks an existance of PHP BCMath extension.
     * On failure saves error description in $this->errstr
     *
     * @access public
     */
    function Crypt_RSA_Math_BCMath()
    {
        if (!extension_loaded('bcmath')) {
            if (!@dl('bcmath.' . PHP_SHLIB_SUFFIX) && !@dl('php_bcmath.' . PHP_SHLIB_SUFFIX)) {
                // cannot load BCMath extension. Set error string
                $this->errstr = 'Crypt_RSA package requires the BCMath extension. See http://php.net/manual/en/ref.bc.php for details';
                return;
            }
        }
    }

    /**
     * Transforms binary representation of large integer into its native form.
     * 
     * Example of transformation:
     *    $str = "\x12\x34\x56\x78\x90";
     *    $num = 0x9078563412;
     *
     * @param string $str
     * @return string
     * @access public
     */
    function bin2int($str)
    {
        $result = '0';
        $n = strlen($str);
        do {
            $result = bcadd(bcmul($result, '256'), ord($str{--$n}));
        } while ($n > 0);
        return $result;
    }

    /**
     * Transforms large integer into binary representation.
     * 
     * Example of transformation:
     *    $num = 0x9078563412;
     *    $str = "\x12\x34\x56\x78\x90";
     *
     * @param string $num
     * @return string
     * @access public
     */
    function int2bin($num)
    {
        $result = '';
        do {
            $result .= chr(bcmod($num, '256'));
            $num = bcdiv($num, '256');
        } while (bccomp($num, '0'));
        return $result;
    }

    /**
     * Calculates pow($num, $pow) (mod $mod)
     *
     * @param string $num
     * @param string $pow
     * @param string $mod
     * @return string
     * @access public
     */
    function powmod($num, $pow, $mod)
    {
        if (function_exists('bcpowmod')) {
            // bcpowmod is only available under PHP5
            return bcpowmod($num, $pow, $mod);
        }

        // emulate bcpowmod
        $result = '1';
        do {
            if (!bccomp(bcmod($pow, '2'), '1')) {
                $result = bcmod(bcmul($result, $num), $mod);
            }
            $num = bcmod(bcpow($num, '2'), $mod);
            $pow = bcdiv($pow, '2');
        } while (bccomp($pow, '0'));
        return $result;
    }

    /**
     * Calculates $num1 * $num2
     *
     * @param string $num1
     * @param string $num2
     * @return string
     * @access public
     */
    function mul($num1, $num2)
    {
        return bcmul($num1, $num2);
    }

    /**
     * Calculates $num1 % $num2
     *
     * @param string $num1
     * @param string $num2
     * @return string
     * @access public
     */
    function mod($num1, $num2)
    {
        return bcmod($num1, $num2);
    }

    /**
     * Compares abs($num1) to abs($num2).
     * Returns:
     *   -1, if abs($num1) < abs($num2)
     *   0, if abs($num1) == abs($num2)
     *   1, if abs($num1) > abs($num2)
     *
     * @param string $num1
     * @param string $num2
     * @return int
     * @access public
     */
    function cmpAbs($num1, $num2)
    {
        return bccomp($num1, $num2);
    }

    /**
     * Tests $num on primality. Returns true, if $num is strong pseudoprime.
     * Else returns false.
     *
     * @param string $num
     * @return bool
     * @access private
     */
    function isPrime($num)
    {
        static $primes = null;
        static $primes_cnt = 0;
        if (is_null($primes)) {
            // generate all primes up to 10000
            $primes = array();
            for ($i = 0; $i < 10000; $i++) {
                $primes[] = $i;
            }
            $primes[0] = $primes[1] = 0;
            for ($i = 2; $i < 100; $i++) {
                while (!$primes[$i]) {
                    $i++;
                }
                $j = $i;
                for ($j += $i; $j < 10000; $j += $i) {
                    $primes[$j] = 0;
                }
            }
            $j = 0;
            for ($i = 0; $i < 10000; $i++) {
                if ($primes[$i]) {
                    $primes[$j++] = $primes[$i];
                }
            }
            $primes_cnt = $j;
        }

        // try to divide number by small primes
        for ($i = 0; $i < $primes_cnt; $i++) {
            if (bccomp($num, $primes[$i]) <= 0) {
                // number is prime
                return true;
            }
            if (!bccomp(bcmod($num, $primes[$i]), '0')) {
                // number divides by $primes[$i]
                return false;
            }
        }

        /*
            try Miller-Rabin's probable-primality test for first
            7 primes as bases
        */
        for ($i = 0; $i < 7; $i++) {
            if (!$this->_millerTest($num, $primes[$i])) {
                // $num is composite
                return false;
            }
        }
        // $num is strong pseudoprime
        return true;
    }

    /**
     * Generates prime number with length $bits_cnt
     * using $random_generator as random generator function.
     *
     * @param int $bits_cnt
     * @param string $rnd_generator
     * @access public
     */
    function getPrime($bits_cnt, $random_generator)
    {
        $bytes_n = intval($bits_cnt / 8);
        $bits_n = $bits_cnt % 8;
        do {
            $str = '';
            for ($i = 0; $i < $bytes_n; $i++) {
                $str .= chr(call_user_func($random_generator) & 0xff);
            }
            $n = call_user_func($random_generator) & 0xff;
            $n |= 0x80;
            $n >>= 8 - $bits_n;
            $str .= chr($n);
            $num = $this->bin2int($str);

            // search for the next closest prime number after [$num]
            if (!bccomp(bcmod($num, '2'), '0')) {
                $num = bcadd($num, '1');
            }
            while (!$this->isPrime($num)) {
                $num = bcadd($num, '2');
            }
        } while ($this->bitLen($num) != $bits_cnt);
        return $num;
    }

    /**
     * Calculates $num - 1
     *
     * @param string $num
     * @return string
     * @access public
     */
    function dec($num)
    {
        return bcsub($num, '1');
    }

    /**
     * Returns true, if $num is equal to one. Else returns false
     *
     * @param string $num
     * @return bool
     * @access public
     */
    function isOne($num)
    {
        return !bccomp($num, '1');
    }

    /**
     * Finds greatest common divider (GCD) of $num1 and $num2
     *
     * @param string $num1
     * @param string $num2
     * @return string
     * @access public
     */
    function GCD($num1, $num2)
    {
        do {
            $tmp = bcmod($num1, $num2);
            $num1 = $num2;
            $num2 = $tmp;
        } while (bccomp($num2, '0'));
        return $num1;
    }

    /**
     * Finds inverse number $inv for $num by modulus $mod, such as:
     *     $inv * $num = 1 (mod $mod)
     *
     * @param string $num
     * @param string $mod
     * @return string
     * @access public
     */
    function invmod($num, $mod)
    {
        $x = '1';
        $y = '0';
        $num1 = $mod;
        do {
            $tmp = bcmod($num, $num1);
            $q = bcdiv($num, $num1);
            $num = $num1;
            $num1 = $tmp;

            $tmp = bcsub($x, bcmul($y, $q));
            $x = $y;
            $y = $tmp;
        } while (bccomp($num1, '0'));
        if (bccomp($x, '0') < 0) {
            $x = bcadd($x, $mod);
        }
        return $x;
    }

    /**
     * Returns bit length of number $num
     *
     * @param string $num
     * @return int
     * @access public
     */
    function bitLen($num)
    {
        $tmp = $this->int2bin($num);
        $bit_len = strlen($tmp) * 8;
        $tmp = ord($tmp{strlen($tmp) - 1});
        if (!$tmp) {
            $bit_len -= 8;
        }
        else {
            while (!($tmp & 0x80)) {
                $bit_len--;
                $tmp <<= 1;
            }
        }
        return $bit_len;
    }

    /**
     * Calculates bitwise or of $num1 and $num2,
     * starting from bit $start_pos for number $num1
     *
     * @param string $num1
     * @param string $num2
     * @param int $start_pos
     * @return string
     * @access public
     */
    function bitOr($num1, $num2, $start_pos)
    {
        $start_byte = intval($start_pos / 8);
        $start_bit = $start_pos % 8;
        $tmp1 = $this->int2bin($num1);

        $num2 = bcmul($num2, 1 << $start_bit);
        $tmp2 = $this->int2bin($num2);
        if ($start_byte < strlen($tmp1)) {
            $tmp2 |= substr($tmp1, $start_byte);
            $tmp1 = substr($tmp1, 0, $start_byte) . $tmp2;
        }
        else {
            $tmp1 = str_pad($tmp1, $start_byte, "\0") . $tmp2;
        }
        return $this->bin2int($tmp1);
    }

    /**
     * Returns part of number $num, starting at bit
     * position $start with length $length
     *
     * @param string $num
     * @param int start
     * @param int length
     * @return string
     * @access public
     */
    function subint($num, $start, $length)
    {
        $start_byte = intval($start / 8);
        $start_bit = $start % 8;
        $byte_length = intval($length / 8);
        $bit_length = $length % 8;
        if ($bit_length) {
            $byte_length++;
        }
        $num = bcdiv($num, 1 << $start_bit);
        $tmp = substr($this->int2bin($num), $start_byte, $byte_length);
        $tmp = str_pad($tmp, $byte_length, "\0");
        $tmp = substr_replace($tmp, $tmp{$byte_length - 1} & chr(0xff >> (8 - $bit_length)), $byte_length - 1, 1);
        return $this->bin2int($tmp);
    }

    /**
     * Returns name of current wrapper
     *
     * @return string name of current wrapper
     * @access public
     */
    function getWrapperName()
    {
        return 'BCMath';
    }
}

?>