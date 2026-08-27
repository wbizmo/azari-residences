<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(): View
    {
        return view('admin.properties.index', [
            'properties' => Property::query()
                ->with(['amenities', 'locationRecord', 'roomType'])
                ->orderBy('sort_order')
                ->latest()
                ->paginate(config('azari.pagination.per_page', 10)),
        ]);
    }

    public function create(): View
    {
        return view('admin.properties.form', $this->formData(new Property));
    }

    public function store(Request $request): RedirectResponse
    {
        $property = Property::query()->create($this->validatedPayload($request));
        $property->amenities()->sync($request->input('amenities', []));

        return redirect()->route('azari.admin.properties.edit', $property)
            ->with('status', 'Property created.');
    }

    public function edit(Property $property): View
    {
        $property->load('amenities');

        return view('admin.properties.form', $this->formData($property));
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $property->update($this->validatedPayload($request, $property));
        $property->amenities()->sync($request->input('amenities', []));

        return back()->with('status', 'Property updated.');
    }

    private function formData(Property $property): array
    {
        return [
            'property' => $property,
            'amenities' => Amenity::query()->orderBy('name')->get(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validatedPayload(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'formatted_address' => ['required', 'string', 'max:1000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_region' => ['nullable', 'string', 'max:120'],
            'address_postal_code' => ['nullable', 'string', 'max:40'],
            'address_country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:1', 'max:30'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:100'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery.*' => ['nullable', 'image', 'max:8192'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $location = Location::query()->findOrFail($data['location_id']);
        $roomType = RoomType::query()->findOrFail($data['room_type_id']);

        $data['location'] = $location->name;
        $data['country'] = $location->country;
        $data['address_country_code'] = filled($data['address_country_code'] ?? null)
            ? strtoupper($data['address_country_code'])
            : null;
        $data['property_type'] = Str::lower($roomType->slug ?: $roomType->name);
        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        $data['currency'] = (string) config('azari.currency', 'USD');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');
        $data['same_day_booking'] = $request->boolean('same_day_booking');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($request->hasFile('cover_image')) {
            if ($property?->cover_image) {
                Storage::disk('public')->delete($property->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('properties/covers', 'public');
        } else {
            unset($data['cover_image']);
        }

        if ($request->hasFile('gallery')) {
            $existing = $property?->gallery ?? [];
            $uploaded = collect($request->file('gallery'))
                ->map(fn ($image) => $image->store('properties/gallery', 'public'))
                ->all();
            $data['gallery'] = array_values([...$existing, ...$uploaded]);
        } else {
            unset($data['gallery']);
        }

        unset($data['amenities']);

        return $data;
    }
}
