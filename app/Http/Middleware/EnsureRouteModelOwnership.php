<?php

namespace App\Http\Middleware;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\GuestIdentityDocument;
use App\Models\Payment;
use App\Models\UserIdentityDocument;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureRouteModelOwnership
{
    /**
     * Route parameters that are security-sensitive in the customer account area.
     *
     * Parameters not listed here are deliberately ignored so this middleware
     * cannot accidentally reject harmless pagination, filters or slugs.
     */
    private const SENSITIVE_PARAMETERS = [
        'reference',
        'payment',
        'document',
        'guest',
        'notification',
        'session',
        'serviceRequest',
        'supportTicket',
        'listing',
        'agreement',
        'withdrawal',
        'payoutProfile',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_if($user->isStaff(), 404);

        foreach (self::SENSITIVE_PARAMETERS as $parameter) {
            if (! $request->route()->hasParameter($parameter)) {
                continue;
            }

            $value = $request->route($parameter);

            abort_unless($this->belongsToUser($parameter, $value, (int) $user->getKey()), 404);
        }

        return $next($request);
    }

    private function belongsToUser(string $parameter, mixed $value, int $userId): bool
    {
        if ($value instanceof Model) {
            return $this->modelBelongsToUser($value, $userId);
        }

        if (! is_scalar($value) || blank((string) $value)) {
            return false;
        }

        return match ($parameter) {
            'reference' => Booking::query()
                ->where('reference', (string) $value)
                ->where('user_id', $userId)
                ->exists(),

            'payment' => Payment::query()
                ->whereKey($value)
                ->where(function ($query) use ($userId): void {
                    $query->where('user_id', $userId)
                        ->orWhereHas('booking', fn ($booking) => $booking->where('user_id', $userId));
                })
                ->exists(),

            'document' => UserIdentityDocument::query()
                ->whereKey($value)
                ->where('user_id', $userId)
                ->exists()
                || GuestIdentityDocument::query()
                    ->whereKey($value)
                    ->whereHas('guest.booking', fn ($booking) => $booking->where('user_id', $userId))
                    ->exists(),

            'guest' => BookingGuest::query()
                ->whereKey($value)
                ->whereHas('booking', fn ($booking) => $booking->where('user_id', $userId))
                ->exists(),

            'notification' => DB::table('notifications')
                ->where('id', (string) $value)
                ->where('notifiable_type', 'App\\Models\\User')
                ->where('notifiable_id', $userId)
                ->exists(),

            'session' => config('session.driver') !== 'database'
                || DB::table(config('session.table', 'sessions'))
                    ->where('id', (string) $value)
                    ->where('user_id', $userId)
                    ->exists(),

            default => $this->genericScalarOwnershipLookup($parameter, $value, $userId),
        };
    }

    private function modelBelongsToUser(Model $model, int $userId): bool
    {
        foreach (['user_id', 'owner_id'] as $column) {
            if ($model->getAttribute($column) !== null) {
                return (int) $model->getAttribute($column) === $userId;
            }
        }

        if (method_exists($model, 'booking')) {
            $booking = $model->relationLoaded('booking')
                ? $model->getRelation('booking')
                : $model->booking()->first();

            return $booking && (int) $booking->user_id === $userId;
        }

        if (method_exists($model, 'guest')) {
            $guest = $model->relationLoaded('guest')
                ? $model->getRelation('guest')
                : $model->guest()->first();

            if ($guest && method_exists($guest, 'booking')) {
                return (int) optional($guest->booking)->user_id === $userId;
            }
        }

        return false;
    }

    private function genericScalarOwnershipLookup(string $parameter, mixed $value, int $userId): bool
    {
        $modelClasses = [
            'serviceRequest' => \App\Models\ServiceRequest::class,
            'supportTicket' => \App\Models\SupportTicket::class,
            'listing' => \App\Models\PropertyListing::class,
            'agreement' => \App\Models\ListingAgreement::class,
            'withdrawal' => \App\Models\WithdrawalRequest::class,
            'payoutProfile' => \App\Models\OwnerPayoutProfile::class,
        ];

        $class = $modelClasses[$parameter] ?? null;

        if (! $class || ! class_exists($class)) {
            return false;
        }

        $model = $class::query()->find($value);

        return $model instanceof Model && $this->modelBelongsToUser($model, $userId);
    }
}
