<?php

use App\Http\Controllers\Admin\Cms\CmsController;
use App\Http\Controllers\Admin\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['azari.staff'])
    ->prefix('azari-admin')
    ->name('azari.admin.')
    ->group(function (): void {
        Route::get('/cms', [CmsController::class, 'index'])->name('cms.index');
        Route::put('/cms/branding', [CmsController::class, 'branding'])->name('cms.branding');
        Route::put('/cms/promotion', [CmsController::class, 'promotion'])->name('cms.promotion');
        Route::post('/cms/themes', [CmsController::class, 'theme'])->name('cms.themes.store');
        Route::post('/cms/navigation', [CmsController::class, 'navigationStore'])->name('cms.navigation.store');
        Route::delete('/cms/navigation/{navigationItem}', [CmsController::class, 'navigationDelete'])->name('cms.navigation.delete');
        Route::put('/cms/navigation-sort', [CmsController::class, 'navigationSort'])->name('cms.navigation.sort');
        Route::post('/cms/sections', [CmsController::class, 'sectionStore'])->name('cms.sections.store');
        Route::delete('/cms/sections/{homepageSection}', [CmsController::class, 'sectionDelete'])->name('cms.sections.delete');
        Route::put('/cms/section-sort', [CmsController::class, 'sectionSort'])->name('cms.sections.sort');
        Route::put('/cms/seo', [CmsController::class, 'seo'])->name('cms.seo');
        Route::post('/cms/media', [CmsController::class, 'mediaStore'])->name('cms.media.store');
        Route::put('/cms/media/{mediaAsset}/archive', [CmsController::class, 'mediaArchive'])->name('cms.media.archive');

        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/locations', [InventoryController::class, 'locationStore'])->name('inventory.locations.store');
        Route::post('/inventory/buildings', [InventoryController::class, 'buildingStore'])->name('inventory.buildings.store');
        Route::post('/inventory/room-types', [InventoryController::class, 'roomTypeStore'])->name('inventory.room-types.store');
        Route::post('/inventory/amenities', [InventoryController::class, 'amenityStore'])->name('inventory.amenities.store');
        Route::delete('/inventory/amenities/{amenity}', [InventoryController::class, 'amenityDelete'])->name('inventory.amenities.delete');

        Route::get('/inventory/properties/create', [InventoryController::class, 'propertyCreate'])->name('inventory.properties.create');
        Route::post('/inventory/properties', [InventoryController::class, 'propertyStore'])->name('inventory.properties.store');
        Route::get('/inventory/properties/{property}/edit', [InventoryController::class, 'propertyEdit'])->name('inventory.properties.edit');
        Route::put('/inventory/properties/{property}', [InventoryController::class, 'propertyUpdate'])->name('inventory.properties.update');
        Route::delete('/inventory/properties/{property}', [InventoryController::class, 'propertyDelete'])->name('inventory.properties.delete');

        Route::delete('/inventory/images/{propertyImage}', [InventoryController::class, 'imageDelete'])->name('inventory.images.delete');
        Route::put('/inventory/properties/{property}/images-sort', [InventoryController::class, 'imageSort'])->name('inventory.images.sort');

        Route::post('/inventory/properties/{property}/pricing', [InventoryController::class, 'pricingStore'])->name('inventory.pricing.store');
        Route::delete('/inventory/pricing/{pricingRule}', [InventoryController::class, 'pricingDelete'])->name('inventory.pricing.delete');
    });
