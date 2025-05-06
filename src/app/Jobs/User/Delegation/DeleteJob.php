<?php

namespace App\Jobs\User\Delegation;

use App\Delegation;
use App\Jobs\CommonJob;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\Roundcube;
use App\User;

class DeleteJob extends CommonJob
{
    /** @var string Delegator's email address */
    protected $delegatorEmail;

    /** @var string Delegatee's email address */
    protected $delegateeEmail;

    /**
     * Create a new job instance.
     *
     * @param string $delegatorEmail delegator's email address
     * @param string $delegateeEmail delegatee's email address
     */
    public function __construct(string $delegatorEmail, string $delegateeEmail)
    {
        // Note: We're using email not id because the user may not exists anymore
        $this->delegatorEmail = $delegatorEmail;
        $this->delegateeEmail = $delegateeEmail;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->logJobStart("{$this->delegatorEmail}/{$this->delegateeEmail}");

        $this->delegationCleanup($this->delegatorEmail, $this->delegateeEmail);
    }

    /**
     * Cleanup delegation relation between two users
     */
    public static function delegationCleanup(string $delegator_email, string $delegatee_email)
    {
        $delegator = User::where('email', $delegator_email)->withTrashed()->first();
        $delegatee = User::where('email', $delegatee_email)->withTrashed()->first();

        // Make sure that the same delegation wasn't re-created in meantime
        if ($delegator && $delegatee && $delegator->delegatees()->where('delegatee_id', $delegatee->id)->exists()) {
            return;
        }

        // Remove identities
        if ($delegatee && !$delegatee->isDeleted() && \config('database.connections.roundcube')) {
            Roundcube::resetIdentities($delegatee);
        }

        // Unsubscribe folders shared by the delegator
        if ($delegatee && $delegatee->isImapReady()) {
            if (!IMAP::unsubscribeSharedFolders($delegatee, $delegator_email)) {
                throw new \Exception("Failed to unsubscribe IMAP folders for user {$delegatee_email}.");
            }

            DAV::unsubscribeSharedFolders($delegatee, $delegator_email);
        }

        // Remove folder permissions for the delegatee
        if ($delegator && $delegator->isImapReady()) {
            if (!IMAP::unshareFolders($delegator, $delegatee_email)) {
                throw new \Exception("Failed to unshare IMAP folders for user {$delegator_email}.");
            }

            DAV::unshareFolders($delegator, $delegatee_email);
        }
    }
}
