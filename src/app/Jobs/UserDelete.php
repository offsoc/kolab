<?php

namespace App\Jobs;

use App\Backends\LDAP;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UserDelete implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $user;

    public $tries = 5;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param int $user_id The ID of the user to delete.
     *
     * @return void
     */
    public function __construct(int $user_id)
    {
        $this->user = User::withTrashed()->find($user_id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->user->isDeleted()) {
            LDAP::deleteUser($this->user);

            $this->user->status |= User::STATUS_DELETED;
            $this->user->save();
        }
    }
}
