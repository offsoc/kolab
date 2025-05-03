<?php

namespace App\Backends\DAV;

class Notification
{
    public const NOTIFICATION_SHARE_INVITE = 'share-invite-notification';
    public const NOTIFICATION_SHARE_REPLY = 'share-reply-notification';

    public const INVITE_NORESPONSE = 'invite-noresponse';
    public const INVITE_ACCEPTED = 'invite-accepted';
    public const INVITE_DECLINED = 'invite-declined';
    public const INVITE_INVALID = 'invite-invalid';
    public const INVITE_DELETED = 'invite-deleted';

    public const INVITE_STATES = [
        self::INVITE_NORESPONSE,
        self::INVITE_ACCEPTED,
        self::INVITE_DECLINED,
        self::INVITE_INVALID,
        self::INVITE_DELETED,
    ];

    /** @var ?string Notification (invitation) share-access property */
    public $access;

    /** @var ?string Notification location */
    public $href;

    /** @var ?string Notification type */
    public $type;

    /** @var ?string Notification (invitation) status */
    public $status;

    /** @var ?string Notification (invitation) principal (organizer) */
    public $principal;


    /**
     * Create Notification object from a DOMElement element
     *
     * @param \DOMElement $element DOM element with notification properties
     * @param string      $href    Notification location
     *
     * @return Notification
     */
    public static function fromDomElement(\DOMElement $element, string $href)
    {
        $notification = new self();
        $notification->href = $href;

        if ($type = $element->getElementsByTagName('notificationtype')->item(0)) {
            if ($type->firstChild) {
                $notification->type = $type->firstChild->localName;
            }
        }

        if ($access = $element->getElementsByTagName('access')->item(0)) {
            if ($access->firstChild) {
                $notification->access = $access->firstChild->localName; // 'read' or 'read-write'
            }
        }

        foreach (self::INVITE_STATES as $name) {
            if ($node = $element->getElementsByTagName($name)->item(0)) {
                $notification->status = $node->localName;
            }
        }

        if ($organizer = $element->getElementsByTagName('organizer')->item(0)) {
            if ($href = $organizer->getElementsByTagName('href')->item(0)) {
                $notification->principal = $href->nodeValue;
            }
            // There should be also 'displayname', but Cyrus uses 'common-name',
            // we'll ignore it for now anyway.
        } elseif ($principal = $element->getElementsByTagName('principal')->item(0)) {
            if ($href = $principal->getElementsByTagName('href')->item(0)) {
                $notification->principal = $href->nodeValue;
            }
            // There should be also 'displayname', but Cyrus uses 'common-name',
            // we'll ignore it for now anyway.
        }

        return $notification;
    }

    /**
     * Get XML string for PROPFIND query on a notification
     *
     * @return string
     */
    public static function propfindXML()
    {
        // Note: With <d:allprop/> notificationtype is not returned, but it's essential
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
                . '<d:prop>'
                    . '<d:notificationtype/>'
                . '</d:prop>'
            . '</d:propfind>';
    }
}
