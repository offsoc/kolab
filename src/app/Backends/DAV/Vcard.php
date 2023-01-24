<?php

namespace App\Backends\DAV;

class Vcard extends CommonObject
{
    /** @var string Object content type (of the string representation) */
    public $contentType = 'text/vcard; charset=utf-8';

    /**
     * Create event object from a DOMElement element
     *
     * @param \DOMElement $element DOM element with object properties
     *
     * @return CommonObject
     */
    public static function fromDomElement(\DOMElement $element)
    {
        /** @var self $object */
        $object = parent::fromDomElement($element);

        if ($data = $element->getElementsByTagName('address-data')->item(0)) {
            $object->fromVcard($data->nodeValue);
        }

        return $object;
    }

    /**
     * Set object properties from a vcard
     *
     * @param string $vcard vCard string
     */
    protected function fromVcard(string $vcard): void
    {
        // TODO
    }

    /**
     * Create string representation of the DAV object (vcard)
     *
     * @return string
     */
    public function __toString()
    {
        // TODO: This will be needed when we want to create/update objects
        return '';
    }
}
