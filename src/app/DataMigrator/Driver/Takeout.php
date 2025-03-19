<?php

namespace App\DataMigrator\Driver;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;

/**
 * Data migration from Google Takeout archive file
 */
class Takeout implements ExporterInterface
{
    /** @var Account Account (local file) to operate on */
    protected $account;

    /** @var Engine Data migrator engine */
    protected $engine;

    /** @var string Local folder with folders/files (extracted from the Takeout archive) */
    protected $location;


    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        $this->account = $account;
        $this->engine = $engine;

        if (!file_exists($account->uri)) {
            throw new \Exception("File does not exists: {$account->uri}");
        }
    }

    /**
     * Authenticate
     */
    public function authenticate(): void
    {
        // NOP
    }

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array
    {
        $this->extractArchive();

        $folders = [];

        // Mail folders
        if (empty($types) || in_array(Engine::TYPE_MAIL, $types)) {
            // GMail has no custom folders, it has labels and categories (we could import them too, as tags)
            // All mail is exported into a single mbox file.
            if (file_exists("{$this->location}/Mail/All mail Including Spam and Trash.mbox")) {
                foreach (['INBOX', 'Sent', 'Drafts', 'Spam', 'Trash'] as $folder) {
                    $folders[] = Folder::fromArray([
                        'fullname' => $folder,
                        'type' => Engine::TYPE_MAIL,
                    ]);
                }
            }
        }

        // Contacts folder
        if (empty($types) || in_array(Engine::TYPE_CONTACT, $types)) {
            if (@filesize("{$this->location}/Contacts/My Contacts/My Contacts.vcf")) {
                $folders[] = Folder::fromArray([
                    'fullname' => 'Contacts',
                    'id' => "{$this->location}/Contacts/My Contacts",
                    'type' => Engine::TYPE_CONTACT,
                ]);
            }
        }

        // Calendars
        if (empty($types) || in_array(Engine::TYPE_EVENT, $types)) {
            if (file_exists("{$this->location}/Calendar")) {
                foreach (glob("{$this->location}/Calendar/*.ics") as $filename) {
                    $folder = preg_replace('/\.ics$/', '', pathinfo($filename, \PATHINFO_BASENAME));
                    $folders[] = Folder::fromArray([
                        // Note: The default calendar is exported into <email>.ics file
                        'fullname' => $folder == $this->account->email ? 'Calendar' : $folder,
                        'id' => $filename,
                        'type' => Engine::TYPE_EVENT,
                    ]);
                }
            }
        }

        // TODO: Tasks
        // TODO: Files

        return $folders;
    }

    /**
     * Fetching a folder metadata
     */
    public function fetchFolder(Folder $folder): void
    {
        // NOP
    }

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void
    {
        $this->extractArchive();

        // Get existing objects from the destination folder
        $existing = $importer->getItems($folder);

        // Mail
        if ($folder->type == Engine::TYPE_MAIL) {
            if (!file_exists("{$this->location}/Mail/All mail Including Spam and Trash.mbox")) {
                return;
            }

            // Read mbox file line by line
            $fp = fopen("{$this->location}/Mail/All mail Including Spam and Trash.mbox", 'r');
            $msg = '';
            $headline = '';

            while (($line = fgets($fp)) !== false) {
                // make sure we use correct end-line sequence
                if (substr($line, -2) != "\r\n") {
                    $line = substr($line, 0, -1) . "\r\n";
                }

                if (str_starts_with($line, 'From ') && preg_match('/^From [^\s]+ [a-zA-Z]{3} [a-zA-Z]{3}/', $line)) {
                    $this->mailItemHandler($folder, $headline, $msg, $existing, $callback);
                    $msg = '';
                    $headline = $line;
                    continue;
                }

                // TODO: Probably stream_get_contents() once per message would be faster than concatenating lines
                $msg .= $line;
            }

            fclose($fp);
            $this->mailItemHandler($folder, $headline, $msg, $existing, $callback);
            return;
        }

        // Calendar(s)
        if ($folder->type == Engine::TYPE_EVENT) {
            $foldername = $folder->fullname == 'Calendar' ? $this->account->email : $folder->fullname;
            if (!file_exists("{$this->location}/Calendar/{$foldername}.ics")) {
                return;
            }

            // Read iCalendar file line by line
            // We can't do a sinle pass pass over the stream because events and event exceptions can be
            // spread across the whole file not necessarily one after another.
            $fp = fopen("{$this->location}/Calendar/{$foldername}.ics", 'r');
            $event = '';
            $head = '';
            $got_head = false;
            $events = [];
            $pos = 0;
            $start = null;

            $add_vevent_block = function ($start_pos, $end_pos) use (&$event, &$events) {
                // Get the UID which will be the array key
                if (preg_match('/\nUID:(.[^\r\n]+(\r\n[\s\t][^\r\n]+)*)/', $event, $matches)) {
                    $uid =  str_replace(["\r\n ", "\r\n  "], '', $matches[1]);
                    // Remember position in the stream, we don't want to copy the whole content into memory
                    $chunk = $start_pos . ':' . $end_pos;
                    $events[$uid] = isset($events[$uid]) ? array_merge($events[$uid], [$chunk]) : [$chunk];
                }

                $event = '';
            };

            while (($line = fgets($fp)) !== false) {
                $pos += strlen($line);

                if (str_starts_with($line, 'BEGIN:VEVENT')) {
                    $got_head = true;
                    if ($start) {
                        $add_vevent_block($start, $pos - strlen($line));
                    }

                    $start = $pos - strlen($line);
                } elseif (!$got_head) {
                    $head .= $line;
                    continue;
                }

                if (str_starts_with($line, 'END:VCALENDAR')) {
                    $pos -= strlen($line);
                    break;
                }

                $event .= $line;
            }

            if ($start) {
                $add_vevent_block($start, $pos);
            }

            // Handle the events one by one (joining multiple VEVENT blocks for the same event)
            foreach ($events as $chunks) {
                $event = '';
                foreach ($chunks as $pos) {
                    [$start, $end] = explode(':', $pos);
                    $event .= stream_get_contents($fp, intval($end) - intval($start), intval($start));
                }

                $this->eventItemHandler($folder, $head . $event . "END:VCALENDAR\r\n", $existing, $callback);
            }

            fclose($fp);
            return;
        }

        // Contacts
        if ($folder->type == Engine::TYPE_CONTACT) {
            if (!file_exists("{$this->location}/Contacts/My Contacts/My Contacts.vcf")) {
                return;
            }

            // Read vCard file line by line
            $fp = fopen("{$this->location}/Contacts/My Contacts/My Contacts.vcf", 'r');
            $vcard = '';

            while (($line = fgets($fp)) !== false) {
                if (str_starts_with($line, 'END:VCARD')) {
                    $this->contactItemHandler($folder, $vcard . $line, $existing, $callback);
                    $vcard = '';
                    continue;
                }

                // TODO: Probably stream_get_contents() once per event would be faster than concatenating lines
                $vcard .= $line;
            }

            // TODO: Takeout does not include vCards for groups (labels), but we could consider
            // creating them for all CATEGORIES mentioned in all contacts.

            fclose($fp);
            return;
        }

        // TODO: Tasks (JSON format, is there any spec?)
        // TODO: Files
        // TODO: Filters (JSON format, is there any spec?)
    }

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void
    {
        // Do nothing, we do all data processing in fetchItemList()
    }

    /**
     * Extract the ZIP archive into temp storage location
     */
    protected function extractArchive(): void
    {
        if ($this->location) {
            return;
        }

        // Use the same location as in the DataMigrator Engine
        $location = storage_path('export/') . $this->account->email;

        if (is_dir($location)) {
            $this->engine->debug("ZIP archive is already extracted at {$location}");
            $this->location = "{$location}/Takeout";
            return;
        }

        $this->engine->debug("Extracting ZIP archive...");

        $zip = new \ZipArchive();
        if (!$zip->open($this->account->uri)) {
            throw new \Exception("Failed to extract Takeout archive file. " . $zip->getStatusString());
        }

        // This will create storage/export/<email>/Takeout folder with
        // Calendar, Contacts, Drive, Mail folders
        $zip->extractTo($location);
        $zip->close();

        $this->location = "{$location}/Takeout";
    }

    /**
     * Handle vCard item
     */
    protected function contactItemHandler(Folder $folder, string $vcard, array $existing, $callback): void
    {
        // Let's try to handle the content without an expensive parser

        $inject = [];
        $fn = null;
        if (preg_match('/\nFN:([^\r\n]+)/', $vcard, $matches)) {
            $fn = $matches[1];
        }

        if ($fn === null) {
            return;
        }

        // It looks like Takeout's contacts do not include UID nor REV properties, we'll add these
        // as they are vital for incremental migration
        if (preg_match('/\nUID:([^\r\n]+)/', $vcard, $matches)) {
            $uid = $matches[1];
        } else {
            // FIXME: FN might not be unique enough probably?
            $uid = md5($fn);
            $inject[] = "UID:{$uid}";
        }

        if (preg_match('/\nREV:([^\r\n]+)/', $vcard, $matches)) {
            $rev = $matches[1];
        } else {
            $rev = date('Y-m-d', crc32($vcard));
            $inject[] = "REV:{$rev}";
        }

        // Skip message that exists and did not change
        $exists = $existing[$uid] ?? null;
        if ($exists) {
            if ($exists['rev'] === $rev) {
                return;
            }
        }

        if (!empty($inject)) {
            $vcard = str_replace("\r\nEND:VCARD", "\r\n" . implode("\r\n", $inject) . "\r\nEND:VCARD", $vcard);
        }

        // Replace PHOTO url with the file content if included outside the .vcf file
        if ($pos = strpos($vcard, "\nPHOTO:https:")) {
            // FIXME: Are these only jpegs?
            // Note: If there's two contacts with the same FN there will be two images:
            // "First Last.jpg" and "First Last(1).jpg", in random order. We ignore all of them.
            $photo = "{$this->location}/Contacts/My Contacts/{$fn}.jpg";
            if (file_exists($photo) && !file_exists("{$this->location}/Contacts/My Contacts/{$fn}(1).jpg")) {
                $content = file_get_contents($photo);
                $content = rtrim(chunk_split(base64_encode($content), 76, "\r\n "));

                $endpos = strpos($vcard, "\r\n", $pos);
                $vcard = substr_replace($vcard, "PHOTO:{$content}", $pos + 1, $endpos - $pos);
            }
        }

        $item = Item::fromArray([
            'id' => $uid,
            'folder' => $folder,
            'existing' => $exists ? $exists['href'] : null,
        ]);

        $this->storeItemContent($item, $vcard, $uid . '.vcf');

        $callback($item);
    }

    /**
     * Handle VEVENT item
     */
    protected function eventItemHandler(Folder $folder, string $event, array $existing, $callback): void
    {
        // Let's try to handle the content without an expensive parser

        if (!preg_match('/\nUID:([^\r\n]+)/', $event, $matches)) {
            return;
        }

        $uid = $matches[1];
        $dtstamp = null;

        if (preg_match('/\nDTSTAMP:([^\r\n]+)/', $event, $matches)) {
            $dtstamp = $matches[1];
        }

        // Skip message that exists and did not change
        $exists = $existing[$uid] ?? null;
        if ($exists) {
            if ($exists['dtstamp'] === $dtstamp) {
                return;
            }
        }

        // Takeout can include events without ORGANIZER, but Cyrus requires it (also in events with RECURRENCE-ID)
        // TODO: Replace existing organizer/attendee email with destination email?
        if (!strpos($event, "\nORGANIZER")) {
            $organizer = 'mailto:' . $this->account->email;
            $event = str_replace("\r\nEND:VEVENT", "\r\nORGANIZER:{$organizer}\r\nEND:VEVENT", $event);
        }

        // TODO: Takeout's VCALENDAR files lack VTIMEZONE block, we should use X-WR-TIMEZONE property
        // to fill timezone into the event.

        $item = Item::fromArray([
            'id' => $uid,
            'folder' => $folder,
            'existing' => $exists ? $exists['href'] : null,
        ]);

        $this->storeItemContent($item, $event, $uid . '.ics');

        $callback($item);
    }

    /**
     * Handle mail message
     */
    protected function mailItemHandler(Folder $folder, string $headline, string $msg, array $existing, $callback): void
    {
        if (!($length = strlen($msg))) {
            return;
        }

        $pos = strpos($msg, "\r\n\r\n");
        $head = $pos ? substr($msg, 0, $pos) : $msg;

        [$foldername, $date, $id, $flags] = self::parseMailHead($head, $headline);

        if ($folder->fullname !== $foldername) {
            return;
        }
        // Skip message that exists and did not change
        $exists = $existing[$id] ?? null;
        $changed = true;
        if ($exists) {
            $changed = $length != $exists['size']
                || \rcube_utils::strtotime($date) != \rcube_utils::strtotime($exists['date']);

            if (!$changed && $flags == array_values(array_intersect($exists['flags'], ['SEEN', 'FLAGGED']))) {
                return;
            }
        }

        $item = Item::fromArray([
            'id' => $id,
            'folder' => $folder,
            'existing' => $exists,
            'data' => [
                'flags' => $flags,
                'internaldate' => $date,
            ],
        ]);

        if ($changed) {
            // We need mail content for new or changed messages
            $this->storeItemContent($item, $msg, $id . '.eml');
        }

        $callback($item);
    }

    /**
     * Extract some details from mail message headers
     *
     * @param string $head     Mail headers
     * @param string $headline MBOX format header (separator) line
     */
    protected static function parseMailHead($head, $headline): array
    {
        $from = explode(' ', $headline, 3);

        $date = isset($from[2]) ? trim($from[2]) : null;
        $folder = 'INBOX';
        $flags = [];
        $id = $from[1] ?? '';

        // Get folder and message state/flags
        if (preg_match('/\nX-Gmail-Labels:([^\r\n]+)/', $head, $matches)) {
            $labels = explode(',', $matches[1]);
            $labels = array_map('trim', $labels);

            if (in_array('Trash', $labels) || in_array('[Imap]/Trash', $labels)) {
                $folder = 'Trash';
            } elseif (in_array('Drafts', $labels) || in_array('[Imap]/Drafts', $labels)) {
                $folder = 'Drafts';
            } elseif (in_array('Sent', $labels) || in_array('[Imap]/Sent', $labels)) {
                $folder = 'Sent';
            }

            // Note: It doesn't look like Google supports ANSWERED, FORWARDED and MDNSENT state
            if (!in_array('Unread', $labels)) {
                $flags[] = 'SEEN';
            }
            if (in_array('Starred', $labels)) {
                $flags[] = 'FLAGGED';
            }
        }

        if (preg_match('/\nMessage-Id:([^\r\n]+)/i', $head, $matches)) {
            // substr() for sanity and compatibility with the IMAP driver
            $id = substr(trim($matches[1]), 0, 2048);
        }

        // Convert date into IMAP format, Takeout uses custom format "Sat Jan  3 01:05:34 +0200 1996"
        try {
            $dt = new \DateTime($date);
            $date = sprintf('%2s', $dt->format('j')) . $dt->format('-M-Y H:i:s O');
        } catch (\Exception $e) {
            \Log::warning("Failed to convert date format for: {$date}");
            $date = null;
        }

        // If Message-ID header does not exist we need to make $id compatible with
        // the one generated by the IMAP driver (for incremental migration comparisons)
        if (empty($id)) {
            if (preg_match('/\nFrom:([^\r\n]+)/i', $head, $matches)) {
                $from_header = $matches[1];
            }
            if (preg_match('/\nDate:([^\r\n]+)/i', $head, $matches)) {
                $date_header = $matches[1];
            }

            $id = md5($folder . ($from_header ?? '') . \rcube_utils::strtotime($date_header ?? $date));
        }

        return [$folder, $date, $id, $flags];
    }

    /**
     * Store item content in a temp file so it can be used by an importer
     */
    protected function storeItemContent($item, $content, $filename)
    {
        if (strlen($content) > Engine::MAX_ITEM_SIZE) {
            $location = $item->folder->tempFileLocation($filename);

            if (file_put_contents($location, $content) === false) {
                throw new \Exception("Failed to write to file at {$location}");
            }

            $item->filename = $location;
        } else {
            $item->content = $content;
            $item->filename = $filename;
        }
    }
}
