<?php

namespace App\Backends\DAV;

use Illuminate\Support\Str;
use Sabre\VObject\Reader;
use Sabre\VObject\Property;

class Vcard extends CommonObject
{
    /** @var string Object content type (of the string representation) */
    public $contentType = 'text/vcard; charset=utf-8';

    public $fn;
    public $rev;


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
        $vobject = Reader::read($vcard, Reader::OPTION_FORGIVING | Reader::OPTION_IGNORE_INVALID_LINES);

        if ($vobject->name != 'VCARD') {
            // FIXME: throw an exception?
            return;
        }

        $string_properties = [
            'FN',
            'REV',
            'UID',
        ];

        foreach ($vobject->children() as $prop) {
            if (!($prop instanceof Property)) {
                continue;
            }

            switch ($prop->name) {
                // TODO: Map all vCard properties to class properties

                default:
                    // map string properties
                    if (in_array($prop->name, $string_properties)) {
                        $key = Str::camel(strtolower($prop->name));
                        $this->{$key} = (string) $prop;
                    }

                    // custom properties
                    if (\str_starts_with($prop->name, 'X-')) {
                        $this->custom[$prop->name] = (string) $prop;
                    }
            }
        }
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
