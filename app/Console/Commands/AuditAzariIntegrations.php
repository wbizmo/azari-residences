<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AuditAzariIntegrations extends Command
{
    protected $signature = 'azari:integrations-audit {--strict : Fail when enabled integrations are incomplete}';

    protected $description = 'Audit Resarva payment, messaging, webhook and production environment configuration.';

    public function handle(): int
    {
        $issues = [];
        $warnings = [];

        $this->checkProductionEnvironment($issues, $warnings);
        $this->checkFlutterwave($issues, $warnings);
        $this->checkPesapal($issues, $warnings);
        $this->checkInTouch($issues, $warnings);
        $this->checkTwilio($issues, $warnings);
        $this->checkQueues($issues, $warnings);

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($issues as $issue) {
            $this->error($issue);
        }

        if ($issues === []) {
            $this->info('Resarva integration configuration audit passed.');

            return self::SUCCESS;
        }

        if (! $this->option('strict')) {
            $this->warn('Audit found blocking issues, but --strict was not supplied.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    private function checkProductionEnvironment(array &$issues, array &$warnings): void
    {
        if (! app()->environment('production')) {
            $warnings[] = 'APP_ENV is not production; live integration safety checks are advisory.';

            return;
        }

        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG must be false in production.';
        }

        if (! str_starts_with(strtolower((string) config('app.url')), 'https://')) {
            $issues[] = 'APP_URL must use HTTPS in production.';
        }

        if (! config('session.secure')) {
            $issues[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }
    }

    private function checkFlutterwave(array &$issues, array &$warnings): void
    {
        if (! config('azari.payments.flutterwave.enabled')) {
            return;
        }

        if (config('azari.payments.flutterwave.api_version') !== 'v4') {
            $issues[] = 'FLUTTERWAVE_API_VERSION must be v4.';
        }

        foreach ([
            'client_id' => 'FLUTTERWAVE_CLIENT_ID',
            'client_secret' => 'FLUTTERWAVE_CLIENT_SECRET',
            'webhook_secret' => 'FLUTTERWAVE_WEBHOOK_SECRET',
            'token_url' => 'FLUTTERWAVE_TOKEN_URL',
            'sandbox_base_url' => 'FLUTTERWAVE_SANDBOX_BASE_URL',
            'live_base_url' => 'FLUTTERWAVE_LIVE_BASE_URL',
            'orchestrator_path' => 'FLUTTERWAVE_ORCHESTRATOR_PATH',
            'charge_path' => 'FLUTTERWAVE_CHARGE_PATH',
        ] as $key => $env) {
            if (blank(config("azari.payments.flutterwave.{$key}"))) {
                $issues[] = "{$env} is required while Flutterwave is enabled.";
            }
        }

        $methods = (array) config('azari.payments.flutterwave.allowed_payment_methods', []);
        if ($methods === [] || array_diff($methods, ['opay', 'ussd']) !== []) {
            $issues[] = 'FLUTTERWAVE_ALLOWED_PAYMENT_METHODS must contain only the implemented v4 methods: opay and/or ussd.';
        }

        if (app()->environment('production')
            && config('azari.payments.flutterwave.mode') !== 'live'
        ) {
            $issues[] = 'FLUTTERWAVE_MODE must be live in production when enabled.';
        }

        $warnings[] = 'Confirm the Flutterwave v4 dashboard webhook URL and HMAC secret match this deployment.';
        $warnings[] = 'Flutterwave card collection remains intentionally disabled until an approved encrypted or SDK-based flow receives PCI review.';
    }

    private function checkPesapal(array &$issues, array &$warnings): void
    {
        if (! config('azari.payments.pesapal.enabled')) {
            return;
        }

        foreach ([
            'consumer_key' => 'PESAPAL_CONSUMER_KEY',
            'consumer_secret' => 'PESAPAL_CONSUMER_SECRET',
            'notification_id' => 'PESAPAL_NOTIFICATION_ID',
            'callback_url' => 'PESAPAL_CALLBACK_URL',
        ] as $key => $env) {
            if (blank(config("azari.payments.pesapal.{$key}"))) {
                $issues[] = "{$env} is required while Pesapal is enabled.";
            }
        }

        if (app()->environment('production')
            && config('azari.payments.pesapal.mode') !== 'live'
        ) {
            $issues[] = 'PESAPAL_MODE must be live in production when enabled.';
        }

        $warnings[] = 'Confirm PESAPAL_NOTIFICATION_ID was returned by registering the exact production IPN URL.';
    }

    private function checkInTouch(array &$issues, array &$warnings): void
    {
        if (! config('azari.payments.intouch.enabled')) {
            return;
        }

        if (config('azari.payments.intouch.profile') !== 'custom_v1') {
            $issues[] = 'INTOUCH_API_PROFILE must be custom_v1 after confirming the merchant API contract.';
        }

        foreach ([
            'base_url' => 'INTOUCH_BASE_URL',
            'merchant_id' => 'INTOUCH_MERCHANT_ID',
            'callback_url' => 'INTOUCH_CALLBACK_URL',
            'webhook_secret' => 'INTOUCH_WEBHOOK_SECRET',
        ] as $key => $env) {
            if (blank(config("azari.payments.intouch.{$key}"))) {
                $issues[] = "{$env} is required while InTouch is enabled.";
            }
        }

        $warnings[] = 'InTouch has no safely identifiable public payment API contract in this repository; compare all configured fields with merchant-account documentation before enabling.';
    }

    private function checkTwilio(array &$issues, array &$warnings): void
    {
        if (! config('services.twilio.enabled')) {
            return;
        }

        foreach ([
            'sid' => 'TWILIO_ACCOUNT_SID',
            'token' => 'TWILIO_AUTH_TOKEN',
            'status_callback' => 'TWILIO_STATUS_CALLBACK_URL',
        ] as $key => $env) {
            if (blank(config("services.twilio.{$key}"))) {
                $issues[] = "{$env} is required while Twilio is enabled.";
            }
        }

        if (blank(config('services.twilio.messaging_service_sid'))
            && blank(config('services.twilio.from'))
        ) {
            $issues[] = 'Set TWILIO_MESSAGING_SERVICE_SID or TWILIO_FROM_NUMBER.';
        }

        if (app()->environment('production')
            && ! config('services.twilio.validate_webhooks')
        ) {
            $issues[] = 'TWILIO_VALIDATE_WEBHOOKS must remain true in production.';
        }

        $warnings[] = 'Configure the exact TWILIO_STATUS_CALLBACK_URL in the send request; signature validation depends on an exact URL match.';
    }

    private function checkQueues(array &$issues, array &$warnings): void
    {
        if (config('queue.default') === 'sync' && app()->environment('production')) {
            $warnings[] = 'QUEUE_CONNECTION=sync reduces resilience; use database, Redis or another durable queue in production.';
        }
    }
}
