<?php

namespace App\Mail;

use App\Tenant;
use App\User;
use App\Utils;
use App\Wallet;

class DegradedAccountReminder extends Mailable
{
    /** @var Wallet A wallet with a negative balance */
    protected $wallet;

    /**
     * Create a new message instance.
     *
     * @param Wallet $wallet A wallet
     * @param User   $user   A wallet controller to whom the email is being sent
     */
    public function __construct(Wallet $wallet, User $user)
    {
        $this->wallet = $wallet;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');
        $supportUrl = Tenant::getConfig($this->user->tenant_id, 'app.support_url');

        $vars = [
            'email' => $this->wallet->owner->email,
            'name' => $this->user->name(true),
            'site' => $appName,
        ];

        $this->view('emails.html.degraded_account_reminder')
            ->text('emails.plain.degraded_account_reminder')
            ->subject(\trans('mail.degradedaccountreminder-subject', $vars))
            ->with([
                'vars' => $vars,
                'supportUrl' => Utils::serviceUrl($supportUrl, $this->user->tenant_id),
                'walletUrl' => Utils::serviceUrl('/wallet', $this->user->tenant_id),
                'dashboardUrl' => Utils::serviceUrl('/dashboard', $this->user->tenant_id),
            ]);

        return $this;
    }

    /**
     * Render the mail template with fake data
     *
     * @param string $type Output format ('html' or 'text')
     *
     * @return string HTML or Plain Text output
     */
    public static function fakeRender(string $type = 'html'): string
    {
        $wallet = new Wallet();
        $user = new User();
        $user->email = 'test@' . \config('app.domain');
        $wallet->owner = $user;

        $mail = new self($wallet, $user);

        return Helper::render($mail, $type);
    }
}
