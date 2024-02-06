<?php

namespace App\Console\Commands\Data\Reports;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\DB;

class VatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:reports:vat {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VAT report';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $recipient = $this->argument('email');

        // Note: Constructor takes care of date, but startOfDay()/endOfDay() takes care of time
        $start = (new Carbon('first day of last month'))->startOfDay();
        $end = (new Carbon('last day of last month'))->endOfDay();

        $result = DB::select(
            <<<SQL
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

        $attachment = Attachment::fromData(fn () => $csv, 'Report.csv')->withMime('text/csv');

        $mail = new \App\Mail\Mailable();

        $mail->subject('VAT Report')
            // This hack allows as to use plain text body instead of a Laravel view
            ->text(new \Illuminate\Support\HtmlString($plainBody))
            ->to($recipient)
            ->attach($attachment);

        \App\Mail\Helper::sendMail($mail);
    }
}
