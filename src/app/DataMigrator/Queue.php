<?php

namespace App\DataMigrator;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The eloquent definition of a DataMigratorQueue
 */
class Queue extends Model
{
    /** @var string Database table name */
    protected $table = 'data_migrator_queues';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = ['data' => 'array'];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'jobs_started' => 0,
        'jobs_finished' => 0,
        'data' => '', // must not be []
    ];


    /**
     * Fast and race-condition free method of bumping the jobs_started value
     */
    public function bumpJobsStarted(int $num = null)
    {
        DB::update(
            "update data_migrator_queues set jobs_started = jobs_started + ? where id = ?",
            [$num ?: 1, $this->id]
        );
    }

    /**
     * Fast and race-condition free method of bumping the jobs_finished value
     */
    public function bumpJobsFinished(int $num = null)
    {
        DB::update(
            "update data_migrator_queues set jobs_finished = jobs_finished + ? where id = ?",
            [$num ?: 1, $this->id]
        );
    }
}
