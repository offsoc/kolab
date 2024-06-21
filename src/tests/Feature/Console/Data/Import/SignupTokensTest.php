<?php

namespace Tests\Feature\Console\Data\Import;

use App\Plan;
use App\SignupToken;
use Tests\TestCase;

class SignupTokensTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        Plan::where('title', 'test')->delete();
        SignupToken::truncate();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        Plan::where('title', 'test')->delete();
        SignupToken::truncate();

        @unlink(storage_path('test-tokens.txt'));

        parent::tearDown();
    }

    /**
     * Test the command
     */
    public function testHandle(): void
    {
        $file = storage_path('test-tokens.txt');
        file_put_contents($file, '');

        // Unknown plan
        $code = \Artisan::call("data:import:signup-tokens unknown {$file}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("Plan not found", $output);

        // Plan not for tokens
        $code = \Artisan::call("data:import:signup-tokens individual {$file}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("The plan is not for tokens", $output);

        $plan = Plan::create([
                'title' => 'test',
                'name' => 'Test Account',
                'description' => 'Test',
                'mode' => Plan::MODE_TOKEN,
        ]);

        // Non-existent input file
        $code = \Artisan::call("data:import:signup-tokens {$plan->title} nofile.txt");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("File 'nofile.txt' does not exist", $output);

        // Empty input file
        $code = \Artisan::call("data:import:signup-tokens {$plan->title} {$file}");
        $output = trim(\Artisan::output());

        $this->assertSame(1, $code);
        $this->assertSame("File '{$file}' is empty", $output);

        // Valid tokens
        file_put_contents($file, "12345\r\nabcde");
        $code = \Artisan::call("data:import:signup-tokens {$plan->id} {$file}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Validating tokens... DONE", $output);
        $this->assertStringContainsString("Importing tokens... DONE", $output);
        $this->assertSame(['12345', 'ABCDE'], $plan->signupTokens()->orderBy('id')->pluck('id')->all());

        // Attempt the same tokens again
        $code = \Artisan::call("data:import:signup-tokens {$plan->id} {$file}");
        $output = trim(\Artisan::output());

        $this->assertSame(0, $code);
        $this->assertStringContainsString("Validating tokens... DONE", $output);
        $this->assertStringContainsString("Nothing to import", $output);
        $this->assertStringNotContainsString("Importing tokens...", $output);
    }
}
