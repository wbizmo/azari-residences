<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingGuest;
use App\Models\BookingStatusHistory;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AzariSprintFiveSixDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'user@example.com'], [
            'name' => 'Azari Demo Administrator', 'password' => Hash::make('12345678'),
            'is_admin' => true, 'staff_role' => 'administrator', 'account_type' => 'staff',
            'status' => 'active', 'is_active' => true, 'email_verified_at' => now(),
        ]);

        $customer = User::updateOrCreate(['email' => 'customer@example.com'], [
            'name' => 'Demo Customer', 'password' => Hash::make('12345678'),
            'is_admin' => false, 'staff_role' => null, 'account_type' => 'customer',
            'status' => 'active', 'is_active' => true, 'email_verified_at' => now(),
        ]);

        $properties = Property::query()->where('is_published', true)->take(4)->get();
        foreach ($properties as $index => $property) {
            $checkIn = today()->addDays(($index * 4) + 2);
            $checkOut = $checkIn->copy()->addDays(2 + ($index % 2));
            $status = $index === 0 ? 'paid' : ($index === 1 ? 'check_in' : ($index === 2 ? 'completed' : 'cancelled'));
            $booking = Booking::updateOrCreate(['reference' => 'AZR-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)], [
                'user_id' => $customer->id, 'property_id' => $property->id,
                'guest_name' => 'Demo Customer', 'guest_first_name' => 'Demo', 'guest_last_name' => 'Customer',
                'guest_email' => $customer->email, 'guest_phone' => '+2348000000000',
                'nationality' => 'Nigerian', 'address' => 'Demo address', 'city' => 'Lagos', 'country' => 'Nigeria',
                'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => 2, 'children' => 0, 'rooms' => 1,
                'status' => $status, 'verification_status' => 'verified', 'currency' => $property->currency ?: 'NGN',
                'nightly_rate' => $property->nightly_rate, 'nights' => $checkIn->diffInDays($checkOut),
                'subtotal' => $property->nightly_rate * $checkIn->diffInDays($checkOut), 'fee_total' => 0,
                'add_on_total' => 0, 'tax_rate' => 0, 'tax_total' => 0,
                'total' => $property->nightly_rate * $checkIn->diffInDays($checkOut),
                'paid_at' => $status !== 'cancelled' ? now() : null,
                'payment_reference' => $status !== 'cancelled' ? 'DEMO-PAY-'.($index + 1) : null,
                'receipt_number' => $status !== 'cancelled' ? 'RCT-DEMO-'.($index + 1) : null,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Demo administrative cancellation.' : null,
                'checked_in_at' => $status === 'check_in' ? now() : null,
                'completed_at' => $status === 'completed' ? now() : null,
            ]);

            foreach ([1, 2] as $position) {
                BookingGuest::updateOrCreate(['booking_id' => $booking->id, 'type' => 'adult', 'position' => $position], [
                    'first_name' => $position === 1 ? 'Demo' : 'Second', 'last_name' => 'Guest', 'is_lead' => $position === 1,
                ]);
            }

            BookingStatusHistory::firstOrCreate(['booking_id' => $booking->id, 'to_status' => $status], [
                'changed_by' => $admin->id, 'from_status' => null, 'note' => 'Seeded demonstration booking.',
            ]);
        }
    }
}
