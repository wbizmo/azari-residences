<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Property;
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
            'properties' => Property::query()->with('amenities')->orderBy('sort_order')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.properties.form', [
            'property' => new Property,
            'amenities' => Amenity::query()->orderBy('name')->get(),
        ]);
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

        return view('admin.properties.form', [
            'property' => $property,
            'amenities' => Amenity::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $property->update($this->validatedPayload($request, $property));
        $property->amenities()->sync($request->input('amenities', []));

        return back()->with('status', 'Property updated.');
    }

    private function validatedPayload(Request $request, ?Property $property = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'location' => ['required', 'string', 'max:180'],
            'country' => ['required', 'string', 'max:100'],
            'property_type' => ['required', 'string', 'max:80'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:30'],
            'bathrooms' => ['required', 'integer', 'min:1', 'max:30'],
            'max_guests' => ['required', 'integer', 'min:1', 'max:100'],
            'nightly_rate' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cover_image' => ['nullable', 'image', 'max:8192'],
            'gallery.*' => ['nullable', 'image', 'max:8192'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug($data['slug'])
            : Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        $data['currency'] = strtoupper($data['currency']);
        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_published'] = $request->boolean('is_published');
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
