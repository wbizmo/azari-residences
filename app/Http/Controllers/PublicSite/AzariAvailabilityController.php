<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Property;
use App\Models\RoomType;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AzariAvailabilityController extends Controller
{
    public function index(
        Request $request,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ): View {
        $filters = $request->validate([
            'check_in' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'check_out' => [
                'required',
                'date',
                'after:check_in',
            ],
            'adults' => [
                'required',
                'integer',
                'min:1',
                'max:40',
            ],
            'children' => [
                'nullable',
                'integer',
                'min:0',
                'max:40',
            ],
            'rooms' => [
                'nullable',
                'integer',
                'min:1',
                'max:20',
            ],
            'location_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
            ],
            'location' => [
                'nullable',
                'string',
                'max:120',
            ],
            'room_type_id' => [
                'nullable',
                'integer',
                'exists:room_types,id',
            ],
            'property_type' => [
                'nullable',
                'string',
                'max:80',
            ],
            'property_id' => [
                'nullable',
                'integer',
                'exists:properties,id',
            ],
        ]);

        $checkIn = CarbonImmutable::parse(
            $filters['check_in']
        )->startOfDay();

        $checkOut = CarbonImmutable::parse(
            $filters['check_out']
        )->startOfDay();

        $adults = (int) $filters['adults'];
        $children = (int) (
            $filters['children']
            ?? 0
        );
        $rooms = (int) (
            $filters['rooms']
            ?? 1
        );

        $locations = Location::query()
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'properties',
                fn (Builder $query) =>
                    $this->applyPublicInventory(
                        $query
                    )
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $roomTypes = RoomType::query()
            ->where(
                'is_active',
                true
            )
            ->whereHas(
                'properties',
                fn (Builder $query) =>
                    $this->applyPublicInventory(
                        $query
                    )
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $filterNotices = [];

        if (
            ! empty(
                $filters['location_id']
            )
            && ! $locations->contains(
                'id',
                (int) $filters['location_id']
            )
        ) {
            $label = Location::query()
                ->find(
                    $filters['location_id']
                )
                ?->name
                ?? 'Selected location';

            $filterNotices[] =
                "{$label} currently has no published "
                ."residence assigned to it, so that "
                ."filter was removed.";

            unset(
                $filters['location_id']
            );
        }

        if (
            ! empty(
                $filters['room_type_id']
            )
            && ! $roomTypes->contains(
                'id',
                (int) $filters['room_type_id']
            )
        ) {
            $label = RoomType::query()
                ->find(
                    $filters['room_type_id']
                )
                ?->name
                ?? 'Selected category';

            $filterNotices[] =
                "{$label} currently has no published "
                ."residence assigned to it, so that "
                ."filter was removed.";

            unset(
                $filters['room_type_id']
            );
        }

        $request->session()->put(
            'azari_stay_search',
            [
                'check_in' =>
                    $filters['check_in'],
                'check_out' =>
                    $filters['check_out'],
                'adults' =>
                    $adults,
                'children' =>
                    $children,
                'rooms' =>
                    $rooms,
                'location_id' =>
                    $filters['location_id']
                    ?? null,
                'room_type_id' =>
                    $filters['room_type_id']
                    ?? null,
            ]
        );

        $query = $this
            ->publicInventoryQuery()
            ->with([
                'locationRecord',
                'roomType',
                'amenities',
            ]);

        if (
            ! empty(
                $filters['property_id']
            )
        ) {
            $query->whereKey(
                (int) $filters['property_id']
            );
        } else {
            $query
                ->when(
                    $filters['location_id']
                    ?? null,
                    fn (
                        Builder $builder,
                        $locationId
                    ) =>
                        $builder->where(
                            'location_id',
                            $locationId
                        )
                )
                ->when(
                    $filters['room_type_id']
                    ?? null,
                    fn (
                        Builder $builder,
                        $roomTypeId
                    ) =>
                        $builder->where(
                            'room_type_id',
                            $roomTypeId
                        )
                )
                ->when(
                    $filters['property_type']
                    ?? null,
                    fn (
                        Builder $builder,
                        $type
                    ) =>
                        $builder->whereRaw(
                            'LOWER(property_type) = ?',
                            [
                                mb_strtolower(
                                    $type
                                ),
                            ]
                        )
                );
        }

        $properties = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $ruleFailures = [];

        $results = $properties
            ->filter(
                function (
                    Property $property
                ) use (
                    $availability,
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    $rooms,
                    &$ruleFailures
                ): bool {
                    try {
                        $availability
                            ->assertRules(
                                $property,
                                $checkIn,
                                $checkOut,
                                $adults,
                                $children,
                                $rooms
                            );
                    } catch (
                        ValidationException $exception
                    ) {
                        $message = collect(
                            $exception->errors()
                        )
                            ->flatten()
                            ->first();

                        if ($message) {
                            $ruleFailures[] =
                                $message;
                        }

                        return false;
                    }

                    return $availability
                        ->available(
                            $property->getKey(),
                            $checkIn,
                            $checkOut
                        );
                }
            )
            ->map(
                fn (
                    Property $property
                ) => [
                    'property' =>
                        $property,
                    'quote' =>
                        $pricing->quote(
                            $property,
                            $checkIn,
                            $checkOut
                        ),
                ]
            )
            ->values();

        $availableLocations = $results
            ->map(
                fn (
                    array $result
                ) =>
                    $result['property']
                        ->locationRecord
            )
            ->filter()
            ->unique('id')
            ->values();

        $emptyState = null;

        if ($results->isEmpty()) {
            if ($properties->isEmpty()) {
                $emptyState =
                    'inventory';
            } elseif (
                count($ruleFailures)
                >= $properties->count()
            ) {
                $emptyState =
                    'rules';
            } else {
                $emptyState =
                    'dates';
            }
        }

        $alternatives = collect();

        if (
            $emptyState === 'dates'
        ) {
            $alternatives =
                $this->findAlternatives(
                    $properties,
                    $availability,
                    $pricing,
                    $checkIn,
                    $checkOut,
                    $adults,
                    $children,
                    $rooms
                );
        }

        return view(
            'public.bookings.availability',
            [
                'results' =>
                    $results,
                'alternatives' =>
                    $alternatives,
                'availableLocations' =>
                    $availableLocations,
                'locations' =>
                    $locations,
                'roomTypes' =>
                    $roomTypes,
                'filters' =>
                    $filters,
                'filterNotices' =>
                    $filterNotices,
                'emptyState' =>
                    $emptyState,
                'ruleFailure' =>
                    collect(
                        $ruleFailures
                    )
                        ->unique()
                        ->first(),
            ]
        );
    }

    public function hold(
        Request $request,
        Property $property,
        AzariAvailabilityEngine $availability
    ): RedirectResponse {
        $data = $request->validate([
            'check_in' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'check_out' => [
                'required',
                'date',
                'after:check_in',
            ],
            'adults' => [
                'required',
                'integer',
                'min:1',
            ],
            'children' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'rooms' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ]);

        abort_unless(
            $property->is_published
            && ! in_array(
                $property->status,
                [
                    'inactive',
                    'unavailable',
                    'maintenance',
                    'archived',
                ],
                true
            ),
            404
        );

        $remembered = (array) $request
            ->session()
            ->get(
                'azari_stay_search',
                []
            );

        $request->session()->put(
            'azari_stay_search',
            array_merge(
                $remembered,
                [
                    'check_in' =>
                        $data['check_in'],
                    'check_out' =>
                        $data['check_out'],
                    'adults' =>
                        (int) $data['adults'],
                    'children' =>
                        (int) (
                            $data['children']
                            ?? 0
                        ),
                    'rooms' =>
                        (int) (
                            $data['rooms']
                            ?? 1
                        ),
                ]
            )
        );

        $hold = $availability->hold(
            $property,
            CarbonImmutable::parse(
                $data['check_in']
            ),
            CarbonImmutable::parse(
                $data['check_out']
            ),
            (int) $data['adults'],
            (int) (
                $data['children']
                ?? 0
            ),
            (int) (
                $data['rooms']
                ?? 1
            ),
            $request->user()?->getKey()
        );

        return redirect()->route(
            'azari.booking.checkout',
            $hold->token
        );
    }

    public function quote(
        Request $request,
        Property $property,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing
    ) {
        $data = $request->validate([
            'check_in' => [
                'required',
                'date',
                'after_or_equal:today',
            ],
            'check_out' => [
                'required',
                'date',
                'after:check_in',
            ],
            'adults' => [
                'required',
                'integer',
                'min:1',
            ],
            'children' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'rooms' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'add_ons' => [
                'nullable',
                'array',
            ],
        ]);

        $checkIn =
            CarbonImmutable::parse(
                $data['check_in']
            );

        $checkOut =
            CarbonImmutable::parse(
                $data['check_out']
            );

        $availability->assertRules(
            $property,
            $checkIn,
            $checkOut,
            (int) $data['adults'],
            (int) (
                $data['children']
                ?? 0
            ),
            (int) (
                $data['rooms']
                ?? 1
            )
        );

        return response()->json([
            'available' =>
                $availability->available(
                    $property->getKey(),
                    $checkIn,
                    $checkOut
                ),
            'quote' =>
                $pricing->quote(
                    $property,
                    $checkIn,
                    $checkOut,
                    $data['add_ons']
                    ?? []
                ),
        ]);
    }

    private function publicInventoryQuery(): Builder
    {
        return $this->applyPublicInventory(
            Property::query()
        );
    }

    private function applyPublicInventory(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'is_published',
                true
            )
            ->where(
                function (
                    Builder $status
                ): void {
                    $status
                        ->whereNull(
                            'status'
                        )
                        ->orWhereNotIn(
                            'status',
                            [
                                'inactive',
                                'unavailable',
                                'maintenance',
                                'archived',
                            ]
                        );
                }
            );
    }

    private function findAlternatives(
        $properties,
        AzariAvailabilityEngine $availability,
        AzariPricingEngine $pricing,
        CarbonImmutable $requestedIn,
        CarbonImmutable $requestedOut,
        int $adults,
        int $children,
        int $rooms
    ) {
        $nights = max(
            1,
            $requestedIn->diffInDays(
                $requestedOut
            )
        );

        $alternatives = collect();

        foreach (
            $properties
            as $property
        ) {
            for (
                $offset = 1;
                $offset <= 30;
                $offset++
            ) {
                $checkIn =
                    $requestedIn->addDays(
                        $offset
                    );

                $checkOut =
                    $checkIn->addDays(
                        $nights
                    );

                try {
                    $availability
                        ->assertRules(
                            $property,
                            $checkIn,
                            $checkOut,
                            $adults,
                            $children,
                            $rooms
                        );
                } catch (
                    ValidationException
                ) {
                    continue;
                }

                if (
                    $availability
                        ->available(
                            $property
                                ->getKey(),
                            $checkIn,
                            $checkOut
                        )
                ) {
                    $alternatives->push([
                        'property' =>
                            $property,
                        'check_in' =>
                            $checkIn,
                        'check_out' =>
                            $checkOut,
                        'quote' =>
                            $pricing->quote(
                                $property,
                                $checkIn,
                                $checkOut
                            ),
                    ]);

                    break;
                }
            }
        }

        return $alternatives
            ->take(6)
            ->values();
    }
}
