<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\AvailabilitySearchRequest;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class AvailabilitySearchController extends Controller
{
    public function __invoke(AvailabilitySearchRequest $request): View
    {
        $data = $request->validated();

        $checkIn = CarbonImmutable::parse($data['check_in']);
        $checkOut = CarbonImmutable::parse($data['check_out']);

        return view('public.search.results', [
            'search' => [
                ...$data,
                'nights' => $checkIn->diffInDays($checkOut),
                'guest_total' => (int) $data['adults'] + (int) $data['children'],
            ],
            'results' => [],
        ]);
    }
}
