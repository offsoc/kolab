<?php

namespace App\Backends\DAV;

use Illuminate\Support\Str;
use Sabre\VObject\Reader;
use Sabre\VObject\Property;
use Sabre\VObject\Writer;

class Vcard extends CommonObject
{
    /** @var string Object content type (of the string representation) */
    public $contentType = 'text/vcard; charset=utf-8';

    public $class;
    public $email = [];
    public $fn;
    public $kind;
    public $note;
    public $members = [];
    public $prodid;
    public $rev;
    public $version;

    private $vobject;


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

        $this->vobject = $vobject;
        $this->members = [];

        $string_properties = [
            'CLASS',
            'FN',
            'KIND',
            'NOTE',
            'PRODID',
            'REV',
            'UID',
            'VERSION',
        ];

        foreach ($vobject->children() as $prop) {
            if (!($prop instanceof Property)) {
                continue;
            }

            switch ($prop->name) {
                // TODO: Map all vCard properties to class properties

                case 'EMAIL':
                    $props = [];
                    foreach ($prop->parameters() as $name => $value) {
                        $key = Str::camel(strtolower($name));
                        $props[$key] = (string) $value;
                    }

                    $props['email'] = (string) $prop;
                    $this->email[] = $props;
                    break;

                case 'MEMBER':
                    foreach ($prop as $member) {
                        $value = (string) $member;
                        if (preg_match('/^mailto:/', $value)) {
                            $this->members[] = $value;
                        }
                    }
                    break;

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

        if (!empty($this->custom['X-ADDRESSBOOKSERVER-KIND']) && empty($this->kind)) {
            $this->kind = strtolower($this->custom['X-ADDRESSBOOKSERVER-KIND']);
        }
    }

    /**
     * Create string representation of the DAV object (vcard)
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->vobject) {
            // TODO we currently can only serialize a message back that we just read
            throw new \Exception("Writing from properties is not implemented");
        }

        return Writer::write($this->vobject);
    }
}
