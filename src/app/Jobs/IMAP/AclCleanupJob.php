<?php

namespace App\Jobs\IMAP;

use App\Jobs\CommonJob;

/**
 * Remove ACL for a specified user/group anywhere in IMAP
 */
class AclCleanupJob extends CommonJob
{
    /**
     * The ACL identifier
     *
     * @var string
     */
    protected $ident;

    /**
     * The ACL subject domain
     *
     * @var string
     */
    protected $domain;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60 * 60;


    /**
     * Create a new job instance.
     *
     * @param string $ident  ACL identifier
     * @param string $domain ACL domain
     *
     * @return void
     */
    public function __construct(string $ident, string $domain = '')
    {
        $this->ident = $ident;
        $this->domain = $domain;
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
        \App\Backends\IMAP::aclCleanup($this->ident, $this->domain);
    }
}
