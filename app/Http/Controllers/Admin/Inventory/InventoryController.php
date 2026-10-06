<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResponsiveImageDerivatives;
use App\Support\ResponsiveImage;
use App\Models\Amenity;
use App\Models\Building;
use App\Models\Location;
use App\Models\PricingRule;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('admin.inventory.index', [
            'locations' => Location::query()->withCount(['buildings', 'properties'])->orderBy('sort_order')->paginate(10, ['*'], 'locations')->withQueryString(),
            'buildings' => Building::query()->with('location')->withCount('properties')->orderBy('sort_order')->paginate(10, ['*'], 'buildings')->withQueryString(),
            'roomTypes' => RoomType::query()->withCount('properties')->orderBy('sort_order')->paginate(10, ['*'], 'room_types')->withQueryString(),
            'properties' => Property::query()->with(['locationRecord', 'building', 'roomType', 'amenities', 'images', 'pricingRules'])->orderBy('sort_order')->latest()->paginate(10, ['*'], 'properties')->withQueryString(),
            'amenities' => Amenity::query()->withCount('properties')->orderBy('sort_order')->paginate(10, ['*'], 'amenities')->withQueryString(),
        ]);
    }

    public function locationStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'alpha_dash', 'max:160', 'unique:locations,slug'],
            'country' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = ((int) Location::query()->max('sort_order')) + 10;

        Location::query()->create($data);

        return back()->with('status', 'Location created.');
    }

    public function buildingStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'name' => ['required', 'string', 'max:140'],
            'code' => ['required', 'alpha_dash', 'max:50', 'unique:buildings,code'],
            'description' => ['nullable', 'string', 'max:3000'],
            'floors' => ['nullable', 'integer', 'between:1,300'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = ((int) Building::query()->max('sort_order')) + 10;

        Building::query()->create($data);

        return back()->with('status', 'Building created.');
    }

    public function roomTypeStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140', 'unique:room_types,name'],
            'slug' => ['nullable', 'alpha_dash', 'max:160', 'unique:room_types,slug'],
            'description' => ['nullable', 'string', 'max:3000'],
            'icon' => ['required', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = ((int) RoomType::query()->max('sort_order')) + 10;

        RoomType::query()->create($data);

        return back()->with('status', 'Room type created.');
    }

    public function amenityStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140', 'unique:amenities,name'],
            'icon' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = ((int) Amenity::query()->max('sort_order')) + 10;

        Amenity::query()->create($data);

        return back()->with('status', 'Amenity created.');
    }

    public function amenityDelete(Amenity $amenity): RedirectResponse
    {
        abort_if($amenity->properties()->exists(), 422, 'This amenity is assigned to a property.');
        $amenity->delete();

        return back()->with('status', 'Amenity deleted.');
    }

    public function propertyCreate(): View
    {
        return view('admin.inventory.property-form', $this->propertyFormData(new Property()));
    }

    public function propertyStore(Request $request): RedirectResponse
    {
        $property = Property::query()->create($this->propertyPayload($request));
        $property->amenities()->sync($request->input('amenities', []));

        $this->storeImages($request, $property);

        return redirect()
            ->route('azari.admin.inventory.properties.edit', $property)
            ->with('status', 'Property created.');
    }

    public function propertyEdit(Property $property): View
    {
        $property->load(['amenities', 'images', 'pricingRules']);

        return view('admin.inventory.property-form', $this->propertyFormData($property));
    }

    public function propertyUpdate(Request $request, Property $property): RedirectResponse
    {
        $property->update($this->propertyPayload($request, $property));
        $property->amenities()->sync($request->input('amenities', []));

        $this->storeImages($request, $property);

        return back()->with('status', 'Property updated.');
    }

    public function propertyDelete(Property $property): RedirectResponse
    {
        foreach ($property->images as $image) {
            ResponsiveImage::deleteDerivatives($image->path);
            Storage::disk('public')->delete($image->path);
        }

        if ($property->cover_image) {
            ResponsiveImage::deleteDerivatives($property->cover_image);
            Storage::disk('public')->delete($property->cover_image);
        }

        $property->delete();

        return redirect()
            ->route('azari.admin.inventory.index')
            ->with('status', 'Property deleted.');
    }

    public function imageDelete(PropertyImage $propertyImage): RedirectResponse
    {
        ResponsiveImage::deleteDerivatives($propertyImage->path);
        Storage::disk('public')->delete($propertyImage->path);
        $propertyImage->delete();

        return back()->with('status', 'Gallery image deleted.');
    }

    public function imageSort(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => [
                'integer',
                Rule::exists('property_images', 'id')->where('property_id', $property->id),
            ],
        ]);

        foreach ($data['items'] as $index => $id) {
            PropertyImage::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }

        return back()->with('status', 'Gallery order updated.');
    }

    public function pricingStore(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'rule_type' => ['required', Rule::in(['seasonal', 'weekend', 'promotion', 'minimum_stay'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'between:-100,1000'],
            'minimum_stay' => ['nullable', 'integer', 'between:1,365'],
            'maximum_stay' => ['nullable', 'integer', 'between:1,730'],
            'priority' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $property->pricingRules()->create($data);

        return back()->with('status', 'Pricing rule created.');
    }

    public function pricingDelete(PricingRule $pricingRule): RedirectResponse
    {
        $pricingRule->delete();

        return back()->with('status', 'Pricing rule deleted.');
    }

    private function propertyFormData(Property $property): array
    {
        return [
            'property' => $property,
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'buildings' => Building::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'supportedCurrencies' => (array) config('localization.supported_currencies', []),
        ];
    }

    private function propertyPayload(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'location_id' => ['nullable', 'exists:locations,id'],
            'building_id' => ['nullable', 'exists:buildings,id'],
            'room_type_id' => ['nullable', 'exists:room_types,id'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'alpha_dash', 'max:200', Rule::unique('properties', 'slug')->ignore($property)],
            'code' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('properties', 'code')->ignore($property)],
            'unit_number' => ['nullable', 'string', 'max:80'],
            'floor' => ['nullable', 'string', 'max:50'],
            'location' => ['required', 'string', 'max:160'],
            'country' => ['required', 'string', 'max:100'],
            'property_type' => ['required', 'string', 'max:100'],
            'bedrooms' => ['required', 'integer', 'between:0,100'],
            'bathrooms' => ['required', 'integer', 'between:0,100'],
            'max_guests' => ['required', 'integer', 'between:1,200'],
            'adult_capacity' => ['required', 'integer', 'between:1,200'],
            'child_capacity' => ['required', 'integer', 'between:0,200'],
            'bed_configuration' => ['nullable', 'string', 'max:240'],
            'room_size' => ['nullable', 'numeric', 'min:0'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'security_deposit' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'currency' => ['required', Rule::in(array_keys((array) config('localization.supported_currencies', [])))],
            'timezone' => ['nullable', 'timezone'],
            'short_description' => ['nullable', 'string', 'max:600'],
            'description' => ['nullable', 'string', 'max:30000'],
            'video_url' => ['nullable', 'url', 'max:1000'],
            'virtual_tour_url' => ['nullable', 'url', 'max:1000'],
            'status' => ['required', Rule::in(['available', 'unavailable', 'maintenance', 'archived'])],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery_images' => ['nullable', 'array', 'max:20'],
            'gallery_images.*' => ['image', 'max:8192'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'is_featured' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');
        $data['currency'] = strtoupper($data['currency']);
        if (blank($data['timezone'] ?? null) && ! empty($data['location_id'])) {
            $data['timezone'] = Location::query()->whereKey($data['location_id'])->value('timezone') ?: config('localization.platform_timezone', 'UTC');
        }
        $data['cleaning_fee'] = $data['cleaning_fee'] ?? 0;
        $data['security_deposit'] = $data['security_deposit'] ?? 0;
        $data['service_charge'] = $data['service_charge'] ?? 0;
        $data['tax_rate'] = $data['tax_rate'] ?? 0;

        if ($request->hasFile('cover_image')) {
            if ($property?->cover_image) {
                ResponsiveImage::deleteDerivatives($property->cover_image);
            Storage::disk('public')->delete($property->cover_image);
            }

            $data['cover_image'] = $request->file('cover_image')->store('properties/covers', 'public');
            GenerateResponsiveImageDerivatives::dispatch($data['cover_image']);
        }

        unset($data['gallery_images'], $data['amenities']);

        return $data;
    }

    private function storeImages(Request $request, Property $property): void
    {
        foreach ($request->file('gallery_images', []) as $index => $file) {
            $property->images()->create([
                'path' => $path = $file->store('properties/gallery', 'public'),
                'title' => $property->name,
                'alt_text' => $property->name.' gallery image',
                'sort_order' => ((int) $property->images()->max('sort_order')) + (($index + 1) * 10),
            ]);
            GenerateResponsiveImageDerivatives::dispatch($path);
        }
    }
}
