<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProviderStatus;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingsController extends Controller
{
    public function edit()
    {
        $settings = SystemSetting::query()
            ->whereNotIn('key', $this->secretKeys())
            ->pluck('value', 'key');

        return view('admin.settings.integrations', [
            'settings' => $settings,
            'providers' => PaymentProviderStatus::query()->orderBy('provider')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'payment_default_gateway' => ['nullable', Rule::in(['flutterwave', 'pesapal', 'intouch'])],
            'mail_from_address' => ['nullable', 'email'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'invoice_prefix' => ['nullable', 'string', 'max:20'],
            'receipt_prefix' => ['nullable', 'string', 'max:20'],
            'booking_prefix' => ['nullable', 'string', 'max:20'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Remove legacy database-stored provider credentials. Sprint 8 credentials
        // are environment-only and must never be exposed or edited in the CMS.
        SystemSetting::query()->whereIn('key', $this->secretKeys())->delete();

        return back()->with('status', 'Safe integration and document settings saved.');
    }

    /** @return list<string> */
    private function secretKeys(): array
    {
        return [
            'paystack_public_key', 'paystack_secret_key',
            'stripe_public_key', 'stripe_secret_key',
            'flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_encryption_key', 'flutterwave_webhook_secret',
            'pesapal_consumer_key', 'pesapal_consumer_secret', 'pesapal_notification_id',
            'intouch_merchant_id', 'intouch_username', 'intouch_password', 'intouch_secret', 'intouch_webhook_secret',
            'twilio_sid', 'twilio_token', 'twilio_from',
        ];
    }
}
