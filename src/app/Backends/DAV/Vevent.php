<?php

namespace App\Backends\DAV;

use Illuminate\Support\Str;
use Sabre\VObject;

class Vevent extends CommonObject
{
    /** @var string Object content type (of the string representation) */
    public $contentType = 'text/calendar; charset=utf-8';

    public $attendees = [];
    public $comment;
    public $description;
    public $location;
    public $organizer;
    public $recurrence = [];
    public $sequence;
    public $status;
    public $summary;
    public $transp;
    public $url;
    public $valarms = [];

    public $dtstart;
    public $dtend;
    public $due;
    public $created;
    public $lastModified;
    public $dtstamp;

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

        if ($data = $element->getElementsByTagName('calendar-data')->item(0)) {
            $object->fromIcal($data->nodeValue);
        }

        return $object;
    }

    /**
     * Set object properties from an iCalendar
     *
     * @param string $ical iCalendar string
     */
    protected function fromIcal(string $ical): void
    {
        $options = VObject\Reader::OPTION_FORGIVING | VObject\Reader::OPTION_IGNORE_INVALID_LINES;
        $this->vobject = VObject\Reader::read($ical, $options);

        if ($this->vobject->name != 'VCALENDAR') {
            return;
        }

        $selfType = strtoupper(class_basename(get_class($this)));

        foreach ($this->vobject->getComponents() as $component) {
            if ($component->name == $selfType) {
                $this->fromVObject($component);
                return;
            }
        }
    }

    /**
     * Set object properties from a Sabre/VObject component object
     *
     * @param VObject\Component $vobject Sabre/VObject component
     */
    protected function fromVObject(VObject\Component $vobject): void
    {
        $string_properties = [
            'COMMENT',
            'DESCRIPTION',
            'LOCATION',
            'SEQUENCE',
            'STATUS',
            'SUMMARY',
            'TRANSP',
            'UID',
            'URL',
        ];

        // map string properties
        foreach ($string_properties as $prop) {
            if (isset($vobject->{$prop})) {
                $key = Str::camel(strtolower($prop));
                $this->{$key} = (string) $vobject->{$prop};
            }
        }

        // map other properties
        foreach ($vobject->children() as $prop) {
            if (!($prop instanceof VObject\Property)) {
                continue;
            }

            switch ($prop->name) {
                case 'DTSTART':
                case 'DTEND':
                case 'DUE':
                case 'CREATED':
                case 'LAST-MODIFIED':
                case 'DTSTAMP':
                    $key = Str::camel(strtolower($prop->name));
                    // These are of type Sabre\VObject\Property\ICalendar\DateTime
                    $this->{$key} = $prop;
                    break;

                case 'RRULE':
                    $params = !empty($this->recurrence) ? $this->recurrence : [];

                    foreach ($prop->getParts() as $k => $v) {
                        $params[Str::camel(strtolower($k))] = is_array($v) ? implode(',', $v) : $v;
                    }

                    if (!empty($params['until'])) {
                        $params['until'] = new \DateTime($params['until']);
                    }

                    if (empty($params['interval'])) {
                        $params['interval'] = 1;
                    }

                    $this->recurrence = array_filter($params);
                    break;

                case 'EXDATE':
                case 'RDATE':
                    $key = strtolower($prop->name);
                    $dates = []; // TODO

                    if (!empty($this->recurrence[$key])) {
                        $this->recurrence[$key] = array_merge($this->recurrence[$key], $dates);
                    } else {
                        $this->recurrence[$key] = $dates;
                    }

                    break;

                case 'ATTENDEE':
                case 'ORGANIZER':
                    $attendee = [
                        'rsvp' => false,
                        'email' => preg_replace('!^mailto:!i', '', (string) $prop),
                    ];

                    $attendeeProps = ['CN', 'PARTSTAT', 'ROLE', 'CUTYPE', 'RSVP', 'DELEGATED-FROM', 'DELEGATED-TO',
                        'SCHEDULE-STATUS', 'SCHEDULE-AGENT', 'SENT-BY'];

                    foreach ($prop->parameters() as $name => $value) {
                        $key = Str::camel(strtolower($name));
                        switch ($name) {
                            case 'RSVP':
                                $params[$key] = strtolower($value) == 'true';
                                break;
                            case 'CN':
                                $params[$key] = str_replace('\,', ',', strval($value));
                                break;
                            default:
                                if (in_array($name, $attendeeProps)) {
                                    $params[$key] = strval($value);
                                }
                                break;
                        }
                    }

                    if ($prop->name == 'ORGANIZER') {
                        $attendee['role'] = 'ORGANIZER';
                        $attendee['partstat'] = 'ACCEPTED';

                        $this->organizer = $attendee;
                    } elseif (empty($this->organizer) || $attendee['email'] != $this->organizer['email']) {
                        $this->attendees[] = $attendee;
                    }

                    break;

                default:
                    if (\str_starts_with($prop->name, 'X-')) {
                        $this->custom[$prop->name] = (string) $prop;
                    }
            }
        }

        // Check DURATION property if no end date is set
        /*
        if (empty($this->dtend) && !empty($this->dtstart) && !empty($vobject->DURATION)) {
            try {
                $duration = new \DateInterval((string) $vobject->DURATION);
                $end = clone $this->dtstart;
                $end->add($duration);
                $this->dtend = $end;
            }
            catch (\Exception $e) {
                // TODO: Error?
            }
        }
        */

        // Find alarms
        foreach ($vobject->select('VALARM') as $valarm) {
            $action  = 'DISPLAY';
            $trigger = null;
            $alarm   = [];

            foreach ($valarm->children() as $prop) {
                $value = strval($prop);

                switch ($prop->name) {
                    case 'TRIGGER':
                        foreach ($prop->parameters as $param) {
                            if ($param->name == 'VALUE' && $param->getValue() == 'DATE-TIME') {
                                $trigger = '@' . $prop->getDateTime()->format('U');
                                $alarm['trigger'] = $prop->getDateTime();
                            } elseif ($param->name == 'RELATED') {
                                $alarm['related'] = $param->getValue();
                            }
                        }
    /*
                        if (!$trigger && ($values = libcalendaring::parse_alarm_value($value))) {
                            $trigger = $values[2];
                        }
    */
                        if (empty($alarm['trigger'])) {
                            $alarm['trigger'] = rtrim(preg_replace('/([A-Z])0[WDHMS]/', '\\1', $value), 'T');
                            // if all 0-values have been stripped, assume 'at time'
                            if ($alarm['trigger'] == 'P') {
                                $alarm['trigger'] = 'PT0S';
                            }
                        }
                        break;

                    case 'ACTION':
                        $action = $alarm['action'] = strtoupper($value);
                        break;

                    case 'SUMMARY':
                    case 'DESCRIPTION':
                    case 'DURATION':
                        $alarm[strtolower($prop->name)] = $value;
                        break;

                    case 'REPEAT':
                        $alarm['repeat'] = (int) $value;
                        break;

                    case 'ATTENDEE':
                        $alarm['attendees'][] = preg_replace('!^mailto:!i', '', $value);
                        break;
                }
            }

            if ($action != 'NONE') {
                if (!empty($alarm['trigger'])) {
                    $this->valarms[] = $alarm;
                }
            }
        }
    }

    /**
     * Create string representation of the DAV object (iCalendar)
     *
     * @return string
     */
    public function __toString()
    {
        if (!$this->vobject) {
            //TODO we currently can only serialize a message back that we just read
            throw new \Exception("Writing from properties is not implemented");
        }
        return VObject\Writer::write($this->vobject);
    }
}
