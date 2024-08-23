<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;


class MigrateUserdata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:userdata
                                {--importpst= : Pst file path}
                                {--extractonly : Only extract the pst file, then exit.}
                                {--username= : Target user}
                                {--password= : Password}
                                {--davUrl= : caldav server url}
                                {--imapUrl= : imap server url}
                                {--clear-target : Remove all messages from the target mailbox.}
                                {--subscribe : Subscribe to the target mailbox.}
                                {--exclude-target=* : Blacklist target mailbox.}
                                {--include-target=* : Whitelist target mailbox.}
                                {--pickup-from= : Pick-up import from a specific target mailbox.}
                                {--type-filter= : Whitelist folder type  [calendar, mail, addressbook]. Not a list atm, only single value.}
                                {--type-blacklist= : Blacklist folder type  [calendar, mail, addressbook]. Not a list atm, only single value.}
                                {--debug : Enable debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate userdata';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('importpst')) {
            return $this->importPst(
                $this->option('importpst'),
                $this->option('extractonly'),
                $this->option('username'),
                $this->option('password'),
                $this->option('clear-target'),
                $this->option('subscribe'),
                $this->option('exclude-target'),
                $this->option('include-target'),
                $this->option('pickup-from'),
                $this->option('debug'),
                $this->option('davUrl'),
                $this->option('imapUrl'),
                $this->option('type-filter'),
                $this->option('type-blacklist'),
            );
        }
    }


     /**
      * Shortcut to creating a progress bar of a particular format with a particular message.
      *
      * @param \Illuminate\Console\OutputStyle $output  Console output object
      * @param int                             $count   Number of progress steps
      * @param string                          $message The description
      *
      * @return \Symfony\Component\Console\Helper\ProgressBar
      */
     private static function createProgressBar($output, $count, $message = null)
     {
         $bar = $output->createProgressBar($count);
 
         $bar->setFormat(
             '%current:7s%/%max:7s% [%bar%] %percent:3s%% %elapsed:7s%/%estimated:-7s% %message% '
         );
 
         if ($message) {
             $bar->setMessage($message . " ...");
         }
 
         $bar->start();
 
         return $bar;
     }


    /**
     * Get LDAP configuration for specified access level
     */
    private static function getConfig($imapUrl, $username, $password, $verifyPeer, $verifyHost)
    {
        $uri = \parse_url($imapUrl ?? \config('imap.uri'));
        $default_port = 143;
        $ssl_mode = null;

        if (isset($uri['scheme'])) {
            if (preg_match('/^(ssl|imaps)/', $uri['scheme'])) {
                $default_port = 993;
                $ssl_mode = 'ssl';
            } elseif ($uri['scheme'] === 'tls') {
                $ssl_mode = 'tls';
            }
        }

        $config = [
            'host' => $uri['host'],
            'user' => $username ?? \config('imap.admin_login'),
            'password' => $password ?? \config('imap.admin_password'),
            'options' => [
                'port' => !empty($uri['port']) ? $uri['port'] : $default_port,
                'ssl_mode' => $ssl_mode,
                'timeout' => 60,
                //Work around guam bug
                'literal+' => false,
                'socket_options' => [
                    'ssl' => [
                        'verify_peer' => $verifyPeer ?? \config('imap.verify_peer'),
                        'verify_peer_name' => $verifyPeer ?? \config('imap.verify_peer'),
                        'verify_host' => $verifyHost ?? \config('imap.verify_host')
                    ],
                ],
            ],
        ];

        return $config;
    }

    /**
     * Initialize connection to IMAP
     */
    private function initIMAP(array $config, string $login_as = null, $debug)
    {
        $imap = new \rcube_imap_generic();

        if ($debug || \config('app.debug')) {
            $imap->setDebug(true, 'App\Backends\IMAP::logDebug');
        }

        if ($login_as) {
            $config['options']['auth_cid'] = $config['user'];
            $config['options']['auth_pw'] = $config['password'];
            $config['options']['auth_type'] = 'PLAIN';
            $config['user'] = $login_as;
        }

        $imap->connect($config['host'], $config['user'], $config['password'], $config['options']);

        if (!$imap->connected()) {
            $message = sprintf("Login failed for %s against %s. %s", $config['user'], $config['host'], $imap->error);
            $this->error($message);
            return null;
        }

        return $imap;
    }

    private static function imapFlagsFromStatus($status)
    {
        # libpst only sets R and O
        $flags = [];
        if (strpos($status, 'R') !== false || strpos($status, 'O') !== false) {
            $flags[] = 'SEEN';
        }
        /* if 'D' in flags: */
        /*     $flags[] = '\Deleted' */
        /* if 'A' in flags: */
        /*     $flags[] = '\Answered' */
        /* if 'F' in flags */
        /*     $flags[] = '\Flagged' */
        return $flags;
    }

    private static function appendFromFile($imap, $mailbox, $path)
    {
        // open message file
        $fp = null;
        if (file_exists(realpath($path))) {
            $fp = fopen($path, 'r');
        }

        if (!$fp) {
            print("Failed to open for reading: " . $path);
            return false;
        }
        $filesize = filesize($path);
        if ($filesize <= 0) {
            print("Empty file: " . $path . "\n");
            return true;
        }

        $message = fread($fp, $filesize);
        // IMAP requires CRLF
        $message = str_replace("\n", "\r\n", str_replace("\r", '', $message));

        $flags = [];
        $matches = null;
        //In practice we only seem to get RO
        if (preg_match("/Status: (R?O?)\r\n/", $message, $matches)) {
            /* print("Found matches:\n"); */
            /* print_r($matches); */
            $flags = self::imapFlagsFromStatus($matches[1]);
            $message = preg_replace("/Status: (R?O?)\r\n/", "", $message);
        }
        $date = null;
        $binary = false;
        return $imap->append($mailbox, $message, $flags, $date, $binary);
    }

    private static function listDirectoryTree($dir)
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $directories = [];
        foreach ($it as $fileinfo) {
            //We look for all "." direcotires, because we somehow can't just iterate over directories without files.
            if ($fileinfo->isDir() && $fileinfo->getFilename() == "." && dirname($fileinfo->getPath()) != dirname($dir)) {
                $directories[] = $fileinfo->getPath();
            }
        }
        sort($directories);
        return $directories;
    }


    private function createTemporaryDirectory()
    {
        $tempfile = tempnam(sys_get_temp_dir(), '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
        }
        mkdir($tempfile);
        if (!is_dir($tempfile)) {
            return null;
        }
        return $tempfile . "/";
    }


    private static function fixICal($ical)
    {
        $lines = explode("\n", $ical);

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            //Remove nonsensical NONE categories libpst by Outlook
            if ($line == "CATEGORIES:NONE") {
                $lines[$i] = null;
            }
        }
        return implode("\n", array_filter($lines));
    }


    private static function caldavAppendFromFile($serverUrl, $user, $pw, $mailbox, $path)
    {
        $content = file_get_contents($path);
        if (!$content) {
            print("Failed to open for reading: " . $path);
            return false;
        }

        $putdata = self::fixICal($content);

        // print($putdata . "\n");

        $urlParts = parse_url($serverUrl);
        $newUrl = $urlParts['scheme'] . "://" . $urlParts['host'];
        if (isset($urlParts['port'])) {
            $newUrl = $newUrl . ":" . $urlParts['port'];
        }
        $url = $newUrl . $mailbox . basename($path);

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HTTPHEADER, array("Content-Type: text/calendar; charset='UTF-8'"));
        curl_setopt($c, CURLOPT_HEADER, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($c, CURLOPT_USERPWD, mb_strtolower($user) . ":" . $pw);
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($c, CURLOPT_POSTFIELDS, $putdata);

        $data = curl_exec($c);

        if (!$data) {
            print("Append failed: " . curl_error($c) . "\n");
            curl_close($c);
            return false;
        }

        curl_close($c);
        return true;
    }


    private static function fixVCard($vcard)
    {
        $lines = explode("\n", $vcard);

        $lines = array_filter($lines, function($line) {
            if ($line == "FN:(null)") {
                return false;
            }
            if ($line == "N:;;;;") {
                return false;
            }

            return true;
        });
        //Generate a UID if there isn't one
        if (strpos($vcard, "UID") === false) {
            $uid = uniqid();
            array_splice($lines, 1, 0, ["UID:{$uid}"]);
        }

        return implode("\n", $lines);
    }

    private function carddavAppendFromFile($serverUrl, $user, $pw, $mailbox, $path)
    {
        $content = file_get_contents($path);
        if (!$content) {
            print("Failed to open for reading: " . $path);
            return false;
        }

        $putdata = self::fixVCard($content);

        // print($putdata . "\n");

        $urlParts = parse_url($serverUrl);
        $newUrl = $urlParts['scheme'] . "://" . $urlParts['host'];
        if (isset($urlParts['port'])) {
            $newUrl = $newUrl . ":" . $urlParts['port'];
        }
        $url = $newUrl . $mailbox . basename($path);

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HTTPHEADER, array("Content-Type: text/vcard; charset='UTF-8'"));
        curl_setopt($c, CURLOPT_HEADER, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($c, CURLOPT_USERPWD, mb_strtolower($user) . ":" . $pw);
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($c, CURLOPT_POSTFIELDS, $putdata);

        $data = curl_exec($c);

        if (!$data) {
            print("Append failed: " . curl_error($c) . "\n");
            curl_close($c);
            return false;
        }
        curl_close($c);
        return true;
    }

    private static function convertToMailbox($name)
    {
        // Replace forbidden characters
        //List based on testing Kolab's Cyrus-IMAP 2.5 (according to the roundcube codebase)
        $name = str_replace(';', '_', $name);
        $name = str_replace('<', '_', $name);
        $name = str_replace('?', '_', $name);
        $name = str_replace('\\', '_', $name);
        $name = str_replace('|', '_', $name);
        $name = str_replace('`', '_', $name);
        $name = str_replace('!', '_', $name);
        $name = str_replace('{', '_', $name);
        $name = str_replace('}', '_', $name);
        $name = str_replace('(', '_', $name);
        $name = str_replace(')', '_', $name);
        $name = str_replace('@', 'at', $name);
        return mb_convert_encoding($name, "UTF7-IMAP", "UTF-8");
    }

    private function findDavMailbox($url, $user, $pw, $mailbox)
    {
        $xml = '<d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>';

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HTTPHEADER, array("Depth: 1", "Content-Type: text/xml; charset='UTF-8'", "Prefer: return-minimal"));
        curl_setopt($c, CURLOPT_HEADER, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($c, CURLOPT_USERPWD, mb_strtolower($user) . ":" . $pw);
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, "PROPFIND");
        curl_setopt($c, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($c);
        if (!$data) {
            $this->error("Curl propfind failed on url {$url}: " . curl_error($c) . "\n");
            curl_close($c);
            return null;
        }
        curl_close($c);

        try {
            $xml = \simplexml_load_string($data, null, 0, "d", true);
        } catch (\Exception $e) {
            $this->error("Exception during xml parsing: {$e->getMessage()}\n");
            $xml = null;
        }

        if (!$xml) {
            $this->error("Failed to parse xml from {$url}\n");
            $this->error("\n" . $data . "\n");

            $errors = \libxml_get_errors();

            foreach ($errors as $error) {
                $this->error("Error: " . $error->message . "\n");
            }

            \libxml_clear_errors();
            return null;
        }

        foreach ($xml->response as $e) {
            $displayname = (string)$e->propstat->prop->displayname;

            //Convert the hierarchy separator back from " » ", " : " or "/"
            //The utf-8 modifier is required for the multibyte character "»" to work within []
            $parts = preg_split('!(*UTF8)(\s*/\s*|\s+[»:]\s+)!', $displayname);
            $converted = self::convertToMailbox(join('/', $parts));
            $this->info("Found {$displayname} : {$converted}");

            if ($converted == $mailbox) {
                return (string)$e->href;
            }
        }

        $this->error("Could not find a matching dav collection for {$mailbox}");
        $this->error("\n" . $data . "\n");

        return null;
    }

    private static function mailboxFromDirectory($directory, $directoryRootDepth, $hierarchyDelimiter)
    {
        // Ignore the toplevel directory which is something like: "Outlook Data File"
        $mailboxParts = array_slice(explode('/', $directory), $directoryRootDepth + 1);

        //Move folders out of inbox
        if (count($mailboxParts) > 1 && strcasecmp($mailboxParts[0], "inbox") == 0) {
            $mailboxParts = array_slice($mailboxParts, 1);
        }

        $mailboxParts = array_map(
            function ($part) {
                return self::convertToMailbox($part);
            },
            $mailboxParts
        );
        return implode($hierarchyDelimiter, $mailboxParts);
    }

    private static function inBlacklist($mailbox, $mailboxBlacklist)
    {
        foreach ($mailboxBlacklist as $entry) {
            if (fnmatch($entry, $mailbox)) {
                return true;
            }
        }
        return false;
    }

    private function getFolderType($type)
    {
        if ($type == "addressbook") {
            return 'contact';
        }
        if ($type == "calendar") {
            return 'event';
        }
        return null;
    }


    private function createFolder($imap, $mailbox, $type, $clearTarget, $subscribe, $davUrl, $username, $password)
    {
        $count = $imap->countMessages($mailbox);
        if ($count === false) {
            $this->info("Creating mailbox: {$mailbox}");
            //TODO set specialuse;
            if (!$imap->createFolder($mailbox)) {
                $this->error("Failed to create mailbox: {$mailbox}");
                return null;
            }
        } elseif ($count) {
            if ($clearTarget) {
                $this->info("Clearing mailbox: {$mailbox}");
                if (!$imap->clearFolder($mailbox)) {
                    $this->error("Error while clearing mailbox: {$mailbox}");
                }
            }
        }

        if ($folderType = $this->getFolderType($type)) {
            $this->info("Setting folder type annotation: {$folderType}");
            if (!$imap->setMetadata($mailbox, ["/private/vendor/kolab/folder-type" => $folderType])) {
                $this->error("Failed to set metadata on: {$mailbox}");
                return null;
            }
        }

        if ($subscribe) {
            $this->info("Subscribing to folder");
            $imap->subscribe($mailbox);
        }

        $davPrefix = null;
        if ($type == "calendar") {
            $davPrefix = "calendars";
        } elseif ($type == "addressbook") {
            $davPrefix = "addressbooks";
        }
        $retries = 0;
        while ($retries < 3) {
            if ($davPrefix) {
                if ($res = $this->findDavMailbox($davUrl . "/{$davPrefix}/{$username}/", $username, $password, $mailbox)) {
                    return $res;
                }
            } else {
                if ($imap->select($mailbox)) {
                    return $mailbox;
                }
            }
            sleep(5);
            $retries++;
        }
        return null;
    }


    private function importPst($file, $extractOnly, $username, $password, $clearTarget, $subscribe, $mailboxBlacklist, $mailboxWhitelist, $pickupFrom, $debug, $davUrl, $imapUrl, $typeFilter, $typeBlacklist): int
    {
        if (!file_exists($file)) {
            //TODO downlaod file (see Utils.php)
            $this->error("File doesn't exist: " . $file);
            return 1;
        }

        $dir = $this->createTemporaryDirectory();
        if (!$dir) {
            $this->error("Failed to create a temporary directory");
            return 1;
        }

        $this->info("Importing pst: " . $file);
        $this->info("Using temporary directory: " . $dir);

        $debugArg = "";
        if ($debug) {
            $debugArg = "-d pstdebugoutput.txt -L 2";
        }

        // Extract the pst file
        if (!exec("readpst $debugArg -e {$file} -o {$dir}", $output, $resultCode)) {
            $this->error("Failed to extract pst data");
            return 1;
        }

        $this->info("Extracted pst file: \n" . implode("\n  ", $output) . "\n");

        $outputDirectories = self::listDirectoryTree($dir);
        $directoryRoot = array_shift($outputDirectories);
        $directoryRootDepth = substr_count($directoryRoot, '/');
        $this->info(" Directory root: {$directoryRoot}");
        $this->info(" Depth: {$directoryRootDepth}");
        $this->info(" Directories: \n" . var_export($outputDirectories, true) . "\n");

        if ($extractOnly) {
            return 0;
        }

        // Prepare imap connection
        $imap = $this->initIMAP(self::getConfig($imapUrl, $username, $password, false, false), false, $debug);
        if (!$imap) {
            $this->error("Failed to connect to imap.");
            return 1;
        }

        $this->info("Logged in to imap as user: {$username}");

        $hierarchyDelimiter = $imap->getHierarchyDelimiter();
        $this->info("Hierarchy delimiter: {$hierarchyDelimiter}");

        //TODO config option
        $importRoot = '';

        $totalFolders = count($outputDirectories);
        $folderCounter = 0;

        $skipBeforePickupfromFolder = !empty($pickupFrom);

        foreach ($outputDirectories as $directory) {
            $this->info("");
            $mailbox = $importRoot . self::mailboxFromDirectory($directory, $directoryRootDepth, $hierarchyDelimiter);
            $folderCounter++;

            if (
                (empty($mailboxWhitelist) && self::inBlacklist($mailbox, $mailboxBlacklist)) ||
                (!empty($mailboxWhitelist) && !self::inBlacklist($mailbox, $mailboxWhitelist))
            ) {
                $this->info("Skipping blacklisted mailbox: {$mailbox}");
                continue;
            }

            if ($skipBeforePickupfromFolder) {
                if ($mailbox == $pickupFrom) {
                    $this->info("Picking up from mailbox: {$mailbox}");
                    $skipBeforePickupfromFolder = false;
                } else {
                    $this->info("Skipping before pickup-from mailbox: {$mailbox}");
                    continue;
                }
            }

            $this->info("Importing folder: {$directory} to mailbox: {$mailbox}");
            $this->info("Folder {$folderCounter} out of {$totalFolders}");

            $type = "mail";

            //TODO exclude folders
            $files = glob("{$directory}/*.ics");
            $fileCount = count($files);
            if ($fileCount) {
                $type = "calendar";
            } else {
                $files = glob("{$directory}/*.vcf");
                $fileCount = count($files);
                if ($fileCount) {
                    $type = "addressbook";
                }
            }

            //Filter folders that match the file naming scheme (e.g. a calendar with a .ics)
            $files = array_filter(
                $files,
                function ($file) {
                    return !is_dir($file);
                }
            );

            $this->info("Folder type is {$type}");

            // This can happen if there e.g. is a calendar and mail folder of the same name.
            // We prefer the calendar/addressbook and ignore the mail in this case.
            if ($type != "mail") {
                $_files = glob("{$directory}/*.eml");
                $_fileCount = count($_files);
                if ($_fileCount) {
                    $this->info("Warning: there are {$_fileCount} .eml files in your {$type} folder that are not imported.");
                }
            } else {
                $files = glob("{$directory}/*.eml");
                $fileCount = count($files);
            }

            if ($typeFilter && $typeFilter != $type) {
                $this->info("Skipping due to type filter");
                continue;
            }

            if ($typeBlacklist && $typeBlacklist == $type) {
                $this->info("Skipping due to type blacklist");
                continue;
            }

            if (!$fileCount) {
                $this->info("Nothing to do");
                continue;
            }

            $mailbox = $this->createFolder($imap, $mailbox, $type, $clearTarget, $subscribe, $davUrl, $username, $password);
            if (!$mailbox) {
                $this->error("Failed to get the target mailbox.");
                return 1;
            }

            $bar = self::createProgressBar($this->output, $fileCount, "Importing to " . $mailbox);
            $bar->advance();
            foreach ($files as $file) {
                /* $this->info("Processing file: {$file}"); */

                if ($type == "calendar") {
                    if (!self::caldavAppendFromFile($davUrl, $username, $password, $mailbox, $file)) {
                        $this->error("Append failed: {$file}");
                        $this->error("IMAP error: {$imap->error}");
                        return 1;
                    }
                } elseif ($type == "addressbook") {
                    if (!self::carddavAppendFromFile($davUrl, $username, $password, $mailbox, $file)) {
                        $this->error("Append failed: {$file}");
                        $this->error("IMAP error: {$imap->error}");
                        return 1;
                    }
                } else {
                    if (!self::appendFromFile($imap, $mailbox, $file)) {
                        $this->error("Append failed: {$file}");
                        $this->error("IMAP error: {$imap->error}");

                        //Whitelist non fatal errors
                        if (strpos($imap->error, "Message contains invalid header") != false) {
                            continue;
                        }

                        return 1;
                    }
                }
                // TODO If the work above did not involve imap (e.g. caldav import) and took too long,
                // it's possible that the imap connection times out and then is invalid in closeConnection() below,
                // or for another imap import. Check connection and reestablish if necessary.
                $bar->advance();
            }
            $bar->finish();
        }
        $imap->closeConnection();

        $this->info("Finished importing folders.");
        return 0;
    }
}
