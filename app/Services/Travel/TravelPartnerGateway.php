<?php

namespace App\Services\Travel;

use App\Contracts\Travel\TravelSupplierAdapter;
use App\Models\TravelSupplier;
use Illuminate\Validation\ValidationException;

final class TravelPartnerGateway
{
    public function certifiedKeys(): array
    {
        return array_keys(array_filter((array) config('travel.supplier_adapters', []),
            fn ($class) => is_string($class)
                && class_exists($class)
                && is_subclass_of($class, TravelSupplierAdapter::class)));
    }

    public function supports(TravelSupplier $supplier): bool
    {
        return $supplier->isApproved()
            && filled($supplier->integration_key)
            && in_array($supplier->integration_key, $this->certifiedKeys(), true);
    }

    public function resolve(TravelSupplier $supplier): TravelSupplierAdapter
    {
        if (! $this->supports($supplier)) {
            throw ValidationException::withMessages([
                'supplier' => 'The supplier is not approved or lacks a certified booking adapter.',
            ]);
        }

        $class = config('travel.supplier_adapters.'.$supplier->integration_key);
        $adapter = app($class);
        if (! $adapter instanceof TravelSupplierAdapter) {
            throw ValidationException::withMessages([
                'supplier' => 'The supplier adapter is not certified for this product.',
            ]);
        }

        return $adapter;
    }
}
