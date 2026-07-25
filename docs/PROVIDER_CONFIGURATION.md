# Provider and Communication Configuration

All credentials remain in `.env`; the administrator interface exposes only safe status.

## Flutterwave
Configure enable flag, mode, public key, secret key, encryption key and webhook secret. The callback/webhook flow must verify signature, transaction, booking reference, amount and currency before finalization.

## Pesapal
Configure enable flag, mode, consumer key, consumer secret, callback URL and notification ID. Verify transaction status with Pesapal before finalization and treat repeated notifications idempotently.

## InTouch
Configure enable flag, mode, merchant ID, username, password, secret, callback URL and webhook secret. Verify authenticity, amount, currency and booking reference before finalization.

## Twilio
Configure enable flag, account SID, auth token, messaging service SID or from number, and status callback URL. Delivery callbacks update communication logs; retries must remain bounded and notification preferences must be honored.

## Mail
Use a production SMTP or API transport. Set sender and reply-to presentation values separately from credentials. Queue transactional messages and monitor failed jobs and communication logs.

Never paste or display full secrets in CMS, support tickets, logs, audit metadata or screenshots.
