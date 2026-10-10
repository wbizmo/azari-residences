<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Travel\TravelVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TravelVoucherRedemptionController extends Controller
{
    public function __invoke(Request $request, TravelVoucherService $service): RedirectResponse
    {
        abort_unless(config('travel.fulfillment_enabled', false), 404);
        $payload = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:travel_suppliers,id'],
            'voucher_code' => ['required', 'string', 'max:48'],
        ]);

        $service->redeem(
            $request->user(),
            strtoupper(trim($payload['voucher_code'])),
            (int) $payload['supplier_id']
        );

        return back()->with('success', 'Voucher verified and redeemed once. No accommodation payment was changed.');
    }
}
