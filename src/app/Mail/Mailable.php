<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable as IMailable;
use Illuminate\Queue\SerializesModels;

class Mailable extends IMailable
{
    use Queueable;
    use SerializesModels;

    /** @var ?User User context */
    protected $user;

    /**
     * Returns the user object of an email main recipient.
     *
     * @return ?User User object if set
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Returns the mail subject.
     *
     * @return string Mail subject
     */
    public function getSubject(): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        // Subject property is not available before build() method was called
        // i.e. before Mail::send().
        // It's also not available when using Mail::fake().
        // This is essentially why we have getSubject() method.

        $class = class_basename(static::class);

        if ($this->user) {
            $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');
        } else {
            $appName = \config('app.name');
        }

        return \trans('mail.' . strtolower($class) . '-subject', ['site' => $appName]);
    }
}
