<?php

use Illuminate\Support\Facades\Route;

$azariHomeSection = static function (string $section) {
    return redirect(route('home').'#'.$section);
};

if (! Route::has('public.apartments')) {
    Route::get('/apartments', fn () => $azariHomeSection('apartments'))->name('public.apartments');
}
if (! Route::has('public.rooms')) {
    Route::get('/rooms', fn () => $azariHomeSection('rooms'))->name('public.rooms');
}
if (! Route::has('public.services')) {
    Route::get('/services', fn () => $azariHomeSection('services'))->name('public.services');
}
if (! Route::has('public.concierge')) {
    Route::get('/concierge', fn () => $azariHomeSection('concierge'))->name('public.concierge');
}
if (! Route::has('public.housekeeping')) {
    Route::get('/housekeeping', fn () => $azariHomeSection('housekeeping'))->name('public.housekeeping');
}
if (! Route::has('public.restaurant')) {
    Route::get('/restaurant', fn () => $azariHomeSection('restaurant'))->name('public.restaurant');
}
if (! Route::has('public.airport-transfers')) {
    Route::get('/airport-transfers', fn () => $azariHomeSection('airport-transfers'))->name('public.airport-transfers');
}
if (! Route::has('public.local-guide')) {
    Route::get('/local-guide', fn () => $azariHomeSection('local-guide'))->name('public.local-guide');
}
if (! Route::has('public.about')) {
    Route::get('/about-azari', fn () => $azariHomeSection('about'))->name('public.about');
}
if (! Route::has('public.contact')) {
    Route::get('/contact', fn () => $azariHomeSection('contact'))->name('public.contact');
}
if (! Route::has('public.support')) {
    Route::get('/support', fn () => $azariHomeSection('support'))->name('public.support');
}
if (! Route::has('public.book-now')) {
    Route::get('/book-now', fn () => $azariHomeSection('availability'))->name('public.book-now');
}
