<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\TravelVoucher;
use App\Services\Travel\TravelVoucherService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TravelVoucherController extends Controller
{
    public function qr(Request $request, TravelVoucher $voucher, TravelVoucherService $service): Response
    {
        abort_unless(config('travel.fulfillment_enabled', false), 404);
        $token = $service->displayToken($voucher, $request->user());
        $svg = (new SvgWriter())->write(new QrCode(data: $token, size: 260))->getString();

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
