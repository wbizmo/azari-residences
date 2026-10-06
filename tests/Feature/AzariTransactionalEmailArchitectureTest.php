<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Tests\TestCase;

class AzariTransactionalEmailArchitectureTest extends TestCase
{
    public function test_critical_azari_mail_bypasses_optional_email_preference(): void
    {
        $user = new User([
            'email' => 'guest@example.com',
            'email_notifications' => false,
        ]);

        $notification = new PremiumMailNotification(
            template: 'booking-confirmed',
            subject: 'Booking confirmed',
            forceDelivery: true,
            mailOnly: true,
        );

        $this->assertSame(['mail'], $notification->via($user));
    }

    public function test_optional_azari_mail_respects_email_preference(): void
    {
        $user = new User([
            'email' => 'guest@example.com',
            'email_notifications' => false,
        ]);

        $notification = new PremiumMailNotification(
            template: 'checkout-reminder',
            subject: 'Checkout reminder',
            forceDelivery: false,
            mailOnly: true,
        );

        $this->assertSame([], $notification->via($user));
    }

    public function test_email_template_is_azari_branded_and_structured(): void
    {
        $html = view('emails.premium', [
            'title' => 'Payment confirmed',
            'preheader' => 'Payment confirmed.',
            'lines' => ['Your payment was verified.'],
            'actionLabel' => 'View receipt',
            'actionUrl' => 'https://example.test/receipt',
            'secondaryActionLabel' => null,
            'secondaryActionUrl' => null,
            'details' => ['Booking reference' => 'AZR-TEST'],
            'notice' => 'Keep this reference.',
            'tone' => 'success',
            'eyebrow' => 'Payment successful',
            'logoUrl' => null,
            'supportEmail' => 'support@example.test',
            'footerText' => 'Transactional message.',
        ])->render();

        $this->assertStringContainsString('RESERVA', $html);
        $this->assertStringContainsString('Booking reference', $html);
        $this->assertStringContainsString('Exceptional Stays, Everywhere.', $html);
        $this->assertStringNotContainsString('Laravel', $html);
    }

    public function test_all_transactional_observers_are_registered(): void
    {
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        foreach ([
            'BookingObserver::class',
            'PaymentObserver::class',
            'ServiceRequestObserver::class',
            'PropertyListingObserver::class',
            'OwnerPayoutProfileObserver::class',
            'WithdrawalRequestObserver::class',
            'OwnerLedgerEntryObserver::class',
            'UserIdentityDocumentObserver::class',
            'GuestIdentityDocumentObserver::class',
        ] as $observer) {
            $this->assertStringContainsString($observer, $provider);
        }
    }

    public function test_transactional_reminder_command_is_scheduled(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString(
            "azari:send-transactional-reminders",
            $schedule
        );
    }
}
