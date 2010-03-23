<?php

require_once 'Math/BigInteger.php';

/**
 * Crypt_RSA stores a Math_BigInteger with value 0, which triggers a bug
 * in Math_BigInteger's wakeup function which spews notices to log or output.
 * This wrapper replaces it with a version that survives serialization.
 */
class SafeMath_BigInteger extends Math_BigInteger
{
    function __wakeup()
    {
        if ($this->hex == '') {
            $this->hex = '0';
        }
        parent::__wakeup();
    }
}

