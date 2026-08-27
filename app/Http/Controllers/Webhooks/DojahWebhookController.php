<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Identity\DojahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DojahWebhookController extends Controller
{
    public function __invoke(Request $request, DojahService $dojah): JsonResponse
    {
        if (! $dojah->enabled()) {
            return response()->json(['received' => false], 503);
        }

        $rawBody = (string) $request->getContent();
        $signatureHeader = (string) config('azari.identity.dojah.webhook_signature_header', 'x-dojah-signature');
        $signatureV2Header = (string) config('azari.identity.dojah.webhook_signature_v2_header', 'x-dojah-signature-v2');

        if (! $dojah->verifyWebhookSignature(
            $rawBody,
            $request->header($signatureHeader),
            $request->header($signatureV2Header),
        )) {
            return response()->json(['received' => false], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['received' => false], 422);
        }

        $verification = $dojah->processWebhook($payload, $rawBody);

        return response()->json([
            'received' => true,
            'matched' => $verification !== null,
        ], $verification ? 200 : 202);
    }
}
