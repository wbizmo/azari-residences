<?php

namespace App\Services\Communications;

use App\Models\Booking;
use App\Models\BookingModificationRequest;
use App\Models\CommunicationLog;
use App\Models\GuestIdentityDocument;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayoutProfile;
use App\Models\Payment;
use App\Models\PropertyListing;
use App\Models\Refund;
use App\Models\ServiceRequest;
use App\Models\SiteSetting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserIdentityDocument;
use App\Models\WithdrawalRequest;
use App\Notifications\PremiumMailNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Throwable;

class AzariTransactionalMailService
{
    public function bookingCreated(Booking $booking): void
    {
        $booking->loadMissing(['property', 'user', 'payments']);

        $details = $this->bookingDetails($booking);
        $paymentUrl = $this->route('public.payment.select', [$booking->reference]);
        $bookingUrl = $booking->user_id
            ? $this->route('user.bookings.show', [$booking->reference])
            : $this->route('bookings.verify', ['reference' => $booking->reference]);

        $this->sendGuest(
            $booking,
            'booking-created',
            'Booking '.$booking->reference.' received',
            [
                'Your reservation has been created and is being held while payment is completed.',
                'Keep the booking reference below for payment, verification and support.',
            ],
            'Continue to secure payment',
            $paymentUrl,
            $details,
            'Payment is required before the reservation becomes confirmed.',
            'warning',
            'Review booking details',
            $bookingUrl,
            true,
            'Reservation received',
            'booking-created:'.$booking->id
        );

        $invoiceUrl = $booking->user_id
            ? $this->route('user.bookings.documents', [$booking->reference, 'invoice'])
            : $bookingUrl;

        $this->sendGuest(
            $booking,
            'invoice-issued',
            'Invoice available for booking '.$booking->reference,
            [
                'Your booking invoice is now available.',
                'The invoice records the stay, charges and current payment position.',
            ],
            'Open invoice',
            $invoiceUrl,
            $details,
            'A receipt is issued only after a successful verified payment.',
            'default',
            null,
            null,
            true,
            'Booking document',
            'invoice-issued:'.$booking->id
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            'booking-admin-alert',
            'New booking '.$booking->reference,
            [
                'A new reservation has been created and is awaiting payment.',
                'Open the booking record to review guest, stay and identity details.',
            ],
            'Open booking',
            $this->route('azari.admin.bookings.show', [$booking->id]),
            $details,
            null,
            'internal',
            ['booking_id' => $booking->id],
            'booking-admin-alert:'.$booking->id
        );
    }

    public function paymentPending(Payment $payment): void
    {
        $booking = $payment->booking;
        if (! $booking) {
            return;
        }

        $details = $this->paymentDetails($payment);

        $this->sendGuest(
            $booking,
            'payment-pending',
            'Payment started for booking '.$booking->reference,
            [
                'Your payment session has been created and is awaiting completion or provider confirmation.',
                'Do not start another payment while this checkout remains active.',
            ],
            'Continue payment',
            $payment->checkout_url ?: $this->route('public.payment.select', [$booking->reference]),
            $details,
            'Your booking is not confirmed until Reserva verifies the payment successfully.',
            'warning',
            null,
            null,
            true,
            'Payment pending',
            'payment-pending:'.$payment->id
        );
    }

    public function paymentSuccessful(Payment $payment): void
    {
        $booking = $payment->booking;
        if (! $booking) {
            return;
        }

        $details = $this->paymentDetails($payment);
        $receiptUrl = $this->route('public.payment.receipt', [$booking->reference, $payment->reference]);
        $bookingUrl = $booking->user_id
            ? $this->route('user.bookings.show', [$booking->reference])
            : $this->route('bookings.verify', ['reference' => $booking->reference]);

        $this->sendGuest(
            $booking,
            'payment-successful',
            'Payment confirmed for booking '.$booking->reference,
            [
                'Reserva has verified your payment successfully.',
                'Your payment reference and amount are shown below.',
            ],
            'View payment receipt',
            $receiptUrl,
            $details,
            'Keep your payment and booking references for arrival verification and support.',
            'success',
            'Open booking',
            $bookingUrl,
            true,
            'Payment successful',
            'payment-successful:'.$payment->id
        );

        $confirmationUrl = $booking->user_id
            ? $this->route('user.bookings.documents', [$booking->reference, 'confirmation'])
            : $bookingUrl;

        $this->sendGuest(
            $booking,
            'booking-confirmed',
            'Booking '.$booking->reference.' is confirmed',
            [
                'Your reservation is now confirmed following successful payment verification.',
                'Review your stay dates, residence and guest information before arrival.',
            ],
            'Open booking confirmation',
            $confirmationUrl,
            $this->bookingDetails($booking),
            'Contact Reserva promptly if any confirmed booking detail is incorrect.',
            'success',
            null,
            null,
            true,
            'Reservation confirmed',
            'booking-confirmed:'.$booking->id
        );

        $receiptDocumentUrl = $booking->user_id
            ? $this->route('user.bookings.documents', [$booking->reference, 'receipt'])
            : $receiptUrl;

        $this->sendGuest(
            $booking,
            'receipt-issued',
            'Receipt issued for booking '.$booking->reference,
            [
                'Your official receipt is now available following successful payment.',
                'The document includes the payment reference, amount, booking reference and verification details.',
            ],
            'Open receipt',
            $receiptDocumentUrl,
            $details,
            null,
            'success',
            null,
            null,
            true,
            'Payment document',
            'receipt-issued:'.$payment->id
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            'payment-success-admin-alert',
            'Successful payment '.$payment->reference,
            [
                'A payment has been verified successfully and the related booking has been updated.',
            ],
            'Open payment',
            $this->route('azari.admin.payments.show', [$payment->id]),
            $details,
            null,
            'internal',
            ['booking_id' => $booking->id, 'payment_id' => $payment->id],
            'payment-success-admin-alert:'.$payment->id
        );
    }

    public function paymentFailed(Payment $payment): void
    {
        $booking = $payment->booking;
        if (! $booking) {
            return;
        }

        $details = $this->paymentDetails($payment);

        $this->sendGuest(
            $booking,
            'payment-failed',
            'Payment was not completed for booking '.$booking->reference,
            [
                'The payment could not be verified successfully.',
                'No successful receipt has been issued for this attempt.',
            ],
            'Try payment again',
            $this->route('public.payment.select', [$booking->reference]),
            $details,
            'Use a fresh checkout attempt. Contact Reserva if your account was debited but the booking remains unpaid.',
            'danger',
            null,
            null,
            true,
            'Payment unsuccessful',
            'payment-failed:'.$payment->id.':'.$payment->status
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            'payment-failed-admin-alert',
            'Payment requires attention '.$payment->reference,
            [
                'A payment attempt failed or was rejected during verification.',
                'Review the payment record and provider diagnostics before taking any manual action.',
            ],
            'Open payment',
            $this->route('azari.admin.payments.show', [$payment->id]),
            $details,
            null,
            'danger',
            ['booking_id' => $booking->id, 'payment_id' => $payment->id],
            'payment-failed-admin-alert:'.$payment->id.':'.$payment->status
        );
    }

    public function paymentExcessAlert(Payment $payment): void
    {
        $booking = $payment->booking;
        if (! $booking) {
            return;
        }

        $this->sendInternal(
            $this->bookingRecipient(),
            'payment-excess-admin-alert',
            'Excess successful payment requires review '.$payment->reference,
            [
                'The provider reported a successful payment after the booking balance had already been satisfied.',
                'Review this payment externally. Reserva does not perform an automatic refund.',
            ],
            'Open payment',
            $this->route('azari.admin.payments.show', [$payment->id]),
            $this->paymentDetails($payment),
            'Do not retry or transfer this payment automatically.',
            'danger',
            ['booking_id' => $booking->id, 'payment_id' => $payment->id],
            'payment-excess-admin-alert:'.$payment->id
        );
    }

    public function bookingCancelled(Booking $booking): void
    {
        $expired = str_contains(strtolower((string) $booking->cancellation_reason), 'payment window expired');
        $template = $expired ? 'booking-expired' : 'booking-cancelled';
        $subject = $expired
            ? 'Booking '.$booking->reference.' expired'
            : 'Booking '.$booking->reference.' was cancelled';

        $this->sendGuest(
            $booking,
            $template,
            $subject,
            [
                $expired
                    ? 'The reservation hold expired because payment was not completed within the available window.'
                    : 'The reservation has been cancelled and is no longer active.',
                'The cancellation information is recorded against your booking reference.',
            ],
            'View booking status',
            $this->route('bookings.verify', ['reference' => $booking->reference]),
            $this->bookingDetails($booking),
            $booking->cancellation_reason ?: 'Contact Reserva if you require clarification about this cancellation.',
            'danger',
            null,
            null,
            true,
            $expired ? 'Reservation expired' : 'Reservation cancelled',
            $template.':'.$booking->id.':'.optional($booking->cancelled_at)->timestamp
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            $template.'-admin-alert',
            $subject,
            [
                'A booking moved into the cancelled state.',
            ],
            'Open booking',
            $this->route('azari.admin.bookings.show', [$booking->id]),
            $this->bookingDetails($booking),
            $booking->cancellation_reason,
            'internal',
            ['booking_id' => $booking->id],
            $template.'-admin-alert:'.$booking->id.':'.optional($booking->cancelled_at)->timestamp
        );
    }

    public function checkInCompleted(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'check-in-completed',
            'Check-in recorded for booking '.$booking->reference,
            [
                'Your check-in has been recorded successfully.',
                'The Reserva team can now manage active-stay requests against this booking.',
            ],
            'Open your booking',
            $this->guestBookingUrl($booking),
            $this->bookingDetails($booking),
            null,
            'success',
            null,
            null,
            true,
            'Stay in progress',
            'check-in-completed:'.$booking->id
        );
    }

    public function stayCompleted(Booking $booking): void
    {
        $this->sendThankYou($booking);
        $this->sendReviewRequest($booking);
    }

    public function serviceRequestCreated(ServiceRequest $request): void
    {
        $booking = $request->booking;
        $user = $request->user;
        $email = $user?->email ?: $booking?->guest_email;

        if (! $email) {
            return;
        }

        $this->send(
            $email,
            $user,
            'service-request-received',
            'Service request '.$request->reference.' received',
            [
                'Your request has been sent to the Reserva team.',
                'Updates and guest-visible responses will appear in your account.',
            ],
            'View service request',
            $this->route('user.service-requests.show', [$request->id]),
            [
                'Request reference' => $request->reference,
                'Request type' => $this->label($request->type),
                'Booking' => $booking?->reference,
                'Status' => $this->label($request->status ?: 'submitted'),
                'Requested time' => $this->dateTime($request->requested_at),
            ],
            'Reserva may contact you if additional information is required.',
            'default',
            null,
            null,
            true,
            true,
            'Guest service',
            ['booking_id' => $booking?->id, 'service_request_id' => $request->id, 'user_id' => $user?->id],
            'service-request-received:'.$request->id
        );
    }

    public function serviceRequestUpdated(ServiceRequest $request): void
    {
        $booking = $request->booking;
        $user = $request->user;
        $email = $user?->email ?: $booking?->guest_email;

        if (! $email) {
            return;
        }

        $this->send(
            $email,
            $user,
            'service-request-updated',
            'Service request '.$request->reference.' updated',
            [
                'The Reserva team updated your service request.',
                $request->guest_reply ?: 'Open the request to review its latest status.',
            ],
            'View service request',
            $this->route('user.service-requests.show', [$request->id]),
            [
                'Request reference' => $request->reference,
                'Request type' => $this->label($request->type),
                'Booking' => $booking?->reference,
                'Status' => $this->label($request->status),
            ],
            null,
            'default',
            null,
            null,
            true,
            true,
            'Guest service update',
            ['booking_id' => $booking?->id, 'service_request_id' => $request->id, 'user_id' => $user?->id],
            'service-request-updated:'.$request->id.':'.optional($request->updated_at)->format('Uu')
        );
    }

    public function passwordChanged(User $user): void
    {
        if (! $user->email) {
            return;
        }

        $this->send(
            $user->email,
            $user,
            'account-password-changed',
            'Your Reserva account password was changed',
            [
                'The password for your Reserva account was changed successfully.',
                'If you did not make this change, reset your password immediately and contact Reserva support.',
            ],
            'Review account security',
            $this->route('profile.edit'),
            [
                'Account' => $this->maskEmail((string) $user->email),
                'Changed' => $this->dateTime(now()),
            ],
            'Reserva will never ask you to send your password by email.',
            'warning',
            null,
            null,
            true,
            true,
            'Account security',
            ['user_id' => $user->id],
            'account-password-changed:'.$user->id.':'.optional($user->updated_at)->format('Uu')
        );
    }

    public function emailAddressChanged(User $user, string $previousEmail): void
    {
        $previousEmail = trim(strtolower($previousEmail));
        $newEmail = trim(strtolower((string) $user->email));

        if (
            ! filter_var($previousEmail, FILTER_VALIDATE_EMAIL)
            || $previousEmail === $newEmail
        ) {
            return;
        }

        $this->send(
            $previousEmail,
            $user,
            'account-email-changed',
            'Your Reserva account email address was changed',
            [
                'The email address connected to your Reserva account was changed.',
                'If you did not make this change, secure the account immediately.',
            ],
            'Reset your password',
            $this->route('password.request'),
            [
                'Previous email' => $this->maskEmail($previousEmail),
                'New email' => $this->maskEmail($newEmail),
                'Changed' => $this->dateTime(now()),
            ],
            'The new address must be verified before protected account features are available.',
            'warning',
            null,
            null,
            true,
            true,
            'Account security',
            ['user_id' => $user->id],
            'account-email-changed:'.$user->id.':'.sha1($previousEmail.'|'.$newEmail).':'.optional($user->updated_at)->format('Uu')
        );
    }

    public function supportTicketCompleted(SupportTicket $ticket): void
    {
        $ticket->loadMissing('user');

        $user = $ticket->user;
        $status = (string) $ticket->status;

        if (! $user?->email || ! in_array($status, ['resolved', 'closed'], true)) {
            return;
        }

        $resolved = $status === 'resolved';

        $this->send(
            $user->email,
            $user,
            $resolved ? 'support-ticket-resolved' : 'support-ticket-closed',
            'Support ticket '.$ticket->reference.' has been '.($resolved ? 'resolved' : 'closed'),
            [
                $resolved
                    ? 'The Reserva team has marked your support request as resolved.'
                    : 'Your Reserva support request has been closed.',
                'Open the ticket to review the conversation and recorded outcome.',
            ],
            'View support ticket',
            $this->route('user.support.show', [$ticket->id]),
            [
                'Ticket reference' => $ticket->reference,
                'Subject' => $ticket->subject,
                'Status' => $this->label($status),
            ],
            $resolved
                ? 'You can reopen the ticket from your account if the issue still requires attention.'
                : null,
            $resolved ? 'success' : 'default',
            null,
            null,
            false,
            true,
            'Support update',
            ['user_id' => $user->id, 'support_ticket_id' => $ticket->id],
            'support-ticket-terminal:'.$ticket->id.':'.$status.':'.optional($ticket->updated_at)->format('Uu')
        );
    }

    public function payoutDestinationChanged(OwnerPayoutProfile $profile): void
    {
        $profile->loadMissing('user');

        if (! $profile->user?->email || blank($profile->preferred_gateway)) {
            return;
        }

        $this->send(
            $profile->user->email,
            $profile->user,
            'owner-payout-destination-changed',
            'Your payout destination was changed',
            [
                'The payout destination saved for your Reserva property-owner account was changed.',
                'For security, only a masked destination is shown in this email.',
            ],
            'Review withdrawal settings',
            $this->route('user.owner.withdrawals'),
            [
                'Gateway' => ucfirst((string) $profile->preferred_gateway),
                'Destination' => $this->maskPayoutDestination($profile),
                'Verification' => $profile->is_verified ? 'Verified' : 'Verification required',
            ],
            $profile->is_verified
                ? 'Contact Reserva immediately if you did not make this change.'
                : 'Withdrawals remain unavailable until Reserva verifies the updated destination.',
            'warning',
            null,
            null,
            true,
            true,
            'Payout security',
            ['user_id' => $profile->user_id, 'owner_payout_profile_id' => $profile->id],
            'owner-payout-destination-changed:'.$profile->id.':'.hash('sha256', implode('|', [
                (string) $profile->preferred_gateway,
                (string) $profile->paypal_recipient,
                (string) $profile->paypal_recipient_type,
                (string) $profile->stripe_connected_account_id,
                optional($profile->updated_at)->format('Uu'),
            ]))
        );
    }

    public function propertyListingCreated(PropertyListing $listing): void
    {
        $name = (string) data_get($listing->property_data, 'name', $listing->reference);

        $this->sendOwner(
            $listing->user,
            'owner-listing-submitted',
            'Property listing '.$listing->reference.' submitted',
            [
                'Reserva has received your property listing for review.',
                'The listing will not appear publicly until it has been approved.',
            ],
            'View listing',
            $this->route('user.owner.listings.show', [$listing->id]),
            [
                'Listing reference' => $listing->reference,
                'Property' => $name,
                'Status' => $this->label($listing->status),
                'Submitted' => $this->dateTime($listing->submitted_at),
            ],
            'Reserva may contact you for verification or additional property information.',
            'default',
            'Owner listing',
            'owner-listing-submitted:'.$listing->id
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            'owner-listing-admin-alert',
            'New owner listing '.$listing->reference,
            [
                'A property owner submitted a listing for Reserva review.',
            ],
            'Review listing',
            $this->route('azari.admin.owner-listings.show', [$listing->id]),
            [
                'Listing reference' => $listing->reference,
                'Property' => $name,
                'Owner' => $listing->user?->name,
                'Status' => $this->label($listing->status),
            ],
            null,
            'internal',
            ['user_id' => $listing->user_id, 'property_listing_id' => $listing->id],
            'owner-listing-admin-alert:'.$listing->id
        );
    }

    public function propertyListingUpdated(PropertyListing $listing, string $from): void
    {
        $status = (string) $listing->status;
        $name = (string) data_get($listing->property_data, 'name', $listing->reference);

        $copy = match ($status) {
            'under_review' => [
                'owner-listing-under-review',
                'Property listing '.$listing->reference.' is under review',
                ['Reserva has started reviewing your property submission.', 'You will receive another update when a decision is recorded.'],
                'default',
                null,
            ],
            'approved' => [
                'owner-listing-approved',
                'Property listing '.$listing->reference.' approved',
                ['Your property listing has been approved and added to Reserva property inventory.', 'Publication depends on the visibility selected by Reserva during approval.'],
                'success',
                null,
            ],
            'declined' => [
                'owner-listing-declined',
                'Property listing '.$listing->reference.' requires changes',
                ['Reserva could not approve the current submission.', 'Review the reason below, update the listing and resubmit it when ready.'],
                'danger',
                $listing->decline_reason,
            ],
            'submitted' => [
                'owner-listing-resubmitted',
                'Property listing '.$listing->reference.' resubmitted',
                ['Reserva has received your revised property listing.', 'The updated submission is awaiting another review.'],
                'default',
                null,
            ],
            default => [null, null, [], 'default', null],
        };

        [$template, $subject, $lines, $tone, $notice] = $copy;
        if (! $template) {
            return;
        }

        $this->sendOwner(
            $listing->user,
            $template,
            $subject,
            $lines,
            'View listing',
            $this->route('user.owner.listings.show', [$listing->id]),
            [
                'Listing reference' => $listing->reference,
                'Property' => $name,
                'Previous status' => $this->label($from),
                'Current status' => $this->label($status),
                'Approved owner share' => $listing->approved_owner_share_percentage !== null
                    ? number_format((float) $listing->approved_owner_share_percentage, 2).'%'
                    : null,
            ],
            $notice,
            $tone,
            'Owner listing update',
            $template.':'.$listing->id.':'.optional($listing->updated_at)->format('Uu')
        );
    }

    public function payoutProfileUpdated(OwnerPayoutProfile $profile): void
    {
        $verified = (bool) $profile->is_verified;

        $this->sendOwner(
            $profile->user,
            $verified ? 'owner-payout-profile-verified' : 'owner-payout-profile-unverified',
            $verified ? 'Your payout destination has been verified' : 'Your payout destination requires verification',
            $verified
                ? ['Reserva has verified your saved payout destination.', 'Eligible withdrawals may now be requested during configured withdrawal windows.']
                : ['Your payout destination is no longer verified.', 'Withdrawals remain unavailable until Reserva verifies the destination again.'],
            'Open withdrawal settings',
            $this->route('user.owner.withdrawals'),
            [
                'Gateway' => ucfirst((string) $profile->preferred_gateway),
                'Destination' => $profile->destinationLabel(),
                'Status' => $verified ? 'Verified' : 'Not verified',
            ],
            null,
            $verified ? 'success' : 'warning',
            'Payout profile',
            'owner-payout-profile:'.$profile->id.':'.($verified ? 'verified' : 'unverified').':'.optional($profile->updated_at)->format('Uu')
        );
    }

    public function withdrawalCreated(WithdrawalRequest $withdrawal): void
    {
        $details = $this->withdrawalDetails($withdrawal);

        $this->sendOwner(
            $withdrawal->user,
            'owner-withdrawal-requested',
            'Withdrawal '.$withdrawal->reference.' received',
            [
                'Reserva has received your withdrawal request and reserved the requested amount.',
                'The request will be processed through your verified payout destination.',
            ],
            'View withdrawal',
            $this->route('user.owner.withdrawals'),
            $details,
            null,
            'default',
            'Withdrawal request',
            'owner-withdrawal-requested:'.$withdrawal->id
        );

        $this->sendInternal(
            $this->bookingRecipient(),
            'owner-withdrawal-admin-alert',
            'New owner withdrawal '.$withdrawal->reference,
            [
                'A property owner submitted a withdrawal request.',
            ],
            'Review withdrawal',
            $this->route('azari.admin.owner-withdrawals.show', [$withdrawal->id]),
            $details + ['Owner' => $withdrawal->user?->name],
            null,
            'internal',
            ['user_id' => $withdrawal->user_id, 'withdrawal_request_id' => $withdrawal->id],
            'owner-withdrawal-admin-alert:'.$withdrawal->id
        );
    }

    public function withdrawalUpdated(WithdrawalRequest $withdrawal): void
    {
        $status = (string) $withdrawal->status;

        $copy = match ($status) {
            'processing' => ['owner-withdrawal-processing', 'Withdrawal '.$withdrawal->reference.' is processing', ['Reserva has started processing your withdrawal.'], 'default', null],
            'provider_sent' => ['owner-withdrawal-provider-sent', 'Withdrawal '.$withdrawal->reference.' sent to provider', ['The payout instruction has been accepted by the configured provider and is being finalised.'], 'default', null],
            'processed' => ['owner-withdrawal-paid', 'Withdrawal '.$withdrawal->reference.' completed', ['Your withdrawal has been processed successfully.'], 'success', null],
            'failed' => ['owner-withdrawal-failed', 'Withdrawal '.$withdrawal->reference.' was not completed', ['The payout could not be confirmed successfully.', 'The reserved balance remains subject to the recorded withdrawal state and reconciliation outcome.'], 'danger', $withdrawal->last_error],
            'reconciliation_required' => ['owner-withdrawal-reconciliation', 'Withdrawal '.$withdrawal->reference.' requires reconciliation', ['The provider may have sent the payout, but Reserva could not finish recording it automatically.', 'Reserva will reconcile the provider outcome before any retry or release.'], 'warning', null],
            'rejected' => ['owner-withdrawal-rejected', 'Withdrawal '.$withdrawal->reference.' was rejected', ['Reserva rejected the withdrawal request.'], 'danger', $withdrawal->rejection_reason ?? $withdrawal->admin_note],
            'pending' => ['owner-withdrawal-retried', 'Withdrawal '.$withdrawal->reference.' returned to pending', ['Reserva has safely returned the withdrawal to the processing queue.'], 'default', null],
            default => [null, null, [], 'default', null],
        };

        [$template, $subject, $lines, $tone, $notice] = $copy;
        if (! $template) {
            return;
        }

        $this->sendOwner(
            $withdrawal->user,
            $template,
            $subject,
            $lines,
            'View withdrawal',
            $this->route('user.owner.withdrawals'),
            $this->withdrawalDetails($withdrawal),
            $notice,
            $tone,
            'Withdrawal update',
            $template.':'.$withdrawal->id.':'.optional($withdrawal->updated_at)->format('Uu')
        );

        if (in_array($status, ['failed', 'reconciliation_required', 'rejected'], true)) {
            $this->sendInternal(
                $this->bookingRecipient(),
                $template.'-admin-alert',
                $subject,
                $lines,
                'Review withdrawal',
                $this->route('azari.admin.owner-withdrawals.show', [$withdrawal->id]),
                $this->withdrawalDetails($withdrawal) + ['Owner' => $withdrawal->user?->name],
                $notice,
                $tone,
                ['user_id' => $withdrawal->user_id, 'withdrawal_request_id' => $withdrawal->id],
                $template.'-admin-alert:'.$withdrawal->id.':'.optional($withdrawal->updated_at)->format('Uu')
            );
        }
    }

    public function ownerEarningCredited(OwnerLedgerEntry $entry): void
    {
        $this->sendOwner(
            $entry->user,
            'owner-earning-credited',
            'Owner earning '.$entry->reference.' credited',
            [
                'Your owner share from a successful Reserva booking has been credited to your account balance.',
            ],
            'View earnings',
            $this->route('user.owner.earnings'),
            [
                'Earning reference' => $entry->reference,
                'Property' => $entry->property?->name,
                'Booking' => $entry->booking?->reference,
                'Amount' => $this->money($entry->currency, $entry->amount),
                'Owner share' => $entry->owner_share_percentage !== null
                    ? number_format((float) $entry->owner_share_percentage, 2).'%'
                    : null,
            ],
            'Available withdrawal balance remains subject to Reserva withdrawal settings and existing reserved requests.',
            'success',
            'Owner earnings',
            'owner-earning-credited:'.$entry->id
        );
    }

    public function userIdentityReviewed(UserIdentityDocument $document): void
    {
        $user = $document->user;
        if (! $user?->email) {
            return;
        }

        $needsReplacement = $document->review_status === 'needs_replacement';

        $this->send(
            $user->email,
            $user,
            $needsReplacement ? 'identity-replacement-required' : 'identity-reviewed',
            $needsReplacement ? 'Your identity document needs replacement' : 'Your identity document has been reviewed',
            $needsReplacement
                ? ['Reserva reviewed your identity document and requires a replacement before it can be accepted.']
                : ['Reserva has completed the review of your identity document.'],
            'Open identity documents',
            $this->route('user.identity.index'),
            [
                'Document type' => $document->identityType?->name,
                'Review status' => $this->label($document->review_status),
                'Reviewed' => $this->dateTime($document->reviewed_at),
            ],
            $document->review_note,
            $needsReplacement ? 'danger' : 'success',
            null,
            null,
            true,
            true,
            'Identity review',
            ['user_id' => $user->id],
            'identity-reviewed:'.$document->id.':'.$document->review_status.':'.optional($document->updated_at)->format('Uu')
        );
    }

    public function guestIdentityReviewed(GuestIdentityDocument $document): void
    {
        $booking = $document->guest?->booking;
        if (! $booking) {
            return;
        }

        $needsReplacement = $document->review_status === 'needs_replacement';

        $this->sendGuest(
            $booking,
            $needsReplacement ? 'guest-identity-replacement-required' : 'guest-identity-reviewed',
            $needsReplacement ? 'A guest identity document needs replacement' : 'A guest identity document has been reviewed',
            $needsReplacement
                ? ['Reserva reviewed an adult guest identity document and requires a replacement.']
                : ['Reserva completed the review of an adult guest identity document.'],
            'Open booking',
            $this->guestBookingUrl($booking),
            [
                'Booking' => $booking->reference,
                'Guest' => trim(($document->guest?->first_name ?? '').' '.($document->guest?->last_name ?? '')),
                'Review status' => $this->label($document->review_status),
            ],
            $document->review_note,
            $needsReplacement ? 'danger' : 'success',
            null,
            null,
            true,
            'Identity review',
            'guest-identity-reviewed:'.$document->id.':'.$document->review_status.':'.optional($document->updated_at)->format('Uu')
        );
    }

    public function sendArrivalReminder(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'arrival-reminder',
            'Your Reserva stay begins tomorrow',
            [
                'Your confirmed stay begins tomorrow.',
                'Review the booking details and ensure every adult guest identity requirement has been completed.',
            ],
            'Review booking',
            $this->guestBookingUrl($booking),
            $this->bookingDetails($booking),
            'Contact Reserva before arrival if your arrival time or guest information has changed.',
            'default',
            null,
            null,
            false,
            'Arrival reminder',
            'arrival-reminder:'.$booking->id
        );
    }

    public function sendCheckInNotice(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'check-in-notice',
            'Your Reserva check-in date is today',
            [
                'Today is the confirmed check-in date for your reservation.',
                'Open your booking for the current status and available arrival actions.',
            ],
            'Open booking',
            $this->guestBookingUrl($booking),
            $this->bookingDetails($booking),
            null,
            'success',
            null,
            null,
            false,
            'Arrival day',
            'check-in-notice:'.$booking->id
        );
    }

    public function sendExtensionReminder(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'stay-extension-reminder',
            'Would you like to extend your Reserva stay?',
            [
                'Your scheduled checkout is approaching.',
                'Any extension remains subject to residence availability and current pricing.',
            ],
            'Contact Reserva',
            $this->route('user.contact'),
            $this->bookingDetails($booking),
            'Request an extension early so the Reserva team can confirm availability.',
            'default',
            null,
            null,
            false,
            'Stay extension',
            'stay-extension-reminder:'.$booking->id
        );
    }

    public function sendCheckoutReminder(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'checkout-reminder',
            'Checkout for booking '.$booking->reference.' is tomorrow',
            [
                'Your scheduled checkout date is tomorrow.',
                'Review any outstanding service requests and contact Reserva if you require assistance.',
            ],
            'Open booking',
            $this->guestBookingUrl($booking),
            $this->bookingDetails($booking),
            null,
            'warning',
            null,
            null,
            false,
            'Departure reminder',
            'checkout-reminder:'.$booking->id
        );
    }

    public function sendThankYou(Booking $booking): void
    {
        $this->sendGuest(
            $booking,
            'post-stay-thank-you',
            'Thank you for staying with Reserva',
            [
                'We appreciate the opportunity to host your stay.',
                'Your completed booking remains available in your account for records and support.',
            ],
            'View booking history',
            $this->guestBookingUrl($booking),
            $this->bookingDetails($booking),
            null,
            'success',
            null,
            null,
            false,
            'Stay completed',
            'post-stay-thank-you:'.$booking->id
        );
    }

    public function sendReviewRequest(Booking $booking): void
    {
        if (! $booking->user_id) {
            return;
        }

        $this->sendGuest(
            $booking,
            'review-request',
            'Share your experience from booking '.$booking->reference,
            [
                'Your verified stay is eligible for a review.',
                'Your feedback helps Reserva improve the guest experience while keeping reviews tied to completed stays.',
            ],
            'Leave a review',
            $this->route('user.bookings.show', [$booking->reference]),
            [
                'Booking' => $booking->reference,
                'Residence' => $booking->property?->name,
                'Stay' => $this->date($booking->check_in).' to '.$this->date($booking->check_out),
            ],
            null,
            'default',
            null,
            null,
            false,
            'Verified-stay review',
            'review-request:'.$booking->id
        );
    }

    public function refundUpdated(Refund $refund, string $event = 'updated'): void
    {
        $booking = $refund->booking;
        if (! $booking) {
            return;
        }

        $status = (string) $refund->status;
        $subject = match ($status) {
            'successful' => 'Refund completed for booking '.$booking->reference,
            'failed' => 'Refund update for booking '.$booking->reference,
            'processing' => 'Refund processing for booking '.$booking->reference,
            default => 'Refund requested for booking '.$booking->reference,
        };

        $tone = match ($status) {
            'successful' => 'success',
            'failed' => 'danger',
            default => 'default',
        };

        $this->sendGuest(
            $booking,
            'refund-'.$status,
            $subject,
            [
                'Your refund record has been updated.',
                'The amount and current status are shown below.',
            ],
            'Open booking',
            $this->guestBookingUrl($booking),
            [
                'Booking' => $booking->reference,
                'Refund reference' => $refund->reference,
                'Amount' => $this->money($refund->currency, $refund->amount),
                'Status' => $this->label($status),
                'Provider reference' => $refund->provider_reference,
            ],
            $refund->safe_error,
            $tone,
            null,
            null,
            true,
            'Refund update',
            'refund-'.$refund->id.':'.$status.':'.$event
        );
    }

    public function bookingModificationUpdated(
        BookingModificationRequest $request,
        string $event = 'updated'
    ): void {
        $booking = $request->booking;
        if (! $booking) {
            return;
        }

        $status = (string) $request->status;
        $subject = $event === 'created'
            ? 'Trip change request '.$request->reference.' received'
            : 'Trip change request '.$request->reference.' updated';

        $this->sendGuest(
            $booking,
            'booking-modification-'.$status,
            $subject,
            [
                $event === 'created'
                    ? 'Reserva received your trip change request.'
                    : 'Reserva updated your trip change request.',
                'Open the booking to review the current status and any staff response.',
            ],
            'Open booking',
            $this->guestBookingUrl($booking),
            [
                'Booking' => $booking->reference,
                'Request' => $request->reference,
                'Type' => $this->label($request->type),
                'Status' => $this->label($status),
            ],
            $request->staff_note,
            in_array($status, ['rejected', 'declined'], true) ? 'danger' : 'default',
            null,
            null,
            false,
            'Trip change',
            'booking-modification-'.$request->id.':'.$status.':'.$event
        );
    }

    private function sendGuest(
        Booking $booking,
        string $template,
        string $subject,
        array $lines,
        ?string $actionLabel,
        ?string $actionUrl,
        array $details,
        ?string $notice,
        string $tone,
        ?string $secondaryActionLabel,
        ?string $secondaryActionUrl,
        bool $critical,
        string $eyebrow,
        string $dedupeKey,
    ): void {
        $email = $booking->user?->email ?: $booking->guest_email;
        if (! $email) {
            return;
        }

        $this->send(
            $email,
            $booking->user,
            $template,
            $subject,
            $lines,
            $actionLabel,
            $actionUrl,
            $details,
            $notice,
            $tone,
            $secondaryActionLabel,
            $secondaryActionUrl,
            $critical,
            false,
            $eyebrow,
            ['booking_id' => $booking->id, 'user_id' => $booking->user_id],
            $dedupeKey
        );
    }

    private function sendOwner(
        ?User $user,
        string $template,
        string $subject,
        array $lines,
        ?string $actionLabel,
        ?string $actionUrl,
        array $details,
        ?string $notice,
        string $tone,
        string $eyebrow,
        string $dedupeKey,
    ): void {
        if (! $user?->email) {
            return;
        }

        $this->send(
            $user->email,
            $user,
            $template,
            $subject,
            $lines,
            $actionLabel,
            $actionUrl,
            $details,
            $notice,
            $tone,
            null,
            null,
            true,
            false,
            $eyebrow,
            ['user_id' => $user->id],
            $dedupeKey
        );
    }

    private function sendInternal(
        ?string $email,
        string $template,
        string $subject,
        array $lines,
        ?string $actionLabel,
        ?string $actionUrl,
        array $details,
        ?string $notice,
        string $tone,
        array $context,
        string $dedupeKey,
    ): void {
        if (! $email) {
            return;
        }

        $this->send(
            $email,
            null,
            $template,
            $subject,
            $lines,
            $actionLabel,
            $actionUrl,
            $details,
            $notice,
            $tone,
            null,
            null,
            true,
            true,
            'Reserva operations',
            $context,
            $dedupeKey
        );
    }

    private function send(
        string $email,
        ?User $user,
        string $template,
        string $subject,
        array $lines,
        ?string $actionLabel,
        ?string $actionUrl,
        array $details,
        ?string $notice,
        string $tone,
        ?string $secondaryActionLabel,
        ?string $secondaryActionUrl,
        bool $critical,
        bool $mailOnly,
        string $eyebrow,
        array $context,
        string $dedupeKey,
    ): void {
        $email = trim(strtolower($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $snapshot = [
            'template' => $template,
            'subject' => $subject,
            'lines' => array_values($lines),
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'details' => $details,
            'notice' => $notice,
            'tone' => $tone,
            'secondary_action_label' => $secondaryActionLabel,
            'secondary_action_url' => $secondaryActionUrl,
            'critical' => $critical,
            'mail_only' => $mailOnly,
            'eyebrow' => $eyebrow,
        ];

        $reservationChannel = $user ? 'dispatch' : 'email';
        $reservationKey = hash('sha256', $reservationChannel.'|'.($user?->id ?: $email).'|'.$dedupeKey);
        $dispatch = CommunicationLog::query()->firstOrCreate(
            ['idempotency_key' => $reservationKey],
            [
                'channel' => $reservationChannel,
                'template' => $template,
                'booking_id' => $context['booking_id'] ?? null,
                'user_id' => $context['user_id'] ?? $user?->id,
                'recipient' => $email,
                'masked_recipient' => $this->maskEmail($email),
                'provider' => $user ? 'laravel-notifications' : config('mail.default'),
                'status' => 'queued',
                'queued_at' => now(),
                'classification' => 'transactional',
                'locale' => app()->getLocale(),
                'timezone' => $user?->timezone ?: config('azari.timezone', 'Africa/Lagos'),
                'payload_hash' => hash('sha256', json_encode($snapshot)),
                'meta' => array_merge($context, [
                    'dedupe_key' => $dedupeKey,
                    'reserved' => true,
                    'snapshot' => $snapshot,
                ]),
            ]
        );

        if (! $dispatch->wasRecentlyCreated) {
            return;
        }

        $notificationContext = array_merge($context, [
            'dedupe_key' => $dedupeKey,
            'classification' => 'transactional',
            'locale' => app()->getLocale(),
            'timezone' => $user?->timezone ?: config('azari.timezone', 'Africa/Lagos'),
            'snapshot' => $snapshot,
        ]);

        if (! $user) {
            $notificationContext['communication_log_id'] = $dispatch->getKey();
        }

        $notification = new PremiumMailNotification(
            template: $template,
            subject: $subject,
            lines: $lines,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            context: $notificationContext,
            details: $details,
            notice: $notice,
            tone: $tone,
            secondaryActionLabel: $secondaryActionLabel,
            secondaryActionUrl: $secondaryActionUrl,
            forceDelivery: $critical,
            mailOnly: $mailOnly,
            eyebrow: $eyebrow,
        );

        try {
            if ($user && strtolower((string) $user->email) === $email) {
                $user->notify($notification);
            } else {
                Notification::route('mail', $email)->notify($notification);
            }

            if ($user) {
                $dispatch->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'safe_error' => null,
                ]);
            }
        } catch (Throwable $exception) {
            $dispatch->update([
                'status' => 'failed',
                'failed_at' => now(),
                'next_attempt_at' => now()->addMinutes(5),
                'safe_error' => 'The notification could not be queued.',
            ]);

            report($exception);
        }
    }

    private function bookingDetails(Booking $booking): array
    {
        return [
            'Booking reference' => $booking->reference,
            'Residence' => $booking->property?->name,
            'Stay' => $this->date($booking->check_in).' to '.$this->date($booking->check_out),
            'Guests' => ((int) $booking->adults).' adult'.((int) $booking->adults === 1 ? '' : 's')
                .' · '.((int) $booking->children).' child'.((int) $booking->children === 1 ? '' : 'ren'),
            'Amount' => $this->money($booking->currency, $booking->total),
            'Status' => $this->label($booking->status),
        ];
    }

    private function paymentDetails(Payment $payment): array
    {
        return [
            'Payment reference' => $payment->reference,
            'Booking reference' => $payment->booking?->reference,
            'Provider' => ucfirst((string) $payment->provider),
            'Amount' => $this->money($payment->currency, $payment->amount),
            'Status' => $this->label($payment->status),
            'Paid' => $this->dateTime($payment->paid_at),
            'Receipt number' => $payment->receipt_number,
        ];
    }

    private function withdrawalDetails(WithdrawalRequest $withdrawal): array
    {
        return [
            'Withdrawal reference' => $withdrawal->reference,
            'Gateway' => ucfirst((string) $withdrawal->gateway),
            'Amount' => $this->money($withdrawal->currency, $withdrawal->amount),
            'Status' => $this->label($withdrawal->status),
            'Provider reference' => $withdrawal->provider_reference,
            'Requested' => $this->dateTime($withdrawal->requested_at),
            'Processed' => $this->dateTime($withdrawal->processed_at),
        ];
    }

    private function guestBookingUrl(Booking $booking): ?string
    {
        return $booking->user_id
            ? $this->route('user.bookings.show', [$booking->reference])
            : $this->route('bookings.verify', ['reference' => $booking->reference]);
    }

    private function bookingRecipient(): ?string
    {
        $email = SiteSetting::valueFor(
            'booking_notification_email',
            config('azari.booking_notification_email', config('mail.from.address'))
        );

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function route(string $name, array $parameters = []): ?string
    {
        return Route::has($name) ? route($name, $parameters) : null;
    }

    private function money(?string $currency, mixed $amount): string
    {
        return strtoupper((string) $currency).' '.number_format((float) $amount, 2);
    }

    private function date(CarbonInterface|string|null $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value->format('d M Y')
            : date('d M Y', strtotime((string) $value));
    }

    private function dateTime(CarbonInterface|string|null $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof CarbonInterface
            ? $value->timezone(config('azari.timezone', 'Africa/Lagos'))->format('d M Y, H:i')
            : date('d M Y, H:i', strtotime((string) $value));
    }

    private function label(?string $value): ?string
    {
        return $value === null ? null : ucfirst(str_replace('_', ' ', $value));
    }

    private function maskPayoutDestination(OwnerPayoutProfile $profile): string
    {
        $destination = trim((string) $profile->destinationLabel());

        if ($destination === '') {
            return 'Not provided';
        }

        if (filter_var($destination, FILTER_VALIDATE_EMAIL)) {
            return $this->maskEmail(strtolower($destination));
        }

        $length = mb_strlen($destination);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)).mb_substr($destination, -4);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
