<?php

require_once 'Crypt/RSA.php';

/**
 * Crypt_RSA stores a Math_BigInteger with value 0, which triggers a bug
 * in Math_BigInteger's wakeup function which spews notices to log or output.
 * This wrapper replaces it with a version that survives serialization.
 */
class SafeCrypt_RSA extends Crypt_RSA
{
    function __construct()
    {
        parent::__construct();
        $this->zero = new SafeMath_BigInteger();
    }
}

