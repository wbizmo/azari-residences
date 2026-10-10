<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\PropertyPhotoModeration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PropertyPhotoModerationController extends Controller
{
    public function update(
        Request $request,
        Property $property,
        PropertyPhotoModeration $photo
    ): RedirectResponse {
        abort_unless((int) $photo->property_id === (int) $property->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'alt_text' => ['required_if:status,approved', 'nullable', 'string', 'min:8', 'max:300'],
            'attribution' => ['required_if:status,approved', 'nullable', 'string', 'min:3', 'max:300'],
            'review_note' => ['required_if:status,rejected', 'nullable', 'string', 'max:1500'],
        ]);

        DB::transaction(function () use ($request, $property, $photo, $data): void {
            $locked = PropertyPhotoModeration::query()
                ->whereKey($photo->id)
                ->where('property_id', $property->id)
                ->lockForUpdate()->firstOrFail();

            $activePaths = collect([$property->fresh()->cover_image])
                ->merge($property->fresh()->gallery ?? [])->filter()->all();
            abort_unless(in_array($locked->path, $activePaths, true), 404);
            abort_unless(Storage::disk('public')->exists($locked->path), 422,
                'This image file is missing. Re-upload the image before approving it.');

            $before = $locked->only([
                'status', 'alt_text', 'attribution', 'review_note',
            ]);
            $locked->update([
                'status' => $data['status'],
                'alt_text' => $data['status'] === 'approved' ? trim($data['alt_text']) : null,
                'attribution' => $data['status'] === 'approved' ? trim($data['attribution']) : null,
                'review_note' => $data['review_note'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            AuditLog::record(
                'property_photo.reviewed', $locked, $before,
                $locked->only(array_keys($before)),
                ['property_id' => $property->id, 'reviewer' => $request->user()->id]
            );
        }, 3);

        return back()->with('status', 'Photo review saved and public media visibility updated.');
    }
}
