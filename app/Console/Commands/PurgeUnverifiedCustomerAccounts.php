<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PurgeUnverifiedCustomerAccounts extends Command
{
    protected $signature = 'resavar:purge-unverified-accounts {--dry-run}';

    protected $description = 'Close stale unverified customer accounts after 7 days and permanently scrub them after 30 days.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $closed = 0;
        $deleted = 0;
        $anonymized = 0;
        $failed = 0;

        User::query()
            ->whereNull('email_verified_at')
            ->whereNull('staff_role')
            ->where('is_admin', false)
            ->where('created_at', '<=', now()->subDays(7))
            ->whereNotIn('status', ['verification_expired', 'suspended'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($dryRun, &$closed): void {
                foreach ($users as $user) {
                    $closed++;

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($user): void {
                        $user->forceFill([
                            'status' => 'verification_expired',
                            'is_active' => false,
                            'remember_token' => null,
                        ])->saveQuietly();

                        if (Schema::hasTable(config('session.table', 'sessions'))) {
                            DB::table(config('session.table', 'sessions'))
                                ->where('user_id', $user->getKey())
                                ->delete();
                        }

                        if (Schema::hasTable('password_reset_tokens')) {
                            DB::table('password_reset_tokens')
                                ->where('email', $user->email)
                                ->delete();
                        }
                    });
                }
            });

        User::query()
            ->whereNull('email_verified_at')
            ->whereNull('staff_role')
            ->where('is_admin', false)
            ->where('created_at', '<=', now()->subDays(30))
            ->orderBy('id')
            ->chunkById(50, function ($users) use ($dryRun, &$deleted, &$anonymized, &$failed): void {
                foreach ($users as $user) {
                    try {
                        if ($dryRun) {
                            $this->hasRetentionRecords($user) ? $anonymized++ : $deleted++;
                            continue;
                        }

                        if ($this->hasRetentionRecords($user)) {
                            $this->anonymizeRetainedAccount($user);
                            $anonymized++;
                            continue;
                        }

                        $this->deleteAccount($user);
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });

        $this->info("Unverified accounts closed after 7 days: {$closed}");
        $this->info("Unverified accounts permanently deleted after 30 days: {$deleted}");
        $this->info("Accounts scrubbed but retained for transactional integrity: {$anonymized}");

        if ($failed > 0) {
            $this->warn("Cleanup failures: {$failed}");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hasRetentionRecords(User $user): bool
    {
        return $user->bookings()->exists()
            || $user->payments()->exists()
            || $user->propertyListings()->exists()
            || $user->withdrawalRequests()->exists()
            || $user->ownerLedgerEntries()->exists()
            || $user->supportTickets()->exists()
            || $user->serviceRequests()->exists()
            || $user->reviews()->exists();
    }

    private function deleteAccount(User $user): void
    {
        $this->deleteProfilePhoto($user);

        DB::transaction(function () use ($user): void {
            $userId = (int) $user->getKey();

            $this->deleteByUserId([
                'communication_preferences',
                'user_favourites',
                'saved_searches',
                'recently_viewed_properties',
                'analytics_events',
                'permission_user',
                'role_user',
            ], $userId);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $userId)
                    ->delete();
            }

            if (Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }

            $user->delete();
        });
    }

    private function anonymizeRetainedAccount(User $user): void
    {
        $this->deleteProfilePhoto($user);

        DB::transaction(function () use ($user): void {
            $userId = (int) $user->getKey();
            $originalEmail = (string) $user->email;

            $this->deleteByUserId([
                'communication_preferences',
                'user_favourites',
                'saved_searches',
                'recently_viewed_properties',
                'analytics_events',
                'permission_user',
                'role_user',
            ], $userId);

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')
                    ->where('notifiable_type', User::class)
                    ->where('notifiable_id', $userId)
                    ->delete();
            }

            if (Schema::hasTable(config('session.table', 'sessions'))) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();
            }

            $user->forceFill([
                'name' => 'Deleted customer',
                'username' => null,
                'email' => 'deleted-'.$userId.'-'.Str::lower(Str::random(12)).'@invalid.resavar',
                'password' => Str::random(64),
                'phone' => null,
                'timezone' => null,
                'locale' => null,
                'display_currency' => null,
                'status' => 'deleted',
                'is_active' => false,
                'avatar_path' => null,
                'profile_photo_path' => null,
                'remember_token' => null,
                'phone_verified_at' => null,
                'last_login_at' => null,
                'last_active_at' => null,
                'emergency_contact_name' => null,
                'emergency_contact_phone' => null,
                'email_notifications' => false,
                'sms_notifications' => false,
                'whatsapp_notifications' => false,
                'marketing_consent' => false,
            ])->saveQuietly();
        });
    }

    private function deleteByUserId(array $tables, int $userId): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                DB::table($table)->where('user_id', $userId)->delete();
            }
        }
    }

    private function deleteProfilePhoto(User $user): void
    {
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }
    }
}
