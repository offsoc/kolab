<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Iatstuti\Database\Support\NullableFields;
use Carbon\Carbon;

/**
 * The eloquent definition of an AuthAttempt.
 *
 * A AuthAttempt is any a authAttempt from any application/client.
 */
class AuthAttempt extends Model
{
    use NullableFields;

    // Password mismatch
    public const REASON_PASSWORD   = 'password';
    // Geolocation not in whitelist
    public const REASON_GEOLOCATION   = 'geolocation';

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

    /**
    * Prepare a date for array / JSON serialization.
    *
    * Required to not omit timezone and match the format of update_at/created_at timestamps.
    *
    * @param  \DateTimeInterface  $date
    * @return string
    */
    protected function serializeDate(\DateTimeInterface $date)
    {
        return Carbon::instance($date)->toIso8601ZuluString('microseconds');
    }

    /**
    * Returns true if the authentication attempt is accepted.
    *
    * @return bool
    */
    public function isAccepted()
    {
        if ($this->status == 'ACCEPTED' && Carbon::now() < $this->expires_at) {
            return true;
        }
        return false;
    }

    /**
    * Returns true if the authentication attempt is denied.
    *
    * @return bool
    */
    public function isDenied()
    {
        return ($this->status == 'DENIED');
    }

    /**
    * Accept the authentication attempt.
    */
    public function accept()
    {
        $this->expires_at = Carbon::now()->addHours(8);
        $this->status = "ACCEPTED";
        $this->reason = '';
    }

    /**
    * Deny the authentication attempt.
    */
    public function deny()
    {
        $this->status = "DENIED";
        $this->reason = '';
    }

    /**
    * Notify the user of this authentication attempt.
    *
    * @return bool false if there was no means to notify
    */
    public function notify()
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

        $authAttempt = $this;
        do {
            if ($authAttempt->isDenied()) {
                \Log::debug("The authentication attempt was denied {$authAttempt->id}");
                return false;
            }

            if ($authAttempt->isAccepted()) {
                \Log::debug("The authentication attempt was accepted {$authAttempt->id}");
                return true;
            }

            if ($timeout < Carbon::now()) {
                \Log::debug("The authentication attempt timed-out: {$authAttempt->id}");
                return false;
            }

            sleep(2);
            $authAttempt = $authAttempt->fresh();
        } while (true);
    }

    /**
    * Record an authentication attempt
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
    * @return bool
    */
    public function waitFor2FA()
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

        // Ensure the authAttempt is now accepted
        $freshAttempt = $this->fresh();
        return $freshAttempt->isAccepted();
    }
}
