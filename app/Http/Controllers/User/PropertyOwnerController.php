<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResponsiveImageDerivatives;
use App\Support\ResponsiveImage;
use App\Models\Amenity;
use App\Models\IdentityVerification;
use App\Models\ListingAgreement;
use App\Models\Location;
use App\Models\OwnerPayoutProfile;
use App\Models\PropertyListing;
use App\Models\RoomType;
use App\Models\SiteSetting;
use App\Services\Owners\ListingCompletenessService;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PropertyOwnerController extends Controller
{
    public function dashboard(Request $request, OwnerBalanceService $balances): View
    {
        $currency = (string) config('azari.currency', 'USD');
        $user = $request->user();

        return view('user.owner.dashboard', [
            'listings' => $user->propertyListings()->latest()->take(5)->get(),
            'recentEntries' => $user->ownerLedgerEntries()->latest()->take(6)->get(),
            'balance' => $balances->balance($user, $currency),
            'pending' => $balances->pending($user, $currency),
            'available' => $balances->available($user, $currency),
            'currency' => $currency,
        ]);
    }

    public function agreement(Request $request): View
    {
        return view('user.owner.agreement', [
            'version' => (string) SiteSetting::valueFor('owner_listing_agreement_version', '1.0'),
            'agreementText' => $this->agreementText(),
            'identity' => $request->user()->currentIdentity,
            'dojahVerified' => IdentityVerification::userIsVerified($request->user()->id),
        ]);
    }

    public function signAgreement(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ((bool) config('azari.identity.dojah.enabled', false)) {
            abort_unless(IdentityVerification::userIsVerified($user->id), 422, 'Complete Dojah identity verification before signing the listing agreement.');
        } else {
            abort_unless($user->currentIdentity, 422, 'Upload your means of identification before signing the listing agreement.');
        }

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'agree' => ['accepted'],
        ]);

        $accountName = Str::of($user->name)->lower()->squish()->value();
        $signedName = Str::of($data['legal_name'])->lower()->squish()->value();

        if (! hash_equals($accountName, $signedName)) {
            return back()->withErrors(['legal_name' => 'The typed legal name must match the full name on your Resarva account.'])->withInput();
        }

        $version = (string) SiteSetting::valueFor('owner_listing_agreement_version', '1.0');
        $text = $this->agreementText();

        ListingAgreement::query()->updateOrCreate(
            ['user_id' => $user->id, 'version' => $version],
            [
                'legal_name' => $data['legal_name'],
                'agreement_text' => $text,
                'signature_hash' => hash('sha256', implode('|', [$user->id, $version, $data['legal_name'], $text])),
                'signed_ip' => $request->ip(),
                'signed_user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'signed_at' => now(),
            ]
        );

        return redirect()->route('user.owner.listings.create')->with('status', 'Listing agreement signed successfully.');
    }

    public function agreementDownload(Request $request): BinaryFileResponse
    {
        $agreement = $request->user()->listingAgreements()->latest('signed_at')->firstOrFail();
        $path = storage_path('app/private/agreements/'.$agreement->id.'.pdf');

        if (! is_file($path)) {
            Storage::disk('local')->makeDirectory('private/agreements');
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('user.owner.agreement-pdf', compact('agreement'))->render());
            $pdf->setPaper('A4');
            $pdf->render();
            file_put_contents($path, $pdf->output());
        }

        return response()->download($path, 'reserva-listing-agreement-'.$agreement->version.'.pdf');
    }

    public function index(Request $request): View
    {
        return view('user.owner.listings-index', [
            'listings' => $request->user()->propertyListings()->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->currentAgreement($request)) {
            return redirect()->route('user.owner.agreement');
        }

        return view('user.owner.listing-form', $this->formData(new PropertyListing));
    }

    public function store(Request $request, ListingCompletenessService $completeness): RedirectResponse
    {
        $agreement = $this->currentAgreement($request);
        abort_unless($agreement, 403);
        $payload = $this->validatedListingPayload($request);

        $listing = PropertyListing::query()->create([
            'user_id' => $request->user()->id,
            'listing_agreement_id' => $agreement->id,
            'status' => 'submitted',
            'property_data' => $payload['property_data'],
            'amenity_ids' => $payload['amenity_ids'],
            'cover_image' => $payload['cover_image'],
            'gallery' => $payload['gallery'],
            'proposed_owner_share_percentage' => $payload['proposed_owner_share_percentage'],
            'owner_notes' => $payload['owner_notes'],
            'submitted_at' => now(),
        ]);

        $completeness->sync($listing->load('user.ownerPayoutProfile'));

        return redirect()->route('user.owner.listings.show', $listing)->with('status', 'Property submitted for Resarva review.');
    }

    public function show(
        Request $request,
        PropertyListing $listing,
        ListingCompletenessService $completeness
    ): View {
        abort_unless($listing->user_id === $request->user()->id, 404);
        $listing->load('user.ownerPayoutProfile');
        $completion = $completeness->evaluate($listing);

        return view('user.owner.listing-show', compact('listing', 'completion'));
    }

    public function edit(Request $request, PropertyListing $listing): View
    {
        abort_unless($listing->user_id === $request->user()->id && $listing->isEditable(), 403);
        return view('user.owner.listing-form', $this->formData($listing));
    }

    public function update(Request $request, PropertyListing $listing, ListingCompletenessService $completeness): RedirectResponse
    {
        abort_unless($listing->user_id === $request->user()->id && $listing->isEditable(), 403);
        $payload = $this->validatedListingPayload($request, $listing);

        $listing->update([
            'status' => 'submitted',
            'property_data' => $payload['property_data'],
            'amenity_ids' => $payload['amenity_ids'],
            'cover_image' => $payload['cover_image'],
            'gallery' => $payload['gallery'],
            'proposed_owner_share_percentage' => $payload['proposed_owner_share_percentage'],
            'owner_notes' => $payload['owner_notes'],
            'decline_reason' => null,
            'submitted_at' => now(),
            'declined_at' => null,
        ]);

        $completeness->sync($listing->load('user.ownerPayoutProfile'));

        return redirect()->route('user.owner.listings.show', $listing)->with('status', 'Listing resubmitted for review.');
    }

    public function earnings(Request $request): View
    {
        return view('user.owner.earnings', [
            'entries' => $request->user()->ownerLedgerEntries()->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function withdrawals(Request $request, OwnerBalanceService $balances): View
    {
        $currency = (string) config('azari.currency', 'USD');

        return view('user.owner.withdrawals', [
            'withdrawals' => $request->user()->withdrawalRequests()->latest()->paginate(10)->withQueryString(),
            'profile' => $request->user()->ownerPayoutProfile ?: new OwnerPayoutProfile,
            'currency' => $currency,
            'available' => $balances->available($request->user(), $currency),
            'minimum' => (float) SiteSetting::valueFor('owner_withdrawal_minimum', 50),
            'paypalEnabled' => filter_var(SiteSetting::valueFor('owner_paypal_enabled', '0'), FILTER_VALIDATE_BOOL),
            'stripeEnabled' => filter_var(SiteSetting::valueFor('owner_stripe_enabled', '0'), FILTER_VALIDATE_BOOL),
            'withdrawalOpen' => $this->withdrawalOpen(),
            'dojahVerified' => IdentityVerification::userIsVerified($request->user()->id),
        ]);
    }

    public function updatePayoutProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferred_gateway' => ['required', Rule::in(['paypal', 'stripe'])],
            'paypal_recipient' => ['nullable', 'string', 'max:255'],
            'paypal_recipient_type' => ['nullable', Rule::in(['EMAIL', 'PHONE', 'PAYPAL_ID'])],
            'stripe_connected_account_id' => ['nullable', 'string', 'max:255', 'regex:/^acct_[A-Za-z0-9]+$/'],
        ]);

        if ($data['preferred_gateway'] === 'paypal' && blank($data['paypal_recipient'] ?? null)) {
            return back()->withErrors(['paypal_recipient' => 'Enter the PayPal payout recipient.']);
        }
        if ($data['preferred_gateway'] === 'stripe' && blank($data['stripe_connected_account_id'] ?? null)) {
            return back()->withErrors(['stripe_connected_account_id' => 'Enter the Stripe connected account ID.']);
        }

        $request->user()->ownerPayoutProfile()->updateOrCreate([], $data + ['is_verified' => false]);
        return back()->with('status', 'Withdrawal destination saved.');
    }

    public function requestWithdrawal(Request $request, OwnerWithdrawalService $withdrawals): RedirectResponse
    {
        abort_unless($this->withdrawalOpen(), 422, 'Withdrawals are not available today.');

        if ((bool) config('azari.identity.dojah.enabled', false)) {
            abort_unless(IdentityVerification::userIsVerified($request->user()->id), 422, 'Complete Dojah identity verification before requesting a withdrawal.');
        }

        $currency = (string) config('azari.currency', 'USD');
        $minimum = (float) SiteSetting::valueFor('owner_withdrawal_minimum', 50);
        $profile = $request->user()->ownerPayoutProfile;

        abort_unless($profile, 422, 'Configure your payout destination first.');
        abort_unless($profile->is_verified, 422, 'Your payout destination is awaiting Resarva verification.');

        $gatewayEnabled = $profile->preferred_gateway === 'paypal'
            ? filter_var(SiteSetting::valueFor('owner_paypal_enabled', '0'), FILTER_VALIDATE_BOOL)
            : filter_var(SiteSetting::valueFor('owner_stripe_enabled', '0'), FILTER_VALIDATE_BOOL);
        abort_unless($gatewayEnabled, 422, 'The selected payout gateway is currently unavailable.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minimum],
            'owner_note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $withdrawals->request($request->user(), $profile, $currency, (float) $data['amount'], $data['owner_note'] ?? null);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['amount' => $exception->getMessage()])->withInput();
        }

        return back()->with('status', 'Withdrawal request submitted and the amount has been reserved.');
    }

    private function currentAgreement(Request $request): ?ListingAgreement
    {
        $version = (string) SiteSetting::valueFor('owner_listing_agreement_version', '1.0');
        return $request->user()->listingAgreements()->where('version', $version)->first();
    }

    private function formData(PropertyListing $listing): array
    {
        return [
            'listing' => $listing,
            'data' => $listing->property_data ?? [],
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'amenities' => Amenity::query()->orderBy('name')->get(),
            'currency' => (string) config('localization.default_currency', config('azari.currency', 'USD')),
            'currencies' => (array) config('localization.supported_currencies', ['USD'=>'US Dollar']),
        ];
    }

    private function validatedListingPayload(Request $request, ?PropertyListing $listing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
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
            'currency' => ['required', Rule::in(array_keys((array) config('localization.supported_currencies', ['USD'=>'US Dollar'])))],
            'timezone' => ['nullable', 'timezone:all'],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:20000'],
            'cover_image' => [$listing?->cover_image ? 'nullable' : 'required', 'image', 'max:8192'],
            'gallery.*' => ['nullable', 'image', 'max:8192'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string', 'max:1000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'owner_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $location = Location::query()->findOrFail($data['location_id']);
        $roomType = RoomType::query()->findOrFail($data['room_type_id']);

        $propertyData = collect($data)->except(['cover_image', 'gallery', 'remove_gallery', 'amenities', 'owner_notes'])->all();
        $propertyData['location'] = $location->name;
        $propertyData['country'] = $location->country;
        $propertyData['address_country_code'] = filled($propertyData['address_country_code'] ?? null) ? strtoupper($propertyData['address_country_code']) : null;
        $propertyData['property_type'] = Str::lower($roomType->slug ?: $roomType->name);
        $propertyData['currency'] = strtoupper((string) $data['currency']);
        $propertyData['timezone'] = $data['timezone'] ?: $location->timezone ?: config('localization.platform_timezone', config('azari.timezone', 'UTC'));
        $propertyData['is_featured'] = false;
        $propertyData['is_published'] = false;
        $propertyData['same_day_booking'] = false;
        $propertyData['sort_order'] = 0;

        $cover = $listing?->cover_image;
        if ($request->hasFile('cover_image')) {
            if ($cover) {
                ResponsiveImage::deleteDerivatives($cover);
                Storage::disk('public')->delete($cover);
            }
            $cover = $request->file('cover_image')->store('owner-listings/covers', 'public');
            GenerateResponsiveImageDerivatives::dispatch($cover);
        }

        $gallery = $listing?->gallery ?? [];
        $removeGallery = array_values(array_intersect($gallery, $data['remove_gallery'] ?? []));
        if ($removeGallery !== []) {
            foreach ($removeGallery as $removedImage) ResponsiveImage::deleteDerivatives($removedImage);
            Storage::disk('public')->delete($removeGallery);
            $gallery = array_values(array_diff($gallery, $removeGallery));
        }
        if ($request->hasFile('gallery')) {
            $uploadedGallery = collect($request->file('gallery'))->map(function ($image) {
                $path = $image->store('owner-listings/gallery', 'public');
                GenerateResponsiveImageDerivatives::dispatch($path);
                return $path;
            })->all();
            $gallery = array_values([...$gallery, ...$uploadedGallery]);
        }

        return [
            'property_data' => $propertyData,
            'amenity_ids' => array_values($data['amenities'] ?? []),
            'cover_image' => $cover,
            'gallery' => $gallery,
            'proposed_owner_share_percentage' => 100.00,
            'owner_notes' => $data['owner_notes'] ?? null,
        ];
    }

    private function agreementText(): string
    {
        return <<<'TEXT'
By submitting a property to Resarva, I confirm that I am legally authorised to offer the property for accommodation and management. I authorise Resarva to review the property, contact me for verification, approve or decline the listing, receive guest payments, credit the applicable owner-property booking revenue to my account balance, and process eligible withdrawals through the payout destination I provide. I confirm that all information and documents supplied are accurate and understand that approval is not guaranteed. I agree to keep property availability, pricing, safety information, ownership authority, and payout details accurate at all times.
TEXT;
    }

    private function withdrawalOpen(): bool
    {
        $days = collect(explode(',', (string) SiteSetting::valueFor('owner_withdrawal_days', '1,2,3,4,5')))
            ->map(fn ($day) => (int) trim($day))->filter()->all();

        return in_array((int) now(config('localization.platform_timezone', 'UTC'))->dayOfWeekIso, $days, true);
    }
}
