<?php

namespace App\Jobs;

/**
 * The abstract \App\Jobs\DomainJob implements the logic needed for all dispatchable Jobs related to
 * \App\Domain objects.
 *
 * ```php
 * $job = new \App\Jobs\Domain\CreateJob($domainId);
 * $job->handle();
 * ```
 */
abstract class DomainJob extends CommonJob
{
    /**
     * The ID for the \App\Domain. This is the shortest globally unique identifier and saves Redis space
     * compared to a serialized version of the complete \App\Domain object.
     *
     * @var int
     */
    protected $domainId;

    /**
     * The \App\Domain namespace property, for legibility in the queue management.
     *
     * @var string
     */
    protected $domainNamespace;

    /**
     * Create a new job instance.
     *
     * @param int $domainId The ID for the domain to create.
     *
     * @return void
     */
    public function __construct(int $domainId)
    {
        $this->domainId = $domainId;

        $domain = $this->getDomain();

        if ($domain) {
            $this->domainNamespace = $domain->namespace;
        }
    }

    /**
     * Get the \App\Domain entry associated with this job.
     *
     * @return \App\Domain|null
     *
     * @throws \Exception
     */
    protected function getDomain()
    {
        $domain = \App\Domain::withTrashed()->find($this->domainId);

        if (!$domain) {
            $this->fail(new \Exception("Domain {$this->domainId} could not be found in the database."));
        }

        return $domain;
    }
}
