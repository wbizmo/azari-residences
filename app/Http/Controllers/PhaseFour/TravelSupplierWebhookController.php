<?php

namespace App\Http\Controllers\PhaseFour;

use App\Http\Controllers\Controller;
use App\Models\TravelSupplier;
use App\Services\Travel\TravelSupplierWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TravelSupplierWebhookController extends Controller
{
    public function __invoke(Request $request, TravelSupplier $supplier, TravelSupplierWebhookService $inbox): JsonResponse
    {
        abort_unless(config('travel.webhooks_enabled', false), 404);

        $result = $inbox->capture(
            $supplier,
            $request->getContent(),
            $request->headers->all()
        );

        return response()->json($result, 202);
    }
}
