<?php

namespace App\Backends\DAV;

use Illuminate\Support\Str;
use Sabre\VObject\Component;
use Sabre\VObject\Property;

class Vtodo extends Vevent
{
    public $due;
    public $percentComplete;

    /**
     * Set object properties from a Sabre/VObject component object
     *
     * @param Component $vobject Sabre/VObject component
     */
    protected function fromVObject(Component $vobject): void
    {
        // Handle common properties with VEVENT
        parent::fromVObject($vobject);

        // map other properties
        foreach ($vobject->children() as $prop) {
            if (!($prop instanceof Property)) {
                continue;
            }

            switch ($prop->name) {
                case 'DUE':
                    // This is of type Sabre\VObject\Property\ICalendar\DateTime
                    $this->due = $prop;
                    break;

                case 'PERCENT-COMPLETE':
                    $this->percentComplete = $prop->getValue();
                    break;
            }
        }
    }
}
