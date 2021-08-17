<?php

namespace App\Jobs\PGP;

use App\Jobs\CommonJob;

/**
 * Remove the GPG key from the WOAT DNS system.
 */
class KeyUnregisterJob extends CommonJob
{
    /**
     * The email property.
     *
     * @var string
     */
    protected $email;

    /**
     * Create a new job instance.
     *
     * @param string $email User email address for the key
     *
     * @return void
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handle()
    {
        \App\Backends\PGP::keyUnregister($this->email);
    }
}
