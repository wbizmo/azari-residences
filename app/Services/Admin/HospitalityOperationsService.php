<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\InventoryDate;
use App\Models\Payment;
use App\Models\PropertyListing;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\WithdrawalRequest;

class HospitalityOperationsService
{
    public function today(): array
    {
        $today = today();

        return [
            'arrivals' => Booking::query()
                ->whereDate('check_in', $today)
                ->whereNotIn('status', ['cancelled', 'expired', 'no_show'])
                ->count(),
            'departures' => Booking::query()
                ->whereDate('check_out', $today)
                ->whereNotIn('status', ['cancelled', 'expired'])
                ->count(),
            'checked_in' => Booking::query()
                ->whereIn('status', ['check_in', 'checked_in'])
                ->count(),
            'overdue_checkouts' => Booking::query()
                ->whereIn('status', ['check_in', 'checked_in'])
                ->whereDate('check_out', '<', $today)
                ->count(),
            'pending_payments' => Booking::query()
                ->whereNotIn('status', ['cancelled', 'completed', 'checked_out', 'no_show'])
                ->whereNull('paid_at')
                ->whereRaw(
                    'total > COALESCE((SELECT SUM(amount) FROM payments WHERE payments.booking_id = bookings.id AND payments.status = ?), 0)',
                    [Payment::SUCCESSFUL]
                )
                ->count(),
            'failed_payments' => Payment::query()
                ->where('status', Payment::FAILED)
                ->whereDate('updated_at', '>=', $today->copy()->subDays(7))
                ->count(),
            'cancellations' => Booking::query()
                ->whereDate('cancelled_at', $today)
                ->count(),
            'no_shows' => Booking::query()
                ->whereDate('no_show_at', $today)
                ->count(),
            'expiring_holds' => BookingHold::query()
                ->active()
                ->whereBetween('expires_at', [now(), now()->addMinutes(30)])
                ->count(),
            'maintenance_inventory' => InventoryDate::query()
                ->whereDate('date', $today)
                ->where(function ($query): void {
                    $query->where('maintenance_inventory', '>', 0)
                        ->orWhere('stop_sell', true);
                })
                ->count(),
            'support_escalations' => SupportTicket::query()
                ->whereNotIn('status', ['resolved', 'closed'])
                ->where(function ($query): void {
                    $query->whereNotNull('escalated_at')
                        ->orWhere('priority', 'urgent');
                })
                ->count(),
            'service_requests' => ServiceRequest::query()
                ->whereNotIn('status', ['resolved', 'closed', 'cancelled'])
                ->count(),
            'owner_approvals' => PropertyListing::query()
                ->whereIn('status', ['submitted', 'under_review'])
                ->count(),
            'withdrawal_requests' => WithdrawalRequest::query()
                ->whereIn('status', ['pending', 'failed'])
                ->count(),
        ];
    }

    public function queues(): array
    {
        return [
            'arrivals' => Booking::query()
                ->with(['property', 'user'])
                ->whereDate('check_in', today())
                ->whereNotIn('status', ['cancelled', 'expired', 'no_show'])
                ->orderBy('arrival_time')
                ->limit(8)
                ->get(),
            'departures' => Booking::query()
                ->with(['property', 'user'])
                ->whereDate('check_out', today())
                ->whereNotIn('status', ['cancelled', 'expired'])
                ->orderBy('check_out')
                ->limit(8)
                ->get(),
            'payment_attention' => Payment::query()
                ->with('booking.property')
                ->where(function ($query): void {
                    $query->whereIn('status', ['failed', 'invalid', 'successful_excess'])
                        ->orWhereHas('refunds', fn ($refunds) => $refunds->whereIn('status', ['requested', 'processing', 'failed']));
                })
                ->latest('updated_at')
                ->limit(8)
                ->get(),
            'support_attention' => SupportTicket::query()
                ->with(['user', 'booking'])
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 WHEN priority = 'high' THEN 1 ELSE 2 END")
                ->oldest('created_at')
                ->limit(8)
                ->get(),
        ];
    }
}
