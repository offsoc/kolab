<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Iatstuti\Database\Support\NullableFields;
use Carbon\Carbon;

/**
 * The eloquent definition of an AuthAttempt.
 *
 * An AuthAttempt represents an authenticaton attempt from an application/client.
 */
class AuthAttempt extends Model
{
    use NullableFields;

    // No specific reason
    public const REASON_NONE = '';
    // Password mismatch
    public const REASON_PASSWORD = 'password';
    // Geolocation whitelist mismatch
    public const REASON_GEOLOCATION = 'geolocation';

    private const STATUS_ACCEPTED  = 'ACCEPTED';
    private const STATUS_DENIED  = 'DENIED';

    protected $nullable = [
        'reason',
    ];

    protected $fillable = [
        'ip',
        'user_id',
        'status',
        'reason',
        'expires_at',
        'last_seen',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_seen' => 'datetime'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
    * Prepare a date for array / JSON serialization.
    *
    * Required to not omit timezone and match the format of update_at/created_at timestamps.
    *
    * @param  \DateTimeInterface  $date
    * @return string
    */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return Carbon::instance($date)->toIso8601ZuluString('microseconds');
    }

    /**
    * Returns true if the authentication attempt is accepted.
    *
    * @return bool
    */
    public function isAccepted(): bool
    {
        if ($this->status == self::STATUS_ACCEPTED && Carbon::now() < $this->expires_at) {
            return true;
        }
        return false;
    }

    /**
    * Returns true if the authentication attempt is denied.
    *
    * @return bool
    */
    public function isDenied(): bool
    {
        return ($this->status == self::STATUS_DENIED);
    }

    /**
    * Accept the authentication attempt.
    */
    public function accept($reason = AuthAttempt::REASON_NONE)
    {
        $this->expires_at = Carbon::now()->addHours(8);
        $this->status = self::STATUS_ACCEPTED;
        $this->reason = $reason;
        $this->save();
    }

    /**
    * Deny the authentication attempt.
    */
    public function deny($reason = AuthAttempt::REASON_NONE)
    {
        $this->status = self::STATUS_DENIED;
        $this->reason = $reason;
        $this->save();
    }

    /**
    * Notify the user of this authentication attempt.
    *
    * @return bool false if there was no means to notify
    */
    public function notify(): bool
    {
        return \App\CompanionApp::notifyUser($this->user_id, ['token' => $this->id]);
    }

    /**
    * Notify the user and wait for a confirmation.
    */
    private function notifyAndWait()
    {
        if (!$this->notify()) {
            //FIXME if the webclient can confirm too we don't need to abort here.
            \Log::warning("There is no 2fa device to notify.");
            return false;
        }

        \Log::debug("Authentication attempt: {$this->id}");

        $confirmationTimeout = 120;
        $timeout = Carbon::now()->addSeconds($confirmationTimeout);

        do {
            if ($this->isDenied()) {
                \Log::debug("The authentication attempt was denied {$this->id}");
                return false;
            }

            if ($this->isAccepted()) {
                \Log::debug("The authentication attempt was accepted {$this->id}");
                return true;
            }

            if ($timeout < Carbon::now()) {
                \Log::debug("The authentication attempt timed-out: {$this->id}");
                return false;
            }

            sleep(2);
            $this->refresh();
        } while (true);
    }

    /**
    * Record a new authentication attempt or update an existing one.
    *
    * @param \App\User $user     The user attempting to authenticate.
    * @param string    $clientIP The ip the authentication attempt is coming from.
    *
    * @return \App\AuthAttempt
    */
    public static function recordAuthAttempt(\App\User $user, $clientIP)
    {
        $authAttempt = \App\AuthAttempt::where('ip', $clientIP)->where('user_id', $user->id)->first();

        if (!$authAttempt) {
            $authAttempt = new \App\AuthAttempt();
            $authAttempt->ip = $clientIP;
            $authAttempt->user_id = $user->id;
        }

        $authAttempt->last_seen = Carbon::now();
        $authAttempt->save();

        return $authAttempt;
    }

    /**
    * Trigger a notification if necessary and wait for confirmation.
    *
    * @return bool Returns true if the attempt is accepted on confirmation
    */
    public function waitFor2FA(): bool
    {
        if ($this->isAccepted()) {
            return true;
        }
        if ($this->isDenied()) {
            return false;
        }

        if (!$this->notifyAndWait()) {
            return false;
        }

        return $this->isAccepted();
    }
}
