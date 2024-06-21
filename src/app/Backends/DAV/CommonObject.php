<?php

namespace App\Backends\DAV;

class CommonObject
{
    /** @var string Object content type (of the string representation) */
    public $contentType = '';

    /** @var ?string Object ETag (getetag property) */
    public $etag;

    /** @var ?string Object location (href property) */
    public $href;

    /** @var ?string Object UID */
    public $uid;


    /**
     * Create DAV object from a DOMElement element
     *
     * @param \DOMElement $element DOM element with object properties
     *
     * @return CommonObject
     */
    public static function fromDomElement(\DOMElement $element)
    {
        $object = new static(); // @phpstan-ignore-line

        if ($href = $element->getElementsByTagName('href')->item(0)) {
            $object->href = $href->nodeValue;

            // Extract UID from the URL
            $href_parts = explode('/', $object->href);
            $object->uid = preg_replace('/\.[a-z]+$/', '', $href_parts[count($href_parts) - 1]);
        }

        if ($etag = $element->getElementsByTagName('getetag')->item(0)) {
            $object->etag = $etag->nodeValue;
            if (preg_match('|^".*"$|', $object->etag)) {
                $object->etag = substr($object->etag, 1, -1);
            }
        }

        return $object;
    }

    /**
     * Create string representation of the DAV object
     *
     * @return string
     */
    public function __toString()
    {
        return '';
    }
}
