<?php

class Atom10EntryException extends Exception
{
}

class Atom10Entry extends XMLStringer
{
    private $namespaces;
    private $categories;
    private $content;
    private $contributors;
    private $id;
    private $links;
    private $published;
    private $rights;
    private $source;
    private $summary;
    private $title;

    function __construct($indent = true) {
        parent::__construct($indent);
        $this->namespaces = array();
    }

    function addNamespace($namespace, $uri)
    {
        $ns = array($namespace => $uri);
        $this->namespaces = array_merge($this->namespaces, $ns);
    }

    function initEntry()
    {

    }

    function endEntry()
    {

    }

    function validate
    {

    }

    function getString()
    {
        $this->validate();

        $this->initEntry();
        $this->renderEntries();
        $this->endEntry();

        return $this->xw->outputMemory();
    }

}