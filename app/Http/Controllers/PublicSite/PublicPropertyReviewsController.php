<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use App\Services\Reviews\ReviewSummaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicPropertyReviewsController extends Controller
{
    public function __invoke(Request $request, Property $property, ReviewSummaryService $summaries): View
    {
        abort_unless(
            $property->is_published
            && ! in_array($property->status, ['inactive', 'unavailable', 'maintenance', 'archived'], true),
            404
        );

        $filters = $request->validate([
            'sort' => ['nullable', Rule::in(['recent', 'highest', 'lowest'])],
            'trip_type' => ['nullable', Rule::in(['business', 'couple', 'family', 'friends', 'solo', 'other'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $sort = $filters['sort'] ?? 'recent';
        $query = Review::query()
            ->where('property_id', $property->id)
            ->where('verified_stay', true)
            ->where('status', 'approved')
            ->with(['user:id,name'])
            ->withCount('helpfulVotes')
            ->when($filters['trip_type'] ?? null,
                fn ($query, $value) => $query->where('trip_type', $value));

        match ($sort) {
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at')->orderByDesc('id'),
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return view('public.properties.reviews', [
            'property' => $property,
            'reviews' => $query->paginate(12)->withQueryString(),
            'summary' => $summaries->forProperty($property->id),
            'filters' => $filters,
        ]);
    }
}
