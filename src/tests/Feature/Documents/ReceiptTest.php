<?php

namespace Tests\Feature\Documents;

use App\Documents\Receipt;
use App\Payment;
use App\Providers\PaymentProvider;
use App\User;
use App\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('receipt-test@kolabnow.com');

        parent::tearDown();
    }

    /**
     * Test receipt HTML output
     *
     * @return void
     */
    public function testHtmlOutput()
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
        $this->assertCount(5, $records);

        $headerCells = $records[0]->getElementsByTagName('th');
        $this->assertCount(3, $headerCells);
        $this->assertSame('Date', $this->getNodeContent($headerCells[0]));
        $this->assertSame('Description', $this->getNodeContent($headerCells[1]));
        $this->assertSame('Amount', $this->getNodeContent($headerCells[2]));
        $cells = $records[1]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-01', $this->getNodeContent($cells[0]));
        $this->assertSame("$appName Services", $this->getNodeContent($cells[1]));
        $this->assertSame('12.34 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[2]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-10', $this->getNodeContent($cells[0]));
        $this->assertSame("$appName Services", $this->getNodeContent($cells[1]));
        $this->assertSame('0.01 CHF', $this->getNodeContent($cells[2]));
        $cells = $records[3]->getElementsByTagName('td');
        $this->assertCount(3, $cells);
        $this->assertSame('2020-05-31', $this->getNodeContent($cells[0]));
        $this->assertSame("$appName Services", $this->getNodeContent($cells[1]));
        $this->assertSame('1.00 CHF', $this->getNodeContent($cells[2]));
        $summaryCells = $records[4]->getElementsByTagName('td');
        $this->assertCount(2, $summaryCells);
        $this->assertSame('Total', $this->getNodeContent($summaryCells[0]));
        $this->assertSame('13.35 CHF', $this->getNodeContent($summaryCells[1]));

        // Customer data
        $customer = $dom->getElementById('customer');
        $customerCells = $customer->getElementsByTagName('td');
        $customerOutput = $this->getNodeContent($customerCells[0]);
        $customerExpected = "Firstname Lastname\nTest Unicode Straße 150\n10115 Berlin";
        $this->assertSame($customerExpected, $this->getNodeContent($customerCells[0]));
        $customerIdents = $this->getNodeContent($customerCells[1]);
        $this->assertTrue(strpos($customerIdents, "Account ID {$wallet->id}") !== false);
        $this->assertTrue(strpos($customerIdents, "Customer No. {$wallet->owner->id}") !== false);

        // TODO: Company details in the footer
        $footer = $dom->getElementById('footer');
        $footerOutput = $footer->textContent;
        $this->assertStringStartsWith(\config('app.company.details'), $footerOutput);
        $this->assertTrue(strpos($footerOutput, \config('app.company.email')) !== false);
    }

    /**
     * Test receipt PDF output
     *
     * @return void
     */
    public function testPdfOutput()
    {
        $wallet = $this->getTestData();
        $receipt = new Receipt($wallet, 2020, 5);
        $pdf = $receipt->PdfOutput();

        $this->assertStringStartsWith("%PDF-1.3\n", $pdf);
        $this->assertTrue(strlen($pdf) > 5000);

        // TODO: Test the content somehow
    }

    /**
     * Prepare data for a test
     *
     * @return \App\Wallet
     */
    protected function getTestData()
    {
        Bus::fake();

        $user = $this->getTestUser('receipt-test@kolabnow.com');
        $user->setSettings([
                'first_name' => 'Firstname',
                'last_name' => 'Lastname',
                'billing_address' => "Test Unicode Straße 150\n10115 Berlin",
        ]);

        $wallet = $user->wallets()->first();

        // Create two payments out of the 2020-05 period
        // and three in it, plus one in the period but unpaid,
        // and one with amount 0

        $payment = Payment::create([
                'id' => 'AAA1',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Paid in April',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 1111,
        ]);
        $payment->updated_at = Carbon::create(2020, 4, 30, 12, 0, 0);
        $payment->save();

        $payment = Payment::create([
                'id' => 'AAA2',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Paid in June',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 2222,
        ]);
        $payment->updated_at = Carbon::create(2020, 6, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
                'id' => 'AAA3',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Auto-Payment Setup',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 0,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
                'id' => 'AAA4',
                'status' => PaymentProvider::STATUS_OPEN,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Payment not yet paid',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 999,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        // ... so we expect the last three on the receipt
        $payment = Payment::create([
                'id' => 'AAA5',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Payment OK',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 1234,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 1, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
                'id' => 'AAA6',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_ONEOFF,
                'description' => 'Payment OK',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 1,
        ]);
        $payment->updated_at = Carbon::create(2020, 5, 10, 0, 0, 0);
        $payment->save();

        $payment = Payment::create([
                'id' => 'AAA7',
                'status' => PaymentProvider::STATUS_PAID,
                'type' => PaymentProvider::TYPE_RECURRING,
                'description' => 'Payment OK',
                'wallet_id' => $wallet->id,
                'provider' => 'stripe',
                'amount' => 100,
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

        return trim(implode($content));
    }
}
