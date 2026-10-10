<?php

use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\RoomTypeController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\PublicSite\AvailabilitySearchController;
use App\Http\Controllers\PublicSite\DestinationSearchController;
use App\Http\Controllers\PublicSite\AzariAvailabilityController;
use App\Http\Controllers\PublicSite\PropertyStayPriceCalendarController;
use App\Http\Controllers\PublicSite\PublicPageController;
use App\Http\Controllers\UserArea\BookingShareController;
use App\Http\Controllers\PublicSite\PublicPropertyReviewsController;
use Illuminate\Support\Facades\Route;

Route::get('/stay-share/{token}', [BookingShareController::class, 'show'])
    ->middleware('throttle:60,1')->name('public.booking-share.show');

Route::get('/availability', [PublicPageController::class, 'availability'])
    ->name('availability.index');
Route::get('/residences/{property}/availability', [PublicPageController::class, 'propertyAvailability'])
    ->name('availability.property');
Route::get('/residences/{property}/price-calendar', PropertyStayPriceCalendarController::class)
    ->middleware('throttle:12,1')->name('properties.price-calendar');

Route::get('/residences/{property}/reviews', PublicPropertyReviewsController::class)
    ->middleware('throttle:60,1')->name('properties.reviews');

Route::get('/availability/results', [AzariAvailabilityController::class, 'index'])
    ->name('availability.results');
Route::get('/availability/map-cursor', [AzariAvailabilityController::class, 'mapCursor'])
    ->middleware('throttle:30,1')
    ->name('availability.map-cursor');
Route::get('/availability/map-points', [AzariAvailabilityController::class, 'mapPoints'])
    ->middleware('throttle:30,1')
    ->name('availability.map-points');

Route::get('/availability/search', AvailabilitySearchController::class)
    ->name('availability.search');

Route::get('/destinations/suggest', DestinationSearchController::class)
    ->middleware('throttle:30,1')
    ->name('destinations.suggest');

Route::get('/book-now', [PublicPageController::class, 'availability'])
    ->name('public.book-now');

Route::get('/apartments', [PublicPageController::class, 'apartments'])
    ->name('public.apartments');

Route::get('/rooms', [PublicPageController::class, 'rooms'])
    ->name('public.rooms');

foreach ([
    'services' => 'services',
    'concierge' => 'concierge',
    'housekeeping' => 'housekeeping',
    'restaurant' => 'restaurant',
    'airport-transfers' => 'airport-transfers',
    'local-guide' => 'local-guide',
    'about-reserva' => 'about',
    'contact' => 'contact',
    'support' => 'support',
    'booking-terms' => 'booking-terms',
    'cancellation-policy' => 'cancellation-policy',
    'privacy-policy' => 'privacy-policy',
    'terms-and-conditions' => 'terms',
] as $uri => $key) {
    Route::get('/'.$uri, fn (PublicPageController $controller) => $controller->page($key))
        ->name('public.'.$key);
}

Route::prefix('azaridevadmin')
    ->name('azari.admin.')
    ->middleware(['azari.staff', 'azari.staff:administrator'])
    ->group(function (): void {
        Route::resource('locations', LocationController::class)
            ->except(['show', 'destroy'])
            ->names('locations');

        Route::post('reviews/{review}/appeal', [AdminReviewController::class, 'decideAppeal'])
            ->middleware('throttle:20,1')
            ->name('reviews.appeal');

        Route::resource('room-types', RoomTypeController::class)
            ->except(['show', 'destroy'])
            ->parameters(['room-types' => 'roomType'])
            ->names('room-types');
    });

Route::redirect('/about-azari', '/about-reserva', 301);

Route::post('/contact', [PublicPageController::class, 'contact'])
    ->middleware('throttle:6,1')
    ->name('public.contact.submit');
