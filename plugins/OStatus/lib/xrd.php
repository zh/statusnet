<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * A sample module to show best practices for StatusNet plugins
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   StatusNet
 * @author    James Walker <james@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */


class XRD
{
    const XML_NS = 'http://www.w3.org/2000/xmlns/';
    
    const XRD_NS = 'http://docs.oasis-open.org/ns/xri/xrd-1.0';

    const HOST_META_NS = 'http://host-meta.net/xrd/1.0';
    
    public $expires;

    public $subject;

    public $host;

    public $alias = array();
    
    public $types = array();
    
    public $links = array();
    
    public static function parse($xml)
    {
        $xrd = new XRD();

        $dom = new DOMDocument();

        // Don't spew XML warnings to output
        $old = error_reporting();
        error_reporting($old & ~E_WARNING);
        $ok = $dom->loadXML($xml);
        error_reporting($old);

        if (!$ok) {
            throw new Exception("Invalid XML");
        }
        $xrd_element = $dom->getElementsByTagName('XRD')->item(0);
        if (!$xrd_element) {
            throw new Exception("Invalid XML, missing XRD root");
        }

        // Check for host-meta host
        $host = $xrd_element->getElementsByTagName('Host')->item(0);
        if ($host) {
            $xrd->host = $host->nodeValue;
        }

        // Loop through other elements
        foreach ($xrd_element->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            switch ($node->tagName) {
            case 'Expires':
                $xrd->expires = $node->nodeValue;
                break;
            case 'Subject':
                $xrd->subject = $node->nodeValue;
                break;
                
            case 'Alias':
                $xrd->alias[] = $node->nodeValue;
                break;

            case 'Link':
                $xrd->links[] = $xrd->parseLink($node);
                break;

            case 'Type':
                $xrd->types[] = $xrd->parseType($node);
                break;

            }
        }
        return $xrd;
    }

    public function toXML()
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        $xrd_dom = $dom->createElementNS(XRD::XRD_NS, 'XRD');
        $dom->appendChild($xrd_dom);

        if ($this->host) {
            $host_dom = $dom->createElement('hm:Host', $this->host);
            $xrd_dom->setAttributeNS(XRD::XML_NS, 'xmlns:hm', XRD::HOST_META_NS);
            $xrd_dom->appendChild($host_dom);
        }
        
		if ($this->expires) {
			$expires_dom = $dom->createElement('Expires', $this->expires);
			$xrd_dom->appendChild($expires_dom);
		}

		if ($this->subject) {
			$subject_dom = $dom->createElement('Subject', $this->subject);
			$xrd_dom->appendChild($subject_dom);
		}

		foreach ($this->alias as $alias) {
			$alias_dom = $dom->createElement('Alias', $alias);
			$xrd_dom->appendChild($alias_dom);
		}

		foreach ($this->types as $type) {
			$type_dom = $dom->createElement('Type', $type);
			$xrd_dom->appendChild($type_dom);
		}

		foreach ($this->links as $link) {
			$link_dom = $this->saveLink($dom, $link);
			$xrd_dom->appendChild($link_dom);
		}

        return $dom->saveXML();
    }

    function parseType($element)
    {
        return array();
    }
    
    function parseLink($element)
    {
        $link = array();
        $link['rel'] = $element->getAttribute('rel');
        $link['type'] = $element->getAttribute('type');
        $link['href'] = $element->getAttribute('href');
        $link['template'] = $element->getAttribute('template');
        foreach ($element->childNodes as $node) {
            if ($node instanceof DOMElement) {
                switch($node->tagName) {
                case 'Title':
                    $link['title'][] = $node->nodeValue;
                }
            }
        }

        return $link;
    }

    function saveLink($doc, $link)
    {
        $link_element = $doc->createElement('Link');
        if (!empty($link['rel'])) {
            $link_element->setAttribute('rel', $link['rel']);
        }
        if (!empty($link['type'])) {
            $link_element->setAttribute('type', $link['type']);
        }
        if (!empty($link['href'])) {
            $link_element->setAttribute('href', $link['href']);
        }
        if (!empty($link['template'])) {
            $link_element->setAttribute('template', $link['template']);
        }

        if (!empty($link['title']) && is_array($link['title'])) {
            foreach($link['title'] as $title) {
                $title = $doc->createElement('Title', $title);
                $link_element->appendChild($title);
            }
        }

        
        return $link_element;
    }
}

