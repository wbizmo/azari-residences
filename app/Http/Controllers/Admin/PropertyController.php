<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResponsiveImageDerivatives;
use App\Support\ResponsiveImage;
use App\Models\Amenity;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\SiteSetting;
use App\Services\Owners\AdminPropertyOwnershipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Location;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, AdminPropertyOwnershipService $ownership): RedirectResponse
    {
        $data = $this->validatedPayload($request);
        $stored = [];

        try {
            $property = DB::transaction(function () use ($request, $ownership, $data, &$stored): Property {
                $payload = $ownership->apply($data, null, $request->user());
                $property = Property::query()->create([
                    ...$payload,
                    ...$this->uploadedMedia($request, null, $stored),
                ]);
                $property->amenities()->sync($request->input('amenities', []));
                $this->registerPendingPhotos($property, collect([$property->cover_image])
                    ->merge($property->gallery ?? [])->filter()->all());

                AuditLog::record('property.created_by_admin', $property, [], [
                    'owner_id' => $property->owner_id,
                    'owner_agreement_id' => $property->owner_agreement_id,
                    'owner_share_percentage' => $property->owner_share_percentage,
                    'is_published' => $property->is_published,
                    'is_featured' => $property->is_featured,
                    'source' => 'admin_direct',
                ]);
                return $property;
            }, 3);
        } catch (\Throwable $e) {
            $this->discardUploadedMedia($stored);
            throw $e;
        }

        $this->queueImageDerivatives($stored);

        return redirect()->route('azari.admin.properties.edit', $property)
            ->with('status', $property->owner_id
                ? 'Owner-assigned property saved as a draft. Review all photographs and accommodations before publishing.'
                : 'Property created. Configure room types and rates as needed.');
    }

    public function edit(Property $property): View
    {
        $property->load(['amenities', 'owner', 'ownerAgreement', 'ownerListing']);

        return view('admin.properties.form', $this->formData($property));
    }

    public function update(
        Request $request,
        Property $property,
        AdminPropertyOwnershipService $ownership
    ): RedirectResponse {
        $data = $this->validatedPayload($request, $property);
        $stored = [];
        $replacedCover = null;

        try {
            DB::transaction(function () use ($request, $property, $ownership, $data, &$stored, &$replacedCover): void {
                $locked = Property::query()->whereKey($property->getKey())->lockForUpdate()->firstOrFail();
                $previous = $locked->only([
                    'owner_id', 'owner_agreement_id', 'owner_share_percentage',
                    'ownership_type', 'is_published', 'is_featured',
                    'location_id', 'formatted_address', 'latitude', 'longitude',
                ]);
                $replacedCover = $locked->cover_image;

                $payload = $ownership->apply($data, $locked, $request->user());
                $locked->fill([
                    ...$payload,
                    ...$this->uploadedMedia($request, $locked, $stored),
                ])->save();

                $locked->amenities()->sync($request->input('amenities', []));
                $this->registerPendingPhotos($locked, $stored);
                if ($locked->owner_id && $locked->is_published) {
                    $locked->unsetRelation('amenities');
                    $locked->unsetRelation('accommodationTypes');
                    $ownership->assertPublishable($locked);
                }

                AuditLog::record('property.updated_by_admin', $locked, $previous, $locked->only(array_keys($previous)), [
                    'source' => $locked->owner_listing_id ? 'owner_submission' : 'admin_direct',
                ]);
            }, 3);
        } catch (\Throwable $e) {
            $this->discardUploadedMedia($stored);
            throw $e;
        }

        $property->refresh();
        if ($replacedCover && $replacedCover !== $property->cover_image
            && ! in_array($replacedCover, $property->gallery ?? [], true)) {
            ResponsiveImage::deleteDerivatives($replacedCover);
            Storage::disk('public')->delete($replacedCover);
        }
        $this->queueImageDerivatives($stored);

        return back()->with('status', 'Property and ownership changes saved.');
    }

    /**
     * Upload inside the database transaction, clean new blobs if that
     * transaction fails, and only remove old media after the commit.
     */
    private function uploadedMedia(Request $request, ?Property $property, array &$stored): array
    {
        $images = [];

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('properties/covers', 'public');
            if (! $path) {
                throw ValidationException::withMessages(['cover_image' => 'The photograph could not be stored.']);
            }
            $stored[] = $path;
            $images['cover_image'] = $path;
        }

        if ($request->hasFile('gallery')) {
            $gallery = $property?->gallery ?? [];
            foreach ($request->file('gallery') as $file) {
                $path = $file->store('properties/gallery', 'public');
                if (! $path) {
                    throw ValidationException::withMessages(['gallery' => 'One or more photographs could not be stored.']);
                }
                $stored[] = $path;
                $gallery[] = $path;
            }
            $images['gallery'] = array_values(array_unique($gallery));
        }

        return $images;
    }

    private function discardUploadedMedia(array $paths): void
    {
        if ($paths === []) {
            return;
        }
        Storage::disk('public')->delete($paths);
    }

    private function queueImageDerivatives(array $paths): void
    {
        foreach ($paths as $path) {
            GenerateResponsiveImageDerivatives::dispatch($path);
        }
    }

    private function registerPendingPhotos(Property $property, array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            PropertyPhotoModeration::query()->firstOrCreate(
                ['property_id' => $property->id,
                 'path_hash' => PropertyPhotoModeration::pathHash($path)],
                ['path' => $path, 'status' => 'pending']
            );
        }
    }

    private function formData(Property $property): array
    {
        $owners = User::query()
            ->where('is_admin', false)
            ->whereNull('staff_role')
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->whereHas('listingAgreements', fn ($agreements) => $agreements
                ->where('version', (string) SiteSetting::valueFor('owner_listing_agreement_version', '1.0'))
                ->whereNotNull('signed_at'))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email']);

        if ($property->owner_id && ! $owners->contains('id', (int) $property->owner_id)) {
            $owners->push($property->owner ?: User::query()->find($property->owner_id));
            $owners = $owners->filter();
        }

        return [
            'property' => $property,
            'owners' => $owners,
            'auditEvents' => $property->exists
                ? AuditLog::query()->where('subject_type', $property->getMorphClass())
                    ->where('subject_id', $property->getKey())
                    ->with('actor:id,name')
                    ->latest('id')->limit(20)->get()
                : collect(),
            'amenities' => Amenity::query()->orderBy('name')->get(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    private function validatedPayload(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_share_percentage' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'formatted_address' => ['required', 'string', 'max:1000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_region' => ['nullable', 'string', 'max:120'],
            'address_postal_code' => ['nullable', 'string', 'max:40'],
            'address_country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:100'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['required', Rule::in(array_keys((array)
                config('localization.supported_currencies', ['USD'=>'US Dollar'])))],
            'timezone' => ['nullable', 'timezone:all'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery' => ['nullable', 'array', 'max:25'],
            'gallery.*' => ['image', 'max:8192'],
            'amenities' => ['nullable', 'array', 'max:100'],
            'amenities.*' => ['integer', 'distinct', 'exists:amenities,id'],
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

        if (filled($data['slug'] ?? null)) {
            $slug = Str::slug($data['slug']);
            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'Enter a valid property slug.']);
            }
            $collision = Property::query()->where('slug', $slug)
                ->when($property?->exists, fn ($q) => $q->whereKeyNot($property->getKey()))
                ->exists();
            if ($collision) {
                throw ValidationException::withMessages(['slug' => 'That property URL is already in use.']);
            }
        } elseif ($property?->exists) {
            $slug = $property->slug;
        } else {
            $root = Str::slug($data['name']);
            if ($root === '') {
                throw ValidationException::withMessages(['name' => 'Enter a valid property name.']);
            }
            do {
                $slug = $root.'-'.Str::lower(Str::random(10));
            } while (Property::query()->where('slug', $slug)->exists());
        }
        $data['slug'] = $slug;

        $data['currency'] = strtoupper((string) $data['currency']);
        $data['timezone'] = $data['timezone'] ?: $location->timezone
            ?: config('localization.platform_timezone', config('azari.timezone', 'UTC'));
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');
        $data['same_day_booking'] = $request->boolean('same_day_booking');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if (! array_key_exists('owner_id', $data)) {
            $data['owner_id'] = $property?->owner_id;
        }
        unset($data['cover_image'], $data['gallery'], $data['amenities']);

        // The unique DB index is the final arbiter if two admins race.
        return $data;
    }
}
