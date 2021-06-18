<?php

namespace Tests\Browser\Reseller;

use App\SignupInvitation;
use Illuminate\Support\Facades\Queue;
use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\Reseller\Invitations;
use Tests\TestCaseDusk;

class InvitationsTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
        SignupInvitation::truncate();
    }

    /**
     * Test invitations page (unauthenticated)
     */
    public function testInvitationsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/invitations')->on(new Home());
        });
    }

    /**
     * Test Invitations creation
     */
    public function testInvitationCreate(): void
    {
        $this->browse(function (Browser $browser) {
            $date_regexp = '/^20[0-9]{2}-/';

            $browser->visit(new Home())
                ->submitLogon('reseller@kolabnow.com', 'reseller', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-invitations', 'Invitations')
                ->click('@links .link-invitations')
                ->on(new Invitations())
                ->assertElementsCount('@table tbody tr', 0)
                ->assertMissing('#more-loader')
                ->assertSeeIn('@table tfoot td', "There are no invitations in the database.")
                ->assertSeeIn('@create-button', 'Create invite(s)');

            // Create a single invite with email address input
            $browser->click('@create-button')
                ->with(new Dialog('#invite-create'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Invite for a signup')
                        ->assertFocused('@body input#email')
                        ->assertValue('@body input#email', '')
                        ->type('@body input#email', 'test')
                        ->assertSeeIn('@button-action', 'Send invite(s)')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, "Form validation error")
                        ->waitFor('@body input#email.is-invalid')
                        ->assertSeeIn(
                            '@body input#email.is-invalid + .invalid-feedback',
                            "The email must be a valid email address."
                        )
                        ->type('@body input#email', 'test@domain.tld')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, "The invitation has been created.")
                ->waitUntilMissing('#invite-create')
                ->waitUntilMissing('@table .app-loader')
                ->assertElementsCount('@table tbody tr', 1)
                ->assertMissing('@table tfoot')
                ->assertSeeIn('@table tbody tr td.email', 'test@domain.tld')
                ->assertText('@table tbody tr td.email title', 'Not sent yet')
                ->assertTextRegExp('@table tbody tr td.datetime', $date_regexp)
                ->assertVisible('@table tbody tr td.buttons button.button-delete')
                ->assertVisible('@table tbody tr td.buttons button.button-resend:disabled');

            sleep(1);

            // Create invites from a file
            $browser->click('@create-button')
                ->with(new Dialog('#invite-create'), function (Browser $browser) {
                    $browser->assertFocused('@body input#email')
                        ->assertValue('@body input#email', '')
                        ->assertMissing('@body input#email.is-invalid')
                        // Submit an empty file
                        ->attach('@body input#file', __DIR__ . '/../../data/empty.csv')
                        ->assertSeeIn('@body input#file + label', 'empty.csv')
                        ->click('@button-action')
                        ->assertToast(Toast::TYPE_ERROR, "Form validation error")
                        // ->waitFor('input#file.is-invalid')
                        ->assertSeeIn(
                            '@body input#file.is-invalid + label + .invalid-feedback',
                            "Failed to find any valid email addresses in the uploaded file."
                        )
                        // Submit non-empty file
                        ->attach('@body input#file', __DIR__ . '/../../data/email.csv')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_SUCCESS, "2 invitations has been created.")
                ->waitUntilMissing('#invite-create')
                ->waitUntilMissing('@table .app-loader')
                ->assertElementsCount('@table tbody tr', 3)
                ->assertTextRegExp('@table tbody tr:nth-child(1) td.email', '/email[12]@test\.com$/')
                ->assertTextRegExp('@table tbody tr:nth-child(2) td.email', '/email[12]@test\.com$/');
        });
    }

    /**
     * Test Invitations deletion and resending
     */
    public function testInvitationDeleteAndResend(): void
    {
        $this->browse(function (Browser $browser) {
            Queue::fake();
            $i1 = SignupInvitation::create(['email' => 'test1@domain.org']);
            $i2 = SignupInvitation::create(['email' => 'test2@domain.org']);
            SignupInvitation::where('id', $i2->id)
                ->update(['created_at' => now()->subHours('2'), 'status' => SignupInvitation::STATUS_FAILED]);

            // Test deleting
            $browser->visit(new Invitations())
                // ->submitLogon('reseller@kolabnow.com', 'reseller', true)
                ->assertElementsCount('@table tbody tr', 2)
                ->click('@table tbody tr:first-child button.button-delete')
                ->assertToast(Toast::TYPE_SUCCESS, "Invitation deleted successfully.")
                ->assertElementsCount('@table tbody tr', 1);

            // Test resending
            $browser->click('@table tbody tr:first-child button.button-resend')
                ->assertToast(Toast::TYPE_SUCCESS, "Invitation added to the sending queue successfully.")
                ->assertElementsCount('@table tbody tr', 1);
        });
    }

    /**
     * Test Invitations list (paging and searching)
     */
    public function testInvitationsList(): void
    {
        $this->browse(function (Browser $browser) {
            Queue::fake();
            $i1 = SignupInvitation::create(['email' => 'email1@ext.com']);
            $i2 = SignupInvitation::create(['email' => 'email2@ext.com']);
            $i3 = SignupInvitation::create(['email' => 'email3@ext.com']);
            $i4 = SignupInvitation::create(['email' => 'email4@other.com']);
            $i5 = SignupInvitation::create(['email' => 'email5@other.com']);
            $i6 = SignupInvitation::create(['email' => 'email6@other.com']);
            $i7 = SignupInvitation::create(['email' => 'email7@other.com']);
            $i8 = SignupInvitation::create(['email' => 'email8@other.com']);
            $i9 = SignupInvitation::create(['email' => 'email9@other.com']);
            $i10 = SignupInvitation::create(['email' => 'email10@other.com']);
            $i11 = SignupInvitation::create(['email' => 'email11@other.com']);

            SignupInvitation::query()->update(['created_at' => now()->subDays('1')]);
            SignupInvitation::where('id', $i1->id)
                ->update(['created_at' => now()->subHours('2'), 'status' => SignupInvitation::STATUS_FAILED]);
            SignupInvitation::where('id', $i2->id)
                ->update(['created_at' => now()->subHours('3'), 'status' => SignupInvitation::STATUS_SENT]);
            SignupInvitation::where('id', $i3->id)
                ->update(['created_at' => now()->subHours('4'), 'status' => SignupInvitation::STATUS_COMPLETED]);
            SignupInvitation::where('id', $i11->id)->update(['created_at' => now()->subDays('3')]);

            // Test paging (load more) feature
            $browser->visit(new Invitations())
                // ->submitLogon('reseller@kolabnow.com', 'reseller', true)
                ->assertElementsCount('@table tbody tr', 10)
                ->assertSeeIn('#more-loader button', 'Load more')
                ->with('@table tbody', function ($browser) use ($i1, $i2, $i3) {
                    $browser->assertSeeIn('tr:nth-child(1) td.email', $i1->email)
                        ->assertText('tr:nth-child(1) td.email svg.text-danger title', 'Sending failed')
                        ->assertVisible('tr:nth-child(1) td.buttons button.button-delete')
                        ->assertVisible('tr:nth-child(1) td.buttons button.button-resend:not(:disabled)')
                        ->assertSeeIn('tr:nth-child(2) td.email', $i2->email)
                        ->assertText('tr:nth-child(2) td.email svg.text-primary title', 'Sent')
                        ->assertVisible('tr:nth-child(2) td.buttons button.button-delete')
                        ->assertVisible('tr:nth-child(2) td.buttons button.button-resend:not(:disabled)')
                        ->assertSeeIn('tr:nth-child(3) td.email', $i3->email)
                        ->assertText('tr:nth-child(3) td.email svg.text-success title', 'User signed up')
                        ->assertVisible('tr:nth-child(3) td.buttons button.button-delete')
                        ->assertVisible('tr:nth-child(3) td.buttons button.button-resend:disabled')
                        ->assertText('tr:nth-child(4) td.email svg title', 'Not sent yet')
                        ->assertVisible('tr:nth-child(4) td.buttons button.button-delete')
                        ->assertVisible('tr:nth-child(4) td.buttons button.button-resend:disabled');
                })
                ->click('#more-loader button')
                ->whenAvailable('@table tbody tr:nth-child(11)', function ($browser) use ($i11) {
                    $browser->assertSeeIn('td.email', $i11->email);
                })
                ->assertMissing('#more-loader button');

            // Test searching (by domain)
            $browser->type('@search-input', 'ext.com')
                ->click('@search-button')
                ->waitUntilMissing('@table .app-loader')
                ->assertElementsCount('@table tbody tr', 3)
                ->assertMissing('#more-loader button')
                // search by full email
                ->type('@search-input', 'email7@other.com')
                ->keys('@search-input', '{enter}')
                ->waitUntilMissing('@table .app-loader')
                ->assertElementsCount('@table tbody tr', 1)
                ->assertSeeIn('@table tbody tr:nth-child(1) td.email', 'email7@other.com')
                ->assertMissing('#more-loader button')
                // reset search
                ->vueClear('#search-form input')
                ->keys('@search-input', '{enter}')
                ->waitUntilMissing('@table .app-loader')
                ->assertElementsCount('@table tbody tr', 10)
                ->assertVisible('#more-loader button');
        });
    }
}
