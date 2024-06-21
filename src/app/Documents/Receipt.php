<?php

namespace App\Documents;

use App\Payment;
use App\User;
use App\Wallet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class Receipt
{
    /** @var \App\Wallet The wallet */
    protected $wallet;

    /** @var int Transactions date year */
    protected $year;

    /** @var int Transactions date month */
    protected $month;

    /** @var bool Enable fake data mode */
    protected static $fakeMode = false;


    /**
     * Document constructor.
     *
     * @param \App\Wallet $wallet A wallet containing transactions
     * @param int         $year   A year to list transactions from
     * @param int         $month  A month to list transactions from
     *
     * @return void
     */
    public function __construct(Wallet $wallet, int $year, int $month)
    {
        $this->wallet = $wallet;
        $this->year = $year;
        $this->month = $month;
    }

    /**
     * Render the mail template with fake data
     *
     * @param string $type Output format ('html' or 'pdf')
     *
     * @return string HTML or PDF output
     */
    public static function fakeRender(string $type = 'html'): string
    {
        $wallet = new Wallet(['currency' => 'CHF']);
        $wallet->id = \App\Utils::uuidStr();
        $wallet->owner = new User(['id' => 123456789]);

        $receipt = new self($wallet, (int) date('Y'), (int) date('n'));

        self::$fakeMode = true;

        if ($type == 'pdf') {
            return $receipt->pdfOutput();
        } elseif ($type !== 'html') {
            throw new \Exception("Unsupported output format");
        }

        return $receipt->htmlOutput();
    }

    /**
     * Render the receipt in HTML format.
     *
     * @return string HTML content
     */
    public function htmlOutput(): string
    {
        return $this->build()->render();
    }

    /**
     * Render the receipt in PDF format.
     *
     * @return string PDF content
     */
    public function pdfOutput(): string
    {
        // Parse ther HTML template
        $html = $this->build()->render();

        // Link fonts from public/fonts to storage/fonts so DomPdf can find them
        if (!is_link(storage_path('fonts/Roboto-Regular.ttf'))) {
            symlink(
                public_path('fonts/Roboto-Regular.ttf'),
                storage_path('fonts/Roboto-Regular.ttf')
            );
            symlink(
                public_path('fonts/Roboto-Bold.ttf'),
                storage_path('fonts/Roboto-Bold.ttf')
            );
        }

        // Fix font and image paths
        $html = str_replace('url(/fonts/', 'url(fonts/', $html);
        $html = str_replace('src="/', 'src="', $html);

        // TODO: The output file is about ~200KB, we could probably slim it down
        // by using separate font files with small subset of languages when
        // there are no Unicode characters used, e.g. only ASCII or Latin.

        // Load PDF generator
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    /**
     * Build the document
     *
     * @return \Illuminate\View\View The template object
     */
    protected function build()
    {
        $appName = \config('app.name');
        $start = Carbon::create($this->year, $this->month, 1, 0, 0, 0);
        $end = $start->copy()->endOfMonth();

        $month = \trans('documents.month' . intval($this->month));
        $title = \trans('documents.receipt-title', ['year' => $this->year, 'month' => $month]);
        $company = $this->companyData();

        if (self::$fakeMode) {
            $customer = [
                'id' => $this->wallet->owner->id,
                'wallet_id' => $this->wallet->id,
                'customer' => 'Freddie Krüger<br>7252 Westminster Lane<br>Forest Hills, NY 11375',
            ];

            $items = collect([
                (object) [
                    'amount' => 1234,
                    'updated_at' => $start->copy()->next(Carbon::MONDAY),
                ],
                (object) [
                    'amount' => 10000,
                    'updated_at' => $start->copy()->next()->next(),
                ],
                (object) [
                    'amount' => 1234,
                    'updated_at' => $start->copy()->next()->next()->next(Carbon::MONDAY),
                ],
                (object) [
                    'amount' => 99,
                    'updated_at' => $start->copy()->next()->next()->next(),
                ],
            ]);

            $items = $items->map(function ($payment) {
                $payment->vatRate = new \App\VatRate();
                $payment->vatRate->rate = 7.7;
                $payment->credit_amount = $payment->amount + round($payment->amount * $payment->vatRate->rate / 100);
                return $payment;
            });
        } else {
            $customer = $this->customerData();
            $items = $this->wallet->payments()
                ->where('status', Payment::STATUS_PAID)
                ->where('updated_at', '>=', $start)
                ->where('updated_at', '<', $end)
                ->where('amount', '<>', 0)
                ->orderBy('updated_at')
                ->get();
        }

        $vatRate = 0;
        $totalVat = 0;
        $total = 0; // excluding VAT

        $items = $items->map(function ($item) use (&$total, &$totalVat, &$vatRate, $appName) {
            $amount = $item->amount;

            if ($item->vatRate && $item->vatRate->rate > 0) {
                $vat = round($item->credit_amount * $item->vatRate->rate / 100);
                $amount -= $vat;
                $totalVat += $vat;
                $vatRate = $item->vatRate->rate; // TODO: Multiple rates
            }

            $total += $amount;

            $type = $item->type ?? null;

            if ($type == Payment::TYPE_REFUND) {
                $description = \trans('documents.receipt-refund');
            } elseif ($type == Payment::TYPE_CHARGEBACK) {
                $description = \trans('documents.receipt-chargeback');
            } else {
                $description = \trans('documents.receipt-item-desc', ['site' => $appName]);
            }

            return [
                'amount' => $this->wallet->money($amount),
                'description' => $description,
                'date' => $item->updated_at->toDateString(),
            ];
        });

        // Load the template
        $view = view('documents.receipt')
            ->with([
                    'site' => $appName,
                    'title' => $title,
                    'company' => $company,
                    'customer' => $customer,
                    'items' => $items,
                    'subTotal' => $this->wallet->money($total),
                    'total' => $this->wallet->money($total + $totalVat),
                    'totalVat' => $this->wallet->money($totalVat),
                    'vatRate' => preg_replace('/(\.00|0|\.)$/', '', sprintf('%.2F', $vatRate)),
                    'vat' => $vatRate > 0,
            ]);

        return $view;
    }

    /**
     * Prepare customer data for the template
     *
     * @return array Customer data for the template
     */
    protected function customerData(): array
    {
        $user = $this->wallet->owner;
        $name = $user->name();
        $settings = $user->getSettings(['organization', 'billing_address']);

        $customer = trim(($settings['organization'] ?: $name) . "\n" . $settings['billing_address']);
        $customer = str_replace("\n", '<br>', htmlentities($customer));

        return [
            'id' => $this->wallet->owner->id,
            'wallet_id' => $this->wallet->id,
            'customer' => $customer,
        ];
    }

    /**
     * Prepare company data for the template
     *
     * @return array Company data for the template
     */
    protected function companyData(): array
    {
        $header = \config('app.company.name') . "\n" . \config('app.company.address');
        $header = str_replace("\n", '<br>', htmlentities($header));

        $footerLineLength = 110;
        $footer = \config('app.company.details');
        $contact = \config('app.company.email');
        $logo = \config('app.company.logo');
        $theme = \config('app.theme');

        if ($contact) {
            $length = strlen($footer) + strlen($contact) + 3;
            $contact = htmlentities($contact);
            $footer .= ($length > $footerLineLength ? "\n" : ' | ')
                . sprintf('<a href="mailto:%s">%s</a>', $contact, $contact);
        }

        if ($logo && strpos($logo, '/') === false) {
            $logo = "/themes/$theme/images/$logo";
        }

        return [
            'logo' => $logo ? "<img src=\"$logo\" width=300>" : '',
            'header' => $header,
            'footer' => $footer,
        ];
    }
}
