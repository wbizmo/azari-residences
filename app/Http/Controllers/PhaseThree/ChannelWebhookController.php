<?php
namespace App\Http\Controllers\PhaseThree;
use App\Http\Controllers\Controller;
use App\Models\ChannelConnection;
use App\Services\PhaseThree\ChannelEventInboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChannelWebhookController extends Controller
{
    public function __invoke(Request $request, ChannelConnection $connection, ChannelEventInboxService $inbox): JsonResponse
    {
        $result = $inbox->capture($connection, $request->getContent(),
            (string) $request->header('X-Resavar-Event-Id'),
            (string) $request->header('X-Resavar-Timestamp'),
            (string) $request->header('X-Resavar-Signature'));
        return response()->json(['accepted' => true, 'duplicate' => $result['duplicate']], $result['duplicate'] ? 200 : 202);
    }
}
