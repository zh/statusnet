<?php

/**
 * An interface for oEmbed consumption
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

require_once 'Services/oEmbed/Object/Exception.php';

/**
 * Base class for consuming oEmbed objects
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
abstract class Services_oEmbed_Object 
{
    
    /**
     * Valid oEmbed object types
     *
     * @var array $types Array of valid object types
     * @see Services_oEmbed_Object::factory()
     */
    static protected $types = array(
        'photo' => 'Photo',
        'video' => 'Video',
        'link'  => 'Link',
        'rich'  => 'Rich'
    );

    /**
     * Create an oEmbed object from result
     *
     * @param object $object Raw object returned from API
     *
     * @throws {@link Services_oEmbed_Object_Exception} on object error
     * @return object Instance of object driver
     * @see Services_oEmbed_Object_Link, Services_oEmbed_Object_Photo
     * @see Services_oEmbed_Object_Rich, Services_oEmbed_Object_Video
     */
    static public function factory($object)
    {
        if (!isset($object->type)) {
            throw new Services_oEmbed_Object_Exception(
                'Object has no type'
            );
        }

        $type = (string)$object->type;
        if (!isset(self::$types[$type])) {
            throw new Services_oEmbed_Object_Exception(
                'Object type is unknown or invalid: ' . $type
            );
        }

        $file = 'Services/oEmbed/Object/' . self::$types[$type] . '.php';
        include_once $file;

        $class = 'Services_oEmbed_Object_' . self::$types[$type];
        if (!class_exists($class)) {
            throw new Services_oEmbed_Object_Exception(
                'Object class is invalid or not present'
            );
        }

        $instance = new $class($object);
        return $instance;
    }

    /**
     * Instantiation is not allowed
     *
     * @return void
     */
    private function __construct()
    {
    
    }
}

?>
