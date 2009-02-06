<?php

/**
 * Base class for oEmbed objects
 *
 * PHP version 5.1.0+
 *
 * Copyright (c) 2008, Digg.com, Inc.
 * 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions are met:
 *
 *  - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *  - Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *  - Neither the name of Digg.com, Inc. nor the names of its contributors 
 *    may be used to endorse or promote products derived from this software 
 *    without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" 
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE 
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE 
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR 
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF 
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS 
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN 
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category  Services
 * @package   Services_oEmbed
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2008 Digg.com, Inc.
 * @license   http://tinyurl.com/42zef New BSD License
 * @version   SVN: @version@
 * @link      http://code.google.com/p/digg
 * @link      http://oembed.com
 */

/**
 * Base class for oEmbed objects
 *
 * @category  Services
 * @package   Services_oEmbed
 * @author    Joe Stump <joe@joestump.net> 
 * @copyright 2008 Digg.com, Inc.
 * @license   http://tinyurl.com/42zef New BSD License
 * @version   Release: @version@
 * @link      http://code.google.com/p/digg
 * @link      http://oembed.com
 */
abstract class Services_oEmbed_Object_Common
{
    /**
     * Raw object returned from API
     *
     * @var object $object The raw object from the API
     */
    protected $object = null;

    /**
     * Required fields per the specification
     *
     * @var array $required Array of required fields
     * @link http://oembed.com
     */
    protected $required = array();

    /**
     * Constructor
     *
     * @param object $object Raw object returned from the API
     *
     * @throws {@link Services_oEmbed_Object_Exception} on missing fields
     * @return void
     */
    public function __construct($object)
    {
        $this->object = $object;

        $this->required[] = 'version';
        foreach ($this->required as $field) {
            if (!isset($this->$field)) {
                throw new Services_oEmbed_Object_Exception(
                    'Object is missing required ' . $field . ' attribute'
                );
            }
        }
    }

    /**
     * Get object variable
     *
     * @param string $var Variable to get
     *
     * @see Services_oEmbed_Object_Common::$object
     * @return mixed Attribute's value or null if it's not set/exists
     */
    public function __get($var)
    {
        if (property_exists($this->object, $var)) {
            return $this->object->$var;
        }

        return null;
    }

    /**
     * Is variable set?
     *
     * @param string $var Variable name to check
     * 
     * @return boolean True if set, false if not
     * @see Services_oEmbed_Object_Common::$object
     */
    public function __isset($var)
    {
        if (property_exists($this->object, $var)) {
            return (isset($this->object->$var));
        }

        return false;
    }

    /**
     * Require a sane __toString for all objects
     *
     * @return string
     */
    abstract public function __toString();
}

?>
