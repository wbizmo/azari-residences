<?php

namespace App\Services\Communication;

use Illuminate\Http\Request;

final class TwilioRequestValidator
{
    public function valid(Request $request): bool
    {
        if (! config('services.twilio.validate_webhooks', true)) {
            return ! app()->environment('production');
        }

        $token = (string) config('services.twilio.token');
        $signature = (string) $request->header('X-Twilio-Signature');

        if ($token === '' || $signature === '') {
            return false;
        }

        $url = (string) config('services.twilio.status_callback');

        if ($url === '') {
            $url = $request->fullUrl();
        }

        $data = $request->post();
        ksort($data);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nested) {
                    $url .= $key.$nested;
                }
            } else {
                $url .= $key.$value;
            }
        }

        $expected = base64_encode(hash_hmac('sha1', $url, $token, true));

        return hash_equals($expected, $signature);
    }
}
