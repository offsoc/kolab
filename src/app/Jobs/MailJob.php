<?php

namespace App\Jobs;

use Illuminate\Queue\SerializesModels;

/**
 * An abstract class for all e-mailing jobs
 */
abstract class MailJob extends CommonJob
{
    use SerializesModels;

    /** @var int The number of times the job may be attempted. */
    public $tries = 3;

    /** @var int The number of seconds to wait before retrying the job. */
    public $backoff = 10;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    public const QUEUE = 'mail';
}
