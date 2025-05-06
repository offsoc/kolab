<?php

namespace App\Console\Commands\Data\Reports;

use App\Mail\Helper;
use App\Mail\Mailable;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class VatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:reports:vat {email} {period?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VAT report';

    private $period = '';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $recipient = $this->argument('email');
        $period = $this->argument('period');

        // Note: Carbon constructor takes care of date, but startOfDay() takes care of time

        if (empty($period)) {
            // By default, report for the last month
            $start = (new Carbon('first day of last month'))->startOfDay();
            $end = $start->copy()->endOfMonth();
            $period = $start->format('Y-m');
        } elseif (preg_match('|^(\d{4})-(\d{2})$|', $period, $matches)) {
            // Report for specific month (YYYY-MM)
            $start = (new Carbon("{$period}-01"))->startOfDay();
            $end = $start->copy()->endOfMonth();
        } elseif (preg_match('|^(\d{4})$|', $period, $matches)) {
            // Report for specific year (YYYY)
            $start = (new Carbon("first day of January {$period}"))->startOfDay();
            $end = $start->copy()->endOfYear();
        } else {
            $this->error("Invalid 'period' format");
            exit(1);
        }

        $this->period = $period;

        $result = DB::select(
            <<<'SQL'
                SELECT
                    DATE_FORMAT(p.created_at, '%Y-%m-%d %H:%I') AS timestamp,
                    v.country AS country,
                    p.id AS payment_id,
                    ROUND((amount / 100), 2) AS income_gross,
                    ROUND(((amount - (amount / (100 + v.rate) * v.rate)) / 100), 2) AS income_net,
                    ROUND(((amount / (100 + v.rate) * v.rate) / 100), 2) AS income_vat
                FROM
                    payments p
                INNER JOIN vat_rates v
                    ON p.vat_rate_id = v.id
                INNER JOIN wallets w
                    ON p.wallet_id = w.id
                INNER JOIN user_settings us
                    ON w.user_id = us.user_id
                WHERE
                    p.status = 'paid'
                    AND us.`key` = 'country'
                    AND p.created_at >= ?
                    AND p.created_at <= ?
                ORDER BY timestamp, country
                SQL,
            [$start->toDateTimeString(), $end->toDateTimeString()]
        );

        $fp = fopen('php://memory', 'w');

        foreach ($result as $record) {
            fputcsv($fp, (array) $record);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        $this->sendMail($recipient, $csv);
    }

    /**
     * Sends an email message with csv file attached
     */
    protected function sendMail($recipient, $csv)
    {
        $plainBody = 'See the attached report!';
        $filename = "Report-{$this->period}.csv";

        $attachment = Attachment::fromData(static fn () => $csv, $filename)->withMime('text/csv');

        $mail = new Mailable();

        $mail->subject('VAT Report')
            // This hack allows as to use plain text body instead of a Laravel view
            ->text(new HtmlString($plainBody))
            ->to($recipient)
            ->attach($attachment);

        Helper::sendMail($mail);
    }
}
