<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function availability(): View
    {
        return view('public.bookings.search', [
            'locations' => Location::query()
                ->where('is_active', true)
                ->whereHas('properties', fn ($query) => $query
                    ->where('is_published', true)
                    ->where('status', '!=', 'inactive'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->whereHas('properties', fn ($query) => $query
                    ->where('is_published', true)
                    ->where('status', '!=', 'inactive'))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function apartments(): View
    {
        return $this->collection('Apartments', 'apartment');
    }

    public function rooms(): View
    {
        return $this->collection('Rooms', 'room');
    }

    public function page(string $key): View
    {
        $pages = [
            'services' => ['Services', 'Thoughtful services supporting every stage of your stay.'],
            'concierge' => ['Concierge', 'Personal assistance for reservations, arrangements and local recommendations.'],
            'housekeeping' => ['Housekeeping', 'Professionally coordinated housekeeping for a consistently comfortable stay.'],
            'restaurant' => ['Restaurant', 'Dining information and guest meal arrangements.'],
            'airport-transfers' => ['Airport transfers', 'Pre-arranged airport pickup and transfer support.'],
            'local-guide' => ['Local guide', 'A practical guide to nearby dining, culture, business and leisure.'],
            'about' => ['About Azari', 'Private, fully serviced residences managed with dependable hospitality.'],
            'contact' => ['Contact', 'Contact the Azari team for booking and guest support.'],
            'support' => ['Guest support', 'Get help with an existing or upcoming stay.'],
            'booking-terms' => ['Booking terms', 'The terms applying to Azari reservations.'],
            'cancellation-policy' => ['Cancellation policy', 'Cancellation conditions are confirmed with each reservation.'],
            'privacy-policy' => ['Privacy policy', 'How Azari handles guest and booking information.'],
            'terms' => ['Terms and conditions', 'The general terms governing use of the Azari website and services.'],
        ];

        abort_unless(isset($pages[$key]), 404);

        [$title, $intro] = $pages[$key];

        return view('public.pages.standard', compact('key', 'title', 'intro'));
    }

    private function collection(string $title, string $type): View
    {
        $properties = Property::query()
            ->with(['locationRecord', 'roomType', 'amenities'])
            ->where('is_published', true)
            ->where('status', '!=', 'inactive')
            ->where(function ($query) use ($type): void {
                $query->whereRaw('LOWER(property_type) = ?', [$type])
                    ->orWhereHas('roomType', fn ($roomType) => $roomType
                        ->whereRaw('LOWER(name) LIKE ?', ['%'.$type.'%']));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(config('azari.pagination.per_page', 10));

        return view('public.pages.collection', compact('title', 'type', 'properties'));
    }
}
