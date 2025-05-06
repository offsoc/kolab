<?php

namespace Tests\Feature\Documents;

use App\Documents\Receipt;
use App\Payment;
use App\User;
use App\VatRate;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Payment::query()->delete();
        VatRate::query()->delete();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser('receipt-test@kolabnow.com');

        Payment::query()->delete();
        VatRate::query()->delete();

        parent::tearDown();
    }

    /**
     * Test receipt HTML output (without VAT)
     */
    public function testHtmlOutput(): void
    {
        $appName = \config('app.name');
        $wallet = $this->getTestData();
        $receipt = new Receipt($wallet, 2020, 5);
        $html = $receipt->htmlOutput();

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($html);

        // Title
        $title = $dom->getElementById('title');
        $this->assertSame("Receipt for May 2020", $title->textContent);

        // Company name/address
        $header = $dom->getElementById('header');
        $companyOutput = $this->getNodeContent($header->getElementsByTagName('td')[0]);
        $companyExpected = \config('app.company.name') . "\n" . \config('app.company.address');
        $this->assertSame($companyExpected, $companyOutput);

        // The main table content
        $content = $dom->getElementById('content');
        $records = $content->getElementsByTagName('tr');
        $this->assertCount(7, $records);

        $headerCells = $records[0]->getElementsByTagName('th');
        $this->assertCount(3, $headerCells);
        $this->assertSame('Date', $this->getNodeContent($headerCells[0]));
        $this->assertSame('Description', $this->getNodeContent($headerCells[1]));
        $this->assertSame('Amount', $this->getNodeContent($headerCells[2]));
        $cells = $records[1]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-01', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('12,34 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[2]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-10', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('0,01 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[3]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-21', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('1,00 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[4]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-30', $this->getNodeContent($cells[0]));
        $this->assertSame("Refund", $this->getNodeContent($cells[1]));
        $this->assertSame('-1,00 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[5]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-31', $this->getNodeContent($cells[0]));
        $this->assertSame("Chargeback", $this->getNodeContent($cells[1]));
        $this->assertSame('-0,10 CHF', $this->getNodeContent($cells[2]));
        $summaryCells = $records[6]->getElementsByTagName('td');
        $this->assertCount(2, $summaryCells);
        $this->assertSame('Total', $this->getNodeContent($summaryCells[0]));
        $this->assertSame('12,25 CHF', $this->getNodeContent($summaryCells[1]));

        // Customer data
        $customer = $dom->getElementById('customer');
        $customerCells = $customer->getElementsByTagName('td');
        $customerOutput = $this->getNodeContent($customerCells[0]);
        $customerExpected = "Firstname Lastname\nTest Unicode Straße 150\n10115 Berlin";
        $this->assertSame($customerExpected, $this->getNodeContent($customerCells[0]));
        $customerIdents = $this->getNodeContent($customerCells[1]);
        // $this->assertTrue(strpos($customerIdents, "Account ID {$wallet->id}") !== false);
        $this->assertTrue(str_contains($customerIdents, "Customer No. {$wallet->owner->id}"));

        // Company details in the footer
        $footer = $dom->getElementById('footer');
        $footerOutput = $footer->textContent;
        $this->assertStringStartsWith(\config('app.company.details'), $footerOutput);
        $this->assertTrue(str_contains($footerOutput, \config('app.company.email')));
    }

    /**
     * Test receipt HTML output (with VAT)
     */
    public function testHtmlOutputVat(): void
    {
        $appName = \config('app.name');
        $wallet = $this->getTestData('CH');
        $receipt = new Receipt($wallet, 2020, 5);
        $html = $receipt->htmlOutput();

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML($html);

        // The main table content
        $content = $dom->getElementById('content');
        $records = $content->getElementsByTagName('tr');
        $this->assertCount(9, $records);

        $cells = $records[1]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-01', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('11,39 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[2]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-10', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('0,01 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[3]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-21', $this->getNodeContent($cells[0]));
        $this->assertSame("{$appName} Services", $this->getNodeContent($cells[1]));
        $this->assertSame('0,92 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[4]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-30', $this->getNodeContent($cells[0]));
        $this->assertSame("Refund", $this->getNodeContent($cells[1]));
        $this->assertSame('-0,92 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[5]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-31', $this->getNodeContent($cells[0]));
        $this->assertSame("Chargeback", $this->getNodeContent($cells[1]));
        $this->assertSame('-0,09 CHF', $this->getNodeContent($cells[2]));
        $subtotalCells = $records[6]->getElementsByTagName('td');
        $this->assertCount(2, $subtotalCells);
        $this->assertSame('Subtotal', $this->getNodeContent($subtotalCells[0]));
        $this->assertSame('11,31 CHF', $this->getNodeContent($subtotalCells[1]));
        $vatCells = $records[7]->getElementsByTagName('td');
        $this->assertCount(2, $vatCells);
        $this->assertSame('VAT (7.7%)', $this->getNodeContent($vatCells[0]));
        $this->assertSame('0,94 CHF', $this->getNodeContent($vatCells[1]));
        $totalCells = $records[8]->getElementsByTagName('td');
        $this->assertCount(2, $totalCells);
        $this->assertSame('Total', $this->getNodeContent($totalCells[0]));
        $this->assertSame('12,25 CHF', $this->getNodeContent($totalCells[1]));
    }

    /**
     * Test receipt PDF output
     */
    public function testPdfOutput(): void
    {
        $wallet = $this->getTestData();
        $receipt = new Receipt($wallet, 2020, 5);
        $pdf = $receipt->PdfOutput();

        $this->assertStringStartsWith("%PDF-1.", $pdf);
        $this->assertTrue(strlen($pdf) > 2000);

        // TODO: Test the content somehow
    }

    /**
     * Prepare data for a test
     *
     * @param string $country User country code
     */
    protected function getTestData(?string $country = null): Wallet
    {
        Bus::fake();

        $user = $this->getTestUser('receipt-test@kolabnow.com');
        $user->setSettings([
            'first_name' => 'Firstname',
            'last_name' => 'Lastname',
            'billing_address' => "Test Unicode Straße 150\n10115 Berlin",
            'country' => $country,
        ]);

        $wallet = $user->wallets()->first();

        $vat = null;
        if ($country) {
            $vat = VatRate::create([
                'country' => $country,
                'rate' => 7.7,
                'start' => now(),
            ])->id;
        }

        // Create two payments out of the 2020-05 period
        // and three in it, plus one in the period but unpaid,
        // and one with amount 0, and an extra refund and chanrgeback

        $payment = Payment::create([
            'id' => 'AAA1',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Paid in April',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1111,
            'credit_amount' => 1111,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 1111,
        ]);
        $payment->updated_at = Carbon::create(2020, 4, 30, 12, 0, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'AAA2',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Paid in June',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 2222,
            'credit_amount' => 2222,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 2222,
        ]);
        $payment->updated_at = Carbon::create(2020, 6, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'AAA3',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Auto-Payment Setup',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 0,
            'credit_amount' => 0,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 0,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'AAA4',
            'status' => Payment::STATUS_OPEN,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Payment not yet paid',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 990,
            'credit_amount' => 990,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 990,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        // ... so we expect the five three on the receipt
        $payment = Payment::create([
            'id' => 'AAA5',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Payment OK',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1234,
            'credit_amount' => 1234,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 1234,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'AAA6',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_ONEOFF,
            'description' => 'Payment OK',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 1,
            'credit_amount' => 1,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 1,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 10, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'AAA7',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_RECURRING,
            'description' => 'Payment OK',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => 100,
            'credit_amount' => 100,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => 100,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 21, 23, 59, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'ref1',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_REFUND,
            'description' => 'refund desc',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => -100,
            'credit_amount' => -100,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => -100,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 30, 23, 59, 0);
        $payment->save();

        $payment = Payment::create([
            'id' => 'chback1',
            'status' => Payment::STATUS_PAID,
            'type' => Payment::TYPE_CHARGEBACK,
            'description' => '',
            'wallet_id' => $wallet->id,
            'provider' => 'stripe',
            'amount' => -10,
            'credit_amount' => -10,
            'vat_rate_id' => $vat,
            'currency' => 'CHF',
            'currency_amount' => -10,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 31, 23, 59, 0);
        $payment->save();

        // Make sure some config is set so we can test it's put into the receipt
        if (empty(\config('app.company.name'))) {
            \config(['app.company.name' => 'Company Co.']);
        }
        if (empty(\config('app.company.email'))) {
            \config(['app.company.email' => 'email@domina.tld']);
        }
        if (empty(\config('app.company.details'))) {
            \config(['app.company.details' => 'VAT No. 123456789']);
        }
        if (empty(\config('app.company.address'))) {
            \config(['app.company.address' => "Test Street 12\n12345 Some Place"]);
        }

        return $wallet;
    }

    /**
     * Extract text from a HTML element replacing <br> with \n
     *
     * @param \DOMElement $node The HTML element
     *
     * @return string The content
     */
    protected function getNodeContent(\DOMElement $node)
    {
        $content = [];
        foreach ($node->childNodes as $child) {
            if ($child->nodeName == 'br') {
                $content[] = "\n";
            } else {
                $content[] = $child->textContent;
            }
        }

        return trim(implode('', $content));
    }
}
