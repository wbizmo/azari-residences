<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Search\PropertyStayPriceCalendar;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyStayPriceCalendarController extends Controller
{
    public function __invoke(
        Request $request,
        Property $property,
        PropertyStayPriceCalendar $calendar
    ): View {
        abort_unless($property->is_published
            && ! in_array($property->status, ['inactive', 'unavailable', 'maintenance', 'archived'], true), 404);

        $options = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'nights' => ['nullable', 'integer', 'min:1', 'max:14'],
            'adults' => ['nullable', 'integer', 'min:1', 'max:12'],
            'children' => ['nullable', 'integer', 'min:0', 'max:8'],
            'rooms' => ['nullable', 'integer', 'min:1', 'max:20'],
            'accommodation_type_id' => ['nullable', 'integer', 'min:1'],
            'rate_plan_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $options['month'] ??= CarbonImmutable::now(
            LocalDate::propertyTimezone($property)
        )->format('Y-m');
        $options['nights'] ??= 1;
        $options['adults'] ??= 2;
        $options['children'] ??= 0;
        $options['rooms'] ??= 1;
        $prices = $calendar->month($property, $options);

        return view('public.propertystay-price-calendar', compact('property', 'options', 'prices'));
    }
}
