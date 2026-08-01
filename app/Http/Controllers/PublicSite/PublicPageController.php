<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Mail\ContactEnquiry;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Bookings\AzariAvailabilityEngine;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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
                ->orderBy('sort_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->whereHas('properties', fn ($query) => $query
                    ->where('is_published', true)
                    ->where('status', '!=', 'inactive'))
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function propertyAvailability(
        Property $property,
        AzariAvailabilityEngine $availability
    ): View {
        abort_unless($property->is_published && $property->status !== 'inactive', 404);

        $property->load(['locationRecord', 'roomType', 'amenities']);
        $start = CarbonImmutable::today(config('azari.timezone', 'Africa/Lagos'));

        return view('public.bookings.property-availability', [
            'property' => $property,
            'calendar' => $availability->calendar($property->getKey(), $start, 90),
            'start' => $start,
        ]);
    }

    public function apartments(): View
    {
        return $this->collection('Apartments', 'apartment', 'azari-hub.png', 'Private residences with the freedom of home and the consistency of thoughtful hospitality.');
    }

    public function rooms(): View
    {
        $properties = Property::query()
            ->with(['locationRecord', 'roomType', 'amenities'])
            ->where('is_published', true)
            ->whereIn('status', ['available', 'published', 'active'])
            ->whereNotNull('room_type_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(4)->withQueryString();

        return view('public.pages.collection', [
            'title' => 'Rooms',
            'type' => 'room',
            'properties' => $properties,
            'image' => 'azari-booking-suite.png',
            'intro' => 'Refined rooms prepared for focused business stays, short visits and effortless city breaks.',
        ]);
    }

    public function page(string $key): View
    {
        $pages = [
            'services' => ['Services', 'Thoughtful support for every stage of your stay.', 'azari-concierge.png'],
            'concierge' => ['Concierge', 'Personal assistance, considered recommendations and dependable arrangements.', 'azari-concierge.png'],
            'housekeeping' => ['Housekeeping', 'Professional care that keeps every residence calm, fresh and ready.', 'azari-housekeeping.png'],
            'restaurant' => ['Restaurant & dining', 'Curated dining support, local recommendations and memorable table experiences.', 'azari-food.png'],
            'airport-transfers' => ['Airport transfers', 'Reliable pickup and drop-off coordination from arrival to residence.', 'azari-airport.png'],
            'local-guide' => ['Local guide', 'Discover dining, culture, business districts and everyday essentials with local confidence.', 'local-guides-azari.png'],
            'about' => ['About Azari', 'A hospitality team creating dependable, private and beautifully managed stays.', 'team-azari.png'],
            'contact' => ['Contact', 'Speak with the Azari team about bookings, stays, partnerships or guest support.', 'contact-azari.png'],
            'support' => ['Guest support', 'Get help with an existing or upcoming stay.', 'contact-azari.png'],
            'booking-terms' => ['Booking terms', 'The terms applying to Azari reservations.', 'azari-hub.png'],
            'cancellation-policy' => ['Cancellation policy', 'Cancellation conditions are confirmed with each reservation.', 'azari-hub.png'],
            'privacy-policy' => ['Privacy policy', 'How Azari handles guest and booking information.', 'azari-hub.png'],
            'terms' => ['Terms and conditions', 'The general terms governing use of the Azari website and services.', 'azari-hub.png'],
        ];

        abort_unless(isset($pages[$key]), 404);
        [$title, $intro, $image] = $pages[$key];

        return view('public.pages.standard', compact('key', 'title', 'intro', 'image'));
    }

    public function contact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $recipient = (string) config('mail.contact_to', config('mail.from.address'));

        Mail::to($recipient)->send(new ContactEnquiry($data));

        return back()->with('success', 'Your message has been sent. The Azari team will respond as soon as possible.');
    }

    private function collection(string $title, string $type, string $image, string $intro): View
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
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(4)->withQueryString();

        return view('public.pages.collection', compact('title', 'type', 'properties', 'image', 'intro'));
    }
}
