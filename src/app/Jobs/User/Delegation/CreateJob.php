<?php

namespace App\Jobs\User\Delegation;

use App\Delegation;
use App\Jobs\UserJob;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\Roundcube;

class CreateJob extends UserJob
{
    /** @var int Delegation identifier */
    protected $delegationId;

    /**
     * Create a new job instance.
     *
     * @param int $delegationId the ID for the delegation to create
     */
    public function __construct(int $delegationId)
    {
        $this->delegationId = $delegationId;

        $delegation = Delegation::find($delegationId);

        if ($delegation->user) {
            $this->userId = $delegation->user->id;
            $this->userEmail = $delegation->user->email;
        }
    }

    /**
     * Execute the job.
     *
     * @throws \Exception
     */
    public function handle()
    {
        $this->logJobStart("{$this->delegationId} ({$this->userEmail})");

        $delegation = Delegation::find($this->delegationId);

        if (!$delegation || $delegation->isActive()) {
            return;
        }

        $user = $delegation->user;
        $delegatee = $delegation->delegatee;

        if (!$user || !$delegatee || $user->trashed() || $delegatee->trashed()) {
            return;
        }

        /*
        if (!$user->isImapReady() || !$delegatee->isImapReady()) {
            $this->release(60);
            return;
        }
        */

        // Create identities in Roundcube
        if (\config('database.connections.roundcube')) {
            Roundcube::createDelegatedIdentities($delegatee, $user);
        }

        // Share IMAP and DAV folders
        if (!IMAP::shareDefaultFolders($user, $delegatee, (array) $delegation->options)) {
            throw new \Exception("Failed to set IMAP delegation for user {$this->userEmail}.");
        }

        DAV::shareDefaultFolders($user, $delegatee, (array) $delegation->options);

        $delegation->status |= Delegation::STATUS_ACTIVE;
        $delegation->save();
    }
}
