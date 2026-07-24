<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(): View
    {
        return view('admin.locations.index', [
            'locations' => Location::query()
                ->withCount('properties')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(config('azari.pagination.per_page', 10)),
        ]);
    }

    public function create(): View
    {
        return view('admin.locations.form', ['location' => new Location]);
    }

    public function store(Request $request): RedirectResponse
    {
        Location::query()->create($this->payload($request));

        return redirect()->route('azari.admin.locations.index')
            ->with('status', 'Location created.');
    }

    public function edit(Location $location): View
    {
        return view('admin.locations.form', compact('location'));
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $location->update($this->payload($request, $location));

        return back()->with('status', 'Location updated.');
    }

    private function payload(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:locations,slug,'.($location?->id ?? 'NULL')],
            'country' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', 'timezone'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
