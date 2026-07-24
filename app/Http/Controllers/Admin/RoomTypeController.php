<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoomTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.room-types.index', [
            'roomTypes' => RoomType::query()
                ->withCount('properties')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(config('azari.pagination.per_page', 10)),
        ]);
    }

    public function create(): View
    {
        return view('admin.room-types.form', ['roomType' => new RoomType]);
    }

    public function store(Request $request): RedirectResponse
    {
        RoomType::query()->create($this->payload($request));

        return redirect()->route('azari.admin.room-types.index')
            ->with('status', 'Residence category created.');
    }

    public function edit(RoomType $roomType): View
    {
        return view('admin.room-types.form', compact('roomType'));
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $roomType->update($this->payload($request, $roomType));

        return back()->with('status', 'Residence category updated.');
    }

    private function payload(Request $request, ?RoomType $roomType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', 'unique:room_types,slug,'.($roomType?->id ?? 'NULL')],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['icon'] = $data['icon'] ?: 'bed';
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
