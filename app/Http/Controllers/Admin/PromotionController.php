<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(): View
    {
        return view('admin.promotions.index', [
            'promotions' => Promotion::query()->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->booleans($request, $this->validated($request));
        $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(5));
        $data = $this->storeImage($request, $data);

        $promotion = Promotion::create($data);
        AuditLog::record('promotion.created', $promotion);

        return back()->with('success', 'Promotion created.');
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $before = $promotion->toArray();
        $data = $this->booleans($request, $this->validated($request));
        $data = $this->storeImage($request, $data, $promotion);
        $promotion->update($data);

        AuditLog::record('promotion.updated', $promotion, $before, $promotion->fresh()->toArray());

        return back()->with('success', 'Promotion updated.');
    }

    private function booleans(Request $request, array $data): array
    {
        $data['is_active'] = $request->boolean('is_active');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['show_on_homepage'] = $request->boolean('show_on_homepage');

        return $data;
    }

    private function storeImage(Request $request, array $data, ?Promotion $promotion = null): array
    {
        if (! $request->hasFile('image')) {
            return $data;
        }

        if ($promotion?->image_path) {
            Storage::disk('public')->delete($promotion->image_path);
        }

        $data['image_path'] = $request->file('image')->store('promotions', 'public');

        return $data;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:promotion,featured_offer,local_guide,testimonial,seasonal_message,booking_notice,homepage_section'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }
}
