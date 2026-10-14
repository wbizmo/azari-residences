<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResponsiveImageDerivatives;
use App\Support\ResponsiveImage;
use App\Models\Amenity;
use App\Models\ListingAgreement;
use App\Models\Location;
use App\Models\OwnerPayoutProfile;
use App\Models\PropertyListing;
use App\Models\AuditLog;
use App\Notifications\OwnerListingStatusNotification;
use App\Services\Owners\OwnerListingControlDocumentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Models\Property;
use App\Models\RoomType;
use App\Models\SiteSetting;
use App\Services\Owners\ListingCompletenessService;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'assignedProperties' => Property::query()->where('owner_id', $user->id)
                ->whereNull('owner_listing_id')->latest()->limit(6)->get(),
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
        ]);
    }

    public function signAgreement(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:180'],
            'agree' => ['accepted'],
        ]);

        $accountName = Str::of($user->name)->lower()->squish()->value();
        $signedName = Str::of($data['legal_name'])->lower()->squish()->value();

        if (! hash_equals($accountName, $signedName)) {
            return back()->withErrors(['legal_name' => 'The typed legal name must match the full name on your Resavar account.'])->withInput();
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
        $disk = Storage::disk('private');
        $relativePath = 'agreements/'.$agreement->id.'.pdf';
        $path = $disk->path($relativePath);

        if (! $disk->exists($relativePath)) {
            $disk->makeDirectory('agreements');
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view('user.owner.agreement-pdf', compact('agreement'))->render());
            $pdf->setPaper('A4');
            $pdf->render();
            if (! $disk->put($relativePath, $pdf->output())) {
                throw new \RuntimeException('Unable to store the private agreement PDF.');
            }
        }

        return response()->download($path, 'resavar-listing-agreement-'.$agreement->version.'.pdf');
    }

    public function index(Request $request): View
    {
        return view('user.owner.listings-index', [
            'listings' => $request->user()->propertyListings()->latest()->paginate(10)->withQueryString(),
            'assignedProperties' => Property::query()->where('owner_id', $request->user()->id)
                ->whereNull('owner_listing_id')->latest()->paginate(10, ['*'], 'assigned_page')->withQueryString(),
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
        return $this->saveListing($request, $completeness);
    }

    public function show(
        Request $request,
        PropertyListing $listing,
        ListingCompletenessService $completeness
    ): View {
        abort_unless((int) $listing->user_id === (int) $request->user()->id, 404);
        $listing->load(['controlDocuments', 'agreement', 'approvedProperty']);
        $completion = $completeness->evaluate($listing);

        return view('user.owner.listing-show', compact('listing', 'completion'));
    }

    public function edit(Request $request, PropertyListing $listing): View
    {
        abort_unless((int) $listing->user_id === (int) $request->user()->id
            && $listing->isEditable(), 404);

        return view('user.owner.listing-form', $this->formData($listing));
    }

    public function update(
        Request $request,
        PropertyListing $listing,
        ListingCompletenessService $completeness
    ): RedirectResponse {
        abort_unless((int) $listing->user_id === (int) $request->user()->id
            && $listing->isEditable(), 404);

        return $this->saveListing($request, $completeness, $listing);
    }

    private function saveListing(
        Request $request,
        ListingCompletenessService $completeness,
        ?PropertyListing $listing = null
    ): RedirectResponse {
        $agreement = $this->currentAgreement($request);
        abort_unless($agreement, 403);

        $intent = $request->input('intent', 'submit');
        if (! in_array($intent, ['draft', 'submit'], true)) {
            throw ValidationException::withMessages(['intent' => 'Choose Save draft or Submit for review.']);
        }

        $newPublicPaths = [];
        $oldPublicPaths = [];
        $newPrivatePaths = [];
        $payload = $this->validatedListingPayload($request, $listing, $intent, $newPublicPaths, $oldPublicPaths);

        try {
            $saved = DB::transaction(function () use (
                $request, $listing, $agreement, $intent, $payload,
                $completeness, &$newPrivatePaths
            ): PropertyListing {
                $locked = $listing ? PropertyListing::query()
                    ->whereKey($listing->getKey())->lockForUpdate()->firstOrFail() : new PropertyListing;

                abort_unless(! $locked->exists
                    || ((int) $locked->user_id === (int) $request->user()->id && $locked->isEditable()), 404);

                $previous = $locked->exists ? $locked->status : null;
                $locked->fill([
                    'user_id' => $request->user()->id,
                    'listing_agreement_id' => $agreement->id,
                    'operator_type' => $payload['operator_type'],
                    'business_legal_name' => $payload['business_legal_name'],
                    'business_registration_number' => $payload['business_registration_number'],
                    'business_email' => $payload['business_email'],
                    'business_phone' => $payload['business_phone'],
                    'business_address' => $payload['business_address'],
                    'listing_fingerprint' => $payload['listing_fingerprint'],
                    'status' => 'draft',
                    'property_data' => $payload['property_data'],
                    'amenity_ids' => $payload['amenity_ids'],
                    'cover_image' => $payload['cover_image'],
                    'gallery' => $payload['gallery'],
                    'proposed_owner_share_percentage' => $payload['proposed_owner_share_percentage'],
                    'owner_notes' => $payload['owner_notes'],
                ]);
                $locked->save();

                if ($request->hasFile('proof_of_control')) {
                    $proof = app(OwnerListingControlDocumentService::class)
                        ->append($locked, $request->file('proof_of_control'));
                    $newPrivatePaths[] = $proof->path;
                } elseif ($intent === 'submit' && $locked->operator_type !== null
                    && ! $locked->controlDocuments()->exists()) {
                    throw ValidationException::withMessages([
                        'proof_of_control' => 'Upload a valid proof of control before submitting for review.',
                    ]);
                }

                // An amended legal entity must submit fresh evidence. Prior
                // verified documents stay retained but no longer authorize it.
                if ($listing && (
                    $listing->operator_type !== $locked->operator_type
                    || $listing->business_legal_name !== $locked->business_legal_name
                    || $listing->business_registration_number !== $locked->business_registration_number
                )) {
                    $locked->controlDocuments()->where('status', 'verified')
                        ->update(['status' => 'superseded', 'updated_at' => now()]);
                }

                $completion = $completeness->sync($locked->load('user.ownerPayoutProfile'));
                if ($intent === 'submit') {
                    $mandatory = array_diff($completion['blockers'], ['amenities']);
                    if ($mandatory !== []) {
                        throw ValidationException::withMessages([
                            'listing' => 'Complete these sections before submission: '.implode(', ', $mandatory).'.',
                        ]);
                    }
                    $locked->forceFill([
                        'status' => 'submitted',
                        'submitted_at' => now(),
                        'decline_reason' => null,
                        'declined_at' => null,
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'approved_at' => null,
                    ])->save();
                } else {
                    $locked->forceFill(['submitted_at' => null])->save();
                }

                AuditLog::record('owner_listing.'.($intent === 'submit' ? 'submitted' : 'draft_saved'),
                    $locked, ['status' => $previous], ['status' => $locked->status],
                    ['operator_type' => $locked->operator_type, 'has_control_proof' => $locked->controlDocuments()->exists()]);

                return $locked;
            }, 1);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($newPublicPaths);
            Storage::disk('private')->delete($newPrivatePaths);
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'name' => 'You already have an application for this property. Edit that application instead.',
                ]);
            }
            throw $e;
        }

        foreach ($oldPublicPaths as $path) {
            if ($path) {
                ResponsiveImage::deleteDerivatives($path);
                Storage::disk('public')->delete($path);
            }
        }
        foreach ($newPublicPaths as $path) {
            GenerateResponsiveImageDerivatives::dispatch($path);
        }

        if ($intent === 'submit') {
            try {
                $request->user()->notify(OwnerListingStatusNotification::forListing(
                    $saved, 'Your property was submitted for Resavar staff review. You can track its status in Property Centre.'
                ));
            } catch (\Throwable $e) {
                Log::warning('Owner listing submission notification failed', [
                    'listing_id' => $saved->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('user.owner.listings.show', $saved)
            ->with('status', $intent === 'submit'
                ? 'Listing submitted for manual review. No property has been published yet.'
                : 'Private draft saved. Continue whenever you are ready.');
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


        $currency = (string) config('azari.currency', 'USD');
        $minimum = (float) SiteSetting::valueFor('owner_withdrawal_minimum', 50);
        $profile = $request->user()->ownerPayoutProfile;

        abort_unless($profile, 422, 'Configure your payout destination first.');
        abort_unless($profile->is_verified, 422, 'Your payout destination is awaiting Resavar verification.');

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

    private function validatedListingPayload(
        Request $request,
        ?PropertyListing $listing,
        string $intent,
        array &$newPublicPaths,
        array &$oldPublicPaths
    ): array {
        $draft = $intent === 'draft';
        if (! $request->has('intent')) {
            $request->merge(['intent' => $intent]);
        }
        $existing = $listing?->property_data ?? [];
        $keys = [
            'name', 'location_id', 'room_type_id', 'formatted_address',
            'address_line_1', 'address_line_2', 'address_city',
            'address_region', 'address_postal_code', 'address_country_code',
            'latitude', 'longitude', 'bedrooms', 'bathrooms', 'max_guests',
            'minimum_stay', 'maximum_stay', 'nightly_rate', 'weekend_rate',
            'cleaning_fee', 'service_charge', 'tax_rate', 'currency', 'timezone',
            'short_description', 'description',
        ];

        // Partial draft edits must retain fields that have already been
        // entered; a full resubmission validates the complete merged record.
        if ($listing) {
            $defaults = collect($existing)->only($keys)->all();
            foreach ([
                'operator_type', 'business_legal_name', 'business_registration_number',
                'business_email', 'business_phone', 'business_address',
                'owner_notes',
            ] as $key) {
                $defaults[$key] = $listing->{$key} ?? null;
            }
            $request->merge(array_replace($defaults, $request->except(['proof_of_control', 'cover_image', 'gallery'])));
        }
        if (! $request->filled('operator_type')) {
            $request->merge(['operator_type' => $listing?->operator_type ?: 'individual']);
        }

        $hotel = $request->input('operator_type') === 'hotel_operator';
        $required = $draft ? 'nullable' : 'required';
        $data = $request->validate([
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'operator_type' => ['required', Rule::in(['individual', 'hotel_operator'])],
            'business_legal_name' => [$hotel && ! $draft ? 'required' : 'nullable', 'string', 'max:180'],
            'business_registration_number' => [$hotel && ! $draft ? 'required' : 'nullable', 'string', 'max:100'],
            'business_email' => [$hotel && ! $draft ? 'required' : 'nullable', 'email', 'max:255'],
            'business_phone' => [$hotel && ! $draft ? 'required' : 'nullable', 'string', 'max:40'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'name' => [$required, 'string', 'max:180'],
            'location_id' => [$required, 'integer', 'exists:locations,id'],
            'room_type_id' => [$required, 'integer', 'exists:room_types,id'],
            'formatted_address' => [$required, 'string', 'max:1000'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_region' => ['nullable', 'string', 'max:120'],
            'address_postal_code' => ['nullable', 'string', 'max:40'],
            'address_country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'bedrooms' => [$required, 'integer', 'min:0', 'max:30'],
            'bathrooms' => [$required, 'integer', 'min:0', 'max:30'],
            'max_guests' => [$required, 'integer', 'min:1', 'max:100'],
            'minimum_stay' => ['nullable', 'integer', 'min:1', 'max:365'],
            'maximum_stay' => ['nullable', 'integer', 'min:1', 'max:730'],
            'nightly_rate' => [$required, 'numeric', 'min:0'],
            'weekend_rate' => ['nullable', 'numeric', 'min:0'],
            'cleaning_fee' => ['nullable', 'numeric', 'min:0'],
            'service_charge' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => [$required, Rule::in(array_keys((array)
                config('localization.supported_currencies', ['USD' => 'US Dollar'])))],
            'timezone' => ['nullable', 'timezone:all'],
            'short_description' => [$required, 'string', 'max:500'],
            'description' => [$required, 'string', 'max:20000'],
            'cover_image' => [$draft || $listing?->cover_image ? 'nullable' : 'required', 'image', 'max:8192'],
            'gallery' => ['nullable', 'array', 'max:25'],
            'gallery.*' => ['image', 'max:8192'],
            'remove_gallery' => ['nullable', 'array', 'max:25'],
            'remove_gallery.*' => ['string', 'max:1000'],
            'proof_of_control' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'amenities' => ['nullable', 'array', 'max:100'],
            'amenities.*' => ['integer', 'distinct', 'exists:amenities,id'],
            'owner_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! $draft && ! $request->hasFile('proof_of_control')
            && ! $listing?->controlDocuments()->exists()) {
            throw ValidationException::withMessages([
                'proof_of_control' => 'Upload a private proof of ownership or legal management authority.',
            ]);
        }

        $details = array_replace($existing, collect($data)->only($keys)->all());
        $location = filled($details['location_id'] ?? null)
            ? Location::query()->findOrFail($details['location_id']) : null;
        $roomType = filled($details['room_type_id'] ?? null)
            ? RoomType::query()->findOrFail($details['room_type_id']) : null;

        if ($location) {
            $details['location'] = $location->name;
            $details['country'] = $location->country;
        }
        if ($roomType) {
            $details['property_type'] = Str::lower($roomType->slug ?: $roomType->name);
        }
        $details['address_country_code'] = filled($details['address_country_code'] ?? null)
            ? strtoupper($details['address_country_code']) : null;
        if (filled($details['currency'] ?? null)) {
            $details['currency'] = strtoupper((string) $details['currency']);
        }
        if ($location) {
            $details['timezone'] = ($details['timezone'] ?? null) ?: $location->timezone
                ?: config('localization.platform_timezone', config('azari.timezone', 'UTC'));
        }
        $details['is_featured'] = false;
        $details['is_published'] = false;
        $details['same_day_booking'] = false;
        $details['sort_order'] = 0;

        $name = Str::lower(Str::squish((string) ($details['name'] ?? '')));
        $fingerprint = $name !== '' && $location
            ? hash('sha256', $name.'|'.$location->id)
            : null;
        if ($fingerprint && PropertyListing::query()
            ->where('user_id', $request->user()->id)
            ->where('listing_fingerprint', $fingerprint)
            ->when($listing?->exists, fn ($q) => $q->whereKeyNot($listing->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'name' => 'You already have an application for this property. Edit it instead of making another.',
            ]);
        }

        $cover = $listing?->cover_image;
        $gallery = $listing?->gallery ?? [];
        $remove = array_intersect($gallery, $data['remove_gallery'] ?? []);

        try {
            if ($request->hasFile('cover_image')) {
                $new = $request->file('cover_image')->store('owner-listings/covers', 'public');
                if (! $new) {
                    throw ValidationException::withMessages(['cover_image' => 'Unable to upload cover image.']);
                }
                $newPublicPaths[] = $new;
                if ($cover) {
                    $oldPublicPaths[] = $cover;
                }
                $cover = $new;
            }

            if ($remove !== []) {
                $gallery = array_values(array_diff($gallery, $remove));
                $oldPublicPaths = [...$oldPublicPaths, ...$remove];
            }

            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $image) {
                    $path = $image->store('owner-listings/gallery', 'public');
                    if (! $path) {
                        throw ValidationException::withMessages(['gallery' => 'Unable to upload gallery image.']);
                    }
                    $newPublicPaths[] = $path;
                    $gallery[] = $path;
                }
            }
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($newPublicPaths);
            throw $e;
        }

        return [
            'operator_type' => $data['operator_type'],
            'business_legal_name' => $hotel ? ($data['business_legal_name'] ?? null) : null,
            'business_registration_number' => $hotel ? ($data['business_registration_number'] ?? null) : null,
            'business_email' => $hotel ? ($data['business_email'] ?? null) : null,
            'business_phone' => $hotel ? ($data['business_phone'] ?? null) : null,
            'business_address' => $hotel ? ($data['business_address'] ?? null) : null,
            'listing_fingerprint' => $fingerprint,
            'property_data' => $details,
            'amenity_ids' => array_values($data['amenities'] ?? ($listing?->amenity_ids ?? [])),
            'cover_image' => $cover,
            'gallery' => array_values(array_unique($gallery)),
            'proposed_owner_share_percentage' => 100.00,
            'owner_notes' => $data['owner_notes'] ?? $listing?->owner_notes,
        ];
    }

    private function agreementText(): string
    {
        return <<<'TEXT'
By submitting a property to Resavar, I confirm that I am legally authorised to offer the property for accommodation and management. I authorise Resavar to review the property, contact me for verification, approve or decline the listing, receive guest payments, credit the applicable owner-property booking revenue to my account balance, and process eligible withdrawals through the payout destination I provide. I confirm that all information and documents supplied are accurate and understand that approval is not guaranteed. I agree to keep property availability, pricing, safety information, ownership authority, and payout details accurate at all times.
TEXT;
    }

    private function withdrawalOpen(): bool
    {
        $days = collect(explode(',', (string) SiteSetting::valueFor('owner_withdrawal_days', '1,2,3,4,5')))
            ->map(fn ($day) => (int) trim($day))->filter()->all();

        return in_array((int) now(config('localization.platform_timezone', 'UTC'))->dayOfWeekIso, $days, true);
    }
}
