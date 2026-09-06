<?php

namespace Tests\Feature;

use App\Mail\AccountDeletionRequestMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountDeletionRequestTest extends TestCase
{
    public function test_account_deletion_page_is_publicly_available(): void
    {
        $this->get('/account-deletion')
            ->assertOk()
            ->assertSee('Account & Data Deletion')
            ->assertSee('Submit deletion request');
    }

    public function test_account_deletion_request_requires_email_and_confirmation(): void
    {
        $this->post('/account-deletion', [])
            ->assertSessionHasErrors(['email', 'confirm']);
    }

    public function test_account_deletion_request_emails_configured_recipient(): void
    {
        Mail::fake();
        config(['azari.contact_recipient_email' => 'privacy@example.com']);

        $this->post('/account-deletion', [
            'name' => 'Azari Reviewer',
            'email' => 'reviewer@example.com',
            'confirm' => '1',
            'company_website' => '',
        ])->assertRedirect(route('account-deletion.show'))
          ->assertSessionHas('account_deletion_reference');

        Mail::assertSent(AccountDeletionRequestMail::class, function (AccountDeletionRequestMail $mail): bool {
            return $mail->requesterEmail === 'reviewer@example.com'
                && $mail->hasTo('privacy@example.com');
        });
    }
}
