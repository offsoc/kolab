<?php

namespace App\Backends\DAV;

class Folder
{
    /** @var ?string Folder location (href property) */
    public $href;

    /** @var ?string Folder name (displayname property) */
    public $name;

    /** @var ?string Folder CTag (getctag property) */
    public $ctag;

    /** @var array Supported component set (supported-*-component-set property) */
    public $components = [];

    /** @var array Supported resource types (resourcetype property) */
    public $types = [];

    /** @var ?string Folder color (calendar-color property) */
    public $color;


    /**
     * Create Folder object from a DOMElement element
     *
     * @param \DOMElement $element DOM element with folder properties
     *
     * @return Folder
     */
    public static function fromDomElement(\DOMElement $element)
    {
        $folder = new Folder();

        if ($href = $element->getElementsByTagName('href')->item(0)) {
            $folder->href = $href->nodeValue;
        }

        if ($color = $element->getElementsByTagName('calendar-color')->item(0)) {
            if (preg_match('/^#[0-9a-fA-F]{6,8}$/', $color->nodeValue)) {
                $folder->color = substr($color->nodeValue, 1);
            }
        }

        if ($name = $element->getElementsByTagName('displayname')->item(0)) {
            $folder->name = $name->nodeValue;
        }

        if ($ctag = $element->getElementsByTagName('getctag')->item(0)) {
            $folder->ctag = $ctag->nodeValue;
        }

        $components = [];
        if ($set_element = $element->getElementsByTagName('supported-calendar-component-set')->item(0)) {
            foreach ($set_element->getElementsByTagName('comp') as $comp) {
                $components[] = $comp->attributes->getNamedItem('name')->nodeValue;
            }
        }

        $types = [];
        if ($type_element = $element->getElementsByTagName('resourcetype')->item(0)) {
            foreach ($type_element->childNodes as $node) {
                if ($node->nodeType == XML_ELEMENT_NODE) {
                    $_type = explode(':', $node->nodeName);
                    $types[] = count($_type) > 1 ? $_type[1] : $_type[0];
                }
            }
        }

        $folder->types = $types;
        $folder->components = $components;

        return $folder;
    }
}
