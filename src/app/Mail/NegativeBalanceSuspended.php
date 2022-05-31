<?php

namespace App\Mail;

use App\Jobs\WalletCheck;
use App\Tenant;
use App\User;
use App\Utils;
use App\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NegativeBalanceSuspended extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var \App\Wallet A wallet with a negative balance */
    protected $wallet;

    /** @var \App\User A wallet controller to whom the email is being sent */
    protected $user;


    /**
     * Create a new message instance.
     *
     * @param \App\Wallet $wallet A wallet
     * @param \App\User   $user   An email recipient
     *
     * @return void
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
        $threshold = WalletCheck::threshold($this->wallet, WalletCheck::THRESHOLD_DELETE);
        $appName = Tenant::getConfig($this->user->tenant_id, 'app.name');
        $supportUrl = Tenant::getConfig($this->user->tenant_id, 'app.support_url');

        $subject = \trans('mail.negativebalancesuspended-subject', ['site' => $appName]);

        $this->view('emails.html.negative_balance_suspended')
            ->text('emails.plain.negative_balance_suspended')
            ->subject($subject)
            ->with([
                    'site' => $appName,
                    'subject' => $subject,
                    'username' => $this->user->name(true),
                    'supportUrl' => Utils::serviceUrl($supportUrl, $this->user->tenant_id),
                    'walletUrl' => Utils::serviceUrl('/wallet', $this->user->tenant_id),
                    'date' => $threshold->toDateString(),
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

        $mail = new self($wallet, $user);

        return Helper::render($mail, $type);
    }
}
