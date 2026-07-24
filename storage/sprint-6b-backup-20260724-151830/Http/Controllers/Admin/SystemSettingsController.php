<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingsController extends Controller
{
    public function edit()
    {
        $settings = SystemSetting::pluck('value', 'key');
        return view('admin.settings.integrations', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'payment_default_gateway' => ['nullable', 'string', 'max:40'],
            'paystack_public_key' => ['nullable', 'string'],
            'paystack_secret_key' => ['nullable', 'string'],
            'flutterwave_public_key' => ['nullable', 'string'],
            'flutterwave_secret_key' => ['nullable', 'string'],
            'stripe_public_key' => ['nullable', 'string'],
            'stripe_secret_key' => ['nullable', 'string'],
            'twilio_sid' => ['nullable', 'string'],
            'twilio_token' => ['nullable', 'string'],
            'twilio_from' => ['nullable', 'string'],
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

        return back()->with('status', 'Integration and document settings saved.');
    }
}
