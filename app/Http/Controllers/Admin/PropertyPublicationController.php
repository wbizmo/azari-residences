<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Notifications\OwnerListingStatusNotification;
use App\Services\Owners\PropertyPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
final class PropertyPublicationController extends Controller {
    public function review(Request $request, Property $property, PropertyPublicationService $service): RedirectResponse {
        $data = $request->validate([
            'review_note' => ['required', 'string', 'min:20', 'max:3000'],
            'identity_checked' => ['required', 'accepted'],
            'property_checked' => ['required', 'accepted'],
            'terms_checked' => ['required', 'accepted'],
        ]);
        $service->review($property, $request->user(), $data['review_note'], [
            'identity' => true, 'property' => true, 'terms' => true,
        ]);
        return back()->with('status', 'Property review approved. The listing is still a private draft until published.');
    }
    public function publish(Request $request, Property $property, PropertyPublicationService $service): RedirectResponse {
        $published = $service->publish($property, $request->user());
        $this->notifyOwner($published, 'Your accommodation was published after Resavar quality review.');
        return back()->with('status', 'Property published after review.');
    }
    public function unpublish(Request $request, Property $property, PropertyPublicationService $service): RedirectResponse {
        $data = $request->validate(['reason' => ['required','string','min:10','max:2000']]);
        $unpublished = $service->unpublish($property, $request->user(), $data['reason']);
        $this->notifyOwner($unpublished, 'Your accommodation was unpublished: '.$data['reason']);
        return back()->with('status', 'Property removed from public search. Historical bookings and IDs preserved.');
    }
    private function notifyOwner(Property $property, string $message): void {
        try {
            $listing = $property->ownerListing;
            if ($listing && $property->owner) {
                $property->owner->notify(OwnerListingStatusNotification::forListing($listing, $message));
            }
        } catch (\Throwable $e) {
            Log::warning('Property publication notification failed', [
                'property_id' => $property->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}