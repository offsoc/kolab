<?php

namespace Tests\Feature\Console\Contact;

use App\Contact;
use Tests\TestCase;

class ImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Contact::truncate();
    }

    protected function tearDown(): void
    {
        Contact::truncate();

        parent::tearDown();
    }

    /**
     * Test input checking
     */
    public function testHandle(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $path = self::BASE_DIR . '/data';

        // Non-existing user
        $code = \Artisan::call("contact:import unknown@unknown.org {$path}/contacts.csv");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("User not found.", $output);

        // Non-existing file
        $code = \Artisan::call("contact:import {$user->email} {$path}/non-existing.csv");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("File '{$path}/non-existing.csv' does not exist.", $output);

        // Empty file
        $code = \Artisan::call("contact:import {$user->email} {$path}/empty.csv");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("File '{$path}/empty.csv' is empty.", $output);

        // Unsupported file type
        $code = \Artisan::call("contact:import {$user->email} {$path}/takeout.zip");
        $output = trim(\Artisan::output());
        $this->assertSame(1, $code);
        $this->assertSame("Unsupported file type.", $output);
    }

    /**
     * Test importing from a CSV file
     */
    public function testHandleCsv(): void
    {
        $user = $this->getTestUser('ned@kolab.org');
        $path = self::BASE_DIR . '/data';

        // Test a proper csv file
        $code = \Artisan::call("contact:import {$user->email} {$path}/contacts.csv");
        $output = trim(\Artisan::output());
        $this->assertSame(0, $code);
        $this->assertStringContainsString("DONE", $output);

        $contacts = $user->contacts()->orderBy('email')->get();
        $this->assertCount(2, $contacts);
        $this->assertSame('contact1@test.com', $contacts[0]->email);
        $this->assertSame('Contact1', $contacts[0]->name);
        $this->assertSame('contact2@test.com', $contacts[1]->email);
        $this->assertSame('Contact2', $contacts[1]->name);

        // TODO: Test only-emails case (data/email.csv)
    }
}
