<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\BookingGuest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdditionalGuestController extends Controller
{
    public function index(Request $request): View
    {
        $guests = BookingGuest::query()
            ->with(['booking.property', 'latestIdentityVerification'])
            ->whereHas('booking', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('type', 'adult')
            ->where('is_lead', false)
            ->latest()
            ->paginate(10);

        return view('user.guests.index', compact('guests'));
    }
}
