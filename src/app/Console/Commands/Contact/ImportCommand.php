<?php

namespace App\Console\Commands\Contact;

use App\Console\Command;
use App\Contact;

class ImportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contact:import {user} {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import user contacts (global addressbook) from a file.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();

        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        // TODO: We should not allow an import for non-owner users
        //       or find the account owner and use it instead

        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File '{$file}' does not exist.");
            return 1;
        }

        $header = file_get_contents($file, false, null, 0, 1024);

        if ($header === false) {
            $this->error("Failed to read file '{$file}'.");
            return 1;
        }

        if (!strlen($header)) {
            $this->error("File '{$file}' is empty.");
            return 1;
        }

        if (!($type = $this->getFileType($header))) {
            $this->error("Unsupported file type.");
            return 1;
        }

        $contacts = $this->getContactsFromFile($file, $type);

        if (empty($contacts)) {
            $this->error("No contacts found in the file.");
            return 1;
        }

        $bar = $this->createProgressBar(count($contacts), "Importing contacts");

        // TODO: An option to remove all existing contacts
        // TODO: Will this use too much memory for a huge addressbook?
        $existing = $user->contacts()->get()->keyBy('email')->all();

        foreach ($contacts as $contact) {
            $exists = $existing[$contact->email] ?? null;

            if (!$exists) {
                $contact->user_id = $user->id;
                $contact->save();
                $existing[$contact->email] = $contact;
            } else {
                $exists->name = $contact->name;
                $exists->save();
            }

            $bar->advance();
        }

        $bar->finish();

        $this->info("DONE");
    }

    /**
     * Parse the input file
     *
     * @param string $file File location
     * @param string $type File type (format)
     *
     * @return array<Contact>
     */
    protected function getContactsFromFile(string $file, string $type): array
    {
        switch ($type) {
            case 'csv':
                return $this->parseCsvFile($file);
        }

        return [];
    }

    /**
     * Recognize file type by parsing a chunk of the content from the file start
     *
     * @param string $header File content
     */
    protected function getFileType(string $header): ?string
    {
        [$line] = preg_split('/\r?\n/', $header);

        // TODO: vCard, LDIF

        // Is this CSV format?
        $arr = str_getcsv($line, ",", "\"", "\\");
        if (count($arr) > 1) {
            // We expect first line to contain data in supported order or field names
            if (
                ($arr[0] == 'Email' && $arr[1] == 'Name')
                || in_array('Email', $arr)
                || str_contains($arr[0], '@')
            ) {
                return 'csv';
            }
        }

        return null;
    }

    /**
     * Validate contact data
     */
    protected function isValid(Contact $contact): bool
    {
        // Validate email address
        // Validate (truncate) display name

        return true;
    }

    /**
     * Parse a CSV file
     *
     * @param string $file File location
     *
     * @return array<Contact>
     */
    protected function parseCsvFile(string $file): array
    {
        $handle = fopen($file, 'r');
        if ($handle === false) {
            return [];
        }

        $fields = null;
        $contacts = [];
        $all_props = ['email', 'name'];

        while (($data = fgetcsv($handle, 4096, ",", "\"", "\\")) !== false) {
            if ($fields === null) {
                $filtered = array_filter(
                    array_map('strtolower', $data),
                    static fn ($h) => in_array($h, $all_props)
                );

                if (count($filtered) == 1 || count($filtered) == 2) {
                    foreach ($all_props as $prop) {
                        if (($idx = array_search($prop, $filtered)) !== false) {
                            $fields[$prop] = $idx;
                        }
                    }

                    continue;
                }
                $fields = ['email' => 0, 'name' => 1];
            }

            $contact = new Contact();

            foreach ($all_props as $prop) {
                if (isset($fields[$prop]) && isset($data[$fields[$prop]])) {
                    $contact->{$prop} = $data[$fields[$prop]];
                }
            }

            if ($this->isValid($contact)) {
                $contacts[] = $contact;
            }
        }

        fclose($handle);

        return $contacts;
    }
}
