<?php

namespace Tests\Feature;

use App\Models\CommunicationLog;
use App\Models\OwnerPayoutProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PolishedTransactionalEmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('mail.from.address', 'operations@azari.test');
    }

    public function test_password_change_queues_one_branded_security_email(): void
    {
        $user = User::factory()->create([
            'email' => 'password-owner@azari.test',
            'password' => Hash::make('CurrentPassword!2026'),
        ]);

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'CurrentPassword!2026',
                'password' => 'NewSecurePassword!2026',
                'password_confirmation' => 'NewSecurePassword!2026',
            ])
            ->assertSessionHas('status', 'password-updated');

        $this->assertDatabaseHas('communication_logs', [
            'template' => 'account-password-changed',
            'user_id' => $user->id,
            'recipient' => 'password-owner@azari.test',
            'status' => 'queued',
        ]);

        Notification::assertSentTo(
            $user,
            PremiumMailNotification::class,
            fn (PremiumMailNotification $notification): bool => $notification->template === 'account-password-changed'
                && $notification->forceDelivery
                && $notification->mailOnly
        );
    }

    public function test_email_change_warns_the_previous_address_and_uses_existing_branded_verification(): void
    {
        $user = User::factory()->create([
            'name' => 'Azari Guest',
            'email' => 'previous-address@azari.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Azari Guest',
                'email' => 'new-address@azari.test',
            ])
            ->assertSessionHas('status', 'profile-updated');

        $this->assertDatabaseHas('communication_logs', [
            'template' => 'account-email-changed',
            'user_id' => $user->id,
            'recipient' => 'previous-address@azari.test',
            'status' => 'queued',
        ]);

        $fresh = $user->fresh();

        $this->assertSame('new-address@azari.test', $fresh->email);
        $this->assertNull($fresh->email_verified_at);

        Notification::assertSentTo(
            $fresh,
            VerifyEmail::class
        );
    }

    public function test_support_ticket_sends_only_one_terminal_email_for_resolved_then_closed(): void
    {
        $user = User::factory()->create([
            'email' => 'support-guest@azari.test',
        ]);

        $ticket = SupportTicket::query()->create([
            'reference' => 'AZR-SUP-TEST-0001',
            'user_id' => $user->id,
            'category' => 'booking',
            'subject' => 'Existing booking support request',
            'status' => 'open',
            'priority' => 'normal',
            'response_due_at' => now()->addDay(),
        ]);

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        $this->assertDatabaseHas('communication_logs', [
            'template' => 'support-ticket-resolved',
            'user_id' => $user->id,
            'recipient' => 'support-guest@azari.test',
            'status' => 'queued',
        ]);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->assertSame(
            1,
            CommunicationLog::query()
                ->where('user_id', $user->id)
                ->whereIn('template', ['support-ticket-resolved', 'support-ticket-closed'])
                ->count()
        );
    }

    public function test_payout_destination_email_is_branded_masked_and_not_duplicated_by_verification_change(): void
    {
        $user = User::factory()->create([
            'email' => 'property-owner@azari.test',
        ]);

        $profile = OwnerPayoutProfile::query()->create([
            'user_id' => $user->id,
            'preferred_gateway' => 'paypal',
            'paypal_recipient' => 'private-payout@azari.test',
            'paypal_recipient_type' => 'EMAIL',
            'is_verified' => false,
        ]);

        Notification::assertSentTo(
            $user,
            PremiumMailNotification::class,
            function (PremiumMailNotification $notification): bool {
                return $notification->template === 'owner-payout-destination-changed'
                    && $notification->forceDelivery
                    && $notification->mailOnly
                    && ($notification->details['Destination'] ?? null) === 'pr***@azari.test'
                    && ! str_contains(json_encode($notification->details), 'private-payout@azari.test');
            }
        );

        $profile->update([
            'paypal_recipient' => 'changed-payout@azari.test',
            'is_verified' => false,
        ]);

        $this->assertSame(
            2,
            CommunicationLog::query()
                ->where('template', 'owner-payout-destination-changed')
                ->where('user_id', $user->id)
                ->count()
        );

        $this->assertDatabaseMissing('communication_logs', [
            'template' => 'owner-payout-profile-unverified',
            'user_id' => $user->id,
        ]);
    }
}
