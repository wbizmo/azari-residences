@php
    $policyPages = [
        'booking-terms' => [
            'label' => 'Reservation policy',
            'updated' => '24 July 2026',
            'summary' => 'These booking terms explain how a reservation is created, verified, paid for, amended and completed with Reserva.',
            'sections' => [
                ['1. Making a booking', [
                    'A booking may be made as a guest or through a registered Reserva account.',
                    'The person making the booking must provide accurate contact, occupancy and stay information and must be authorised to act for every person included in the reservation.',
                    'Availability, pricing, taxes, cleaning fees, security deposits, promotional rates and any property-specific conditions shown during booking form part of the reservation presented for confirmation.',
                    'A submitted request is not a confirmed stay until Reserva records the booking as confirmed and issues the applicable booking reference or confirmation.',
                ]],
                ['2. Guest identity and identification', [
                    'The booking owner must provide one accepted government-issued photo identification document when required for the stay.',
                    'Accepted documents may include a passport, national identity card, driver’s licence or another government-issued document approved by Reserva.',
                    'Every additional adult included in a booking must have their own government-issued identification attached to that booking. Children do not require identification unless the administrator has enabled that requirement.',
                    'For registered guests, the account owner’s securely stored identification may be linked to a booking that includes them. Guest bookings must provide identification for the booking owner and every additional adult.',
                    'Reserva does not use OCR, facial recognition or third-party automated identity verification. Identification is collected for secure booking and check-in administration and is reviewed through authorised operational processes.',
                ]],
                ['3. Occupancy and guest details', [
                    'The number of adults and children must not exceed the capacity stated for the selected room or apartment.',
                    'Only guests recorded or subsequently approved for the booking may occupy the residence, subject to any property rules and check-in requirements.',
                    'The booking owner remains responsible for the accuracy of guest information and for ensuring that all guests comply with the applicable house rules and stay conditions.',
                ]],
                ['4. Payment and booking records', [
                    'Payment instructions, due dates, accepted payment methods, taxes, fees, deposits and any balance payable are shown during the booking or communicated in the related invoice.',
                    'Each booking maintains its own payment record, invoice, receipt, verification record and booking reference.',
                    'A payment made for one booking cannot be transferred to another booking. Payments remain attached to the reservation for which they were recorded.',
                    'Invoices and receipts may include secure QR codes or protected verification links. A verification result confirms the status recorded by Reserva at the time of verification and does not expose internal or unnecessary guest information.',
                ]],
                ['5. Changes, extensions and rebooking', [
                    'A room or apartment cannot be transferred from one booking to another.',
                    'A guest who wants a different room, a different apartment or a new stay must complete a new booking.',
                    'An extended stay must be processed as a formal extension transaction or a new booking, subject to availability and approval.',
                    'Every new booking or formal extension receives its own payment record, invoice, receipt, verification record and booking reference. Amounts and records from the original booking are not moved into the new transaction.',
                ]],
                ['6. Check-in and stay requirements', [
                    'Guests may be required to complete digital check-in, validate the booking, provide arrival details, upload required identification, provide emergency contact information, accept house rules and acknowledge check-in terms before access is released.',
                    'Check-in instructions are issued through the approved Reserva communication channels after the required booking and identity steps have been completed.',
                    'Special requests and service requests are subject to availability, location, notice period and confirmation and do not become guaranteed merely because they were entered during booking.',
                ]],
                ['7. Booking verification', [
                    'Bookings, invoices and receipts may be verified using a booking number, verification code, QR code, invoice number, receipt number or a signed or protected verification link.',
                    'Verification pages may show a privacy-masked guest name together with the residence, stay dates and recorded booking, payment or invoice status.',
                    'Expired, invalid or unsuccessful verification attempts do not provide access to internal booking records. Suspicious attempts may be logged where enabled.',
                ]],
                ['8. Cancellation', [
                    'Cancellation rights, deadlines, charges and refund eligibility are governed by the cancellation conditions displayed for the selected reservation and by the Cancellation Policy.',
                    'A cancellation is not complete until it has been submitted through an approved channel and recorded by Reserva.',
                ]],
            ],
        ],
        'cancellation-policy' => [
            'label' => 'Guest policy',
            'updated' => '24 July 2026',
            'summary' => 'This policy explains how cancellation requests are handled and how cancellation affects booking, payment and rebooking records.',
            'sections' => [
                ['1. Reservation-specific conditions', [
                    'Cancellation deadlines, applicable charges, refund eligibility and any non-refundable amount may differ by property, rate, promotion, date or stay type.',
                    'The conditions presented during booking and recorded against the confirmed reservation apply to that booking. Guests should review those conditions before payment and confirmation.',
                    'Where a reservation-specific condition conflicts with a general summary on this page, the condition expressly recorded for the confirmed booking applies, subject to applicable law.',
                ]],
                ['2. How to request cancellation', [
                    'Cancellation must be requested through the guest dashboard or another official Reserva support channel made available for the booking.',
                    'The request should include the booking reference and any information reasonably required to validate the booking owner.',
                    'A request is not treated as completed until Reserva records the booking as cancelled and issues or displays the resulting status.',
                ]],
                ['3. Identity and booking validation', [
                    'Reserva may validate the booking reference, account session, registered email address, phone number or other booking information before accepting a cancellation instruction.',
                    'This validation protects the guest and helps prevent unauthorised changes. It does not involve facial recognition, OCR or third-party automated identity verification.',
                ]],
                ['4. Charges and refunds', [
                    'Any cancellation charge, retained amount or refundable balance is determined from the conditions attached to the reservation and the payment records actually received.',
                    'A refund, where approved, is connected to the original booking and payment transaction. Processing time may depend on the payment method, provider and banking network.',
                    'Service, processing or payment-provider charges that are expressly stated as non-refundable remain subject to the conditions shown for the reservation and applicable law.',
                ]],
                ['5. No transfer between bookings', [
                    'Cancelling one booking does not create a transferable credit, room transfer or payment transfer to another reservation unless Reserva expressly records a separate approved arrangement.',
                    'A guest who wants another room, apartment or stay date must create a new booking or approved formal extension.',
                    'The replacement booking receives a separate booking reference, payment record, invoice, receipt and verification record. The original booking retains its own cancellation and financial history.',
                ]],
                ['6. Amendments and extensions', [
                    'A date change, residence change or stay extension is not automatically treated as a cancellation and reallocation.',
                    'Where the requested change cannot be processed as a formal extension, the guest may need to cancel under the applicable conditions and make a new booking, subject to availability and current pricing.',
                ]],
                ['7. Records and notifications', [
                    'Reserva records the cancellation reason, status, authorised action, related financial entries and relevant audit information.',
                    'Confirmation may be sent by email, SMS or through the guest dashboard where those channels are enabled.',
                    'Guests should retain the cancellation confirmation and any updated invoice, receipt or refund record issued for the booking.',
                ]],
            ],
        ],
        'privacy-policy' => [
            'label' => 'Privacy and data',
            'updated' => '24 July 2026',
            'summary' => 'This policy describes the guest, booking, identity, payment and communications information Reserva processes to provide and protect its hospitality services.',
            'sections' => [
                ['1. Information we collect', [
                    'We may collect names, email addresses, phone numbers, account credentials, addresses, booking dates, selected residence, adult and child occupancy, guest details, arrival information, emergency contacts, special requests and communications with our team.',
                    'Where required for registration, booking or check-in, we collect one government-issued photo identification document for the account or booking owner and a separate document for each additional adult included in the booking.',
                    'We also maintain booking references, verification codes, invoices, receipts, payment status, cancellation information, check-in acknowledgements, service requests and security or audit records.',
                ]],
                ['2. How we use information', [
                    'We use information to create and manage accounts and bookings, confirm availability, process and reconcile payments, issue invoices and receipts, support digital check-in, coordinate guest services, communicate about a stay, handle cancellations and extensions, prevent unauthorised activity and meet operational or legal obligations.',
                    'We may use booking and transaction data to provide reports, analytics, operational oversight and auditable records within Reserva.',
                ]],
                ['3. Identification documents', [
                    'Identification documents are stored securely and linked only to the relevant account or booking workflow.',
                    'Reserva does not perform optical character recognition, facial recognition or third-party automated identity verification on uploaded identification documents.',
                    'Access is restricted to authorised roles with a legitimate operational requirement. Identification requirements for children apply only where enabled by an administrator and communicated for the booking.',
                ]],
                ['4. Booking and public verification', [
                    'Secure booking, invoice and receipt verification may be available through protected links, verification codes, QR codes or reference numbers.',
                    'Public verification displays only the information needed to establish validity. Guest names may be masked, sensitive identity information remains hidden and internal records are not exposed.',
                    'Invalid, expired or suspicious verification activity may be recorded for security and audit purposes.',
                ]],
                ['5. Payments and service providers', [
                    'Payment and communication services may be provided through enabled integrations such as payment gateways, email infrastructure and SMS providers.',
                    'Sensitive credentials for those integrations are stored through protected server environment configuration rather than public page content.',
                    'A provider receives only the information required for the service it performs, subject to its own legal and security obligations.',
                ]],
                ['6. Security and access control', [
                    'Reserva applies role-based access controls, audit logs, protected links, validation, secure storage and other administrative and technical controls intended to protect guest and business records.',
                    'No internet system can be guaranteed to be completely risk-free. Guests should protect their account credentials and contact Reserva promptly if they suspect unauthorised access.',
                ]],
                ['7. Retention and record integrity', [
                    'Information is retained for as long as reasonably necessary to administer bookings, preserve invoices and receipts, maintain audit and security records, resolve disputes and satisfy applicable legal, accounting or operational obligations.',
                    'Booking records are not transferred between reservations. Each booking and formal extension maintains its own identity, payment, invoice, receipt and verification history.',
                ]],
                ['8. Your information and enquiries', [
                    'Guests may contact Reserva to request assistance with personal information, account access, inaccurate details or privacy concerns, subject to identity and booking validation where necessary.',
                    'Certain booking, financial, security or audit records may need to be retained even after an account or stay is no longer active.',
                ]],
            ],
        ],
        'terms' => [
            'label' => 'Website and service terms',
            'updated' => '24 July 2026',
            'summary' => 'These terms govern access to the Reserva website, guest accounts, booking services, verification tools and related digital features.',
            'sections' => [
                ['1. Acceptance of these terms', [
                    'By using the website, creating an account, submitting a booking, completing digital check-in or using a verification service, you agree to these terms and to the policies expressly connected to the relevant service.',
                    'Booking-specific pricing, payment, cancellation, occupancy and property conditions presented during reservation form part of the agreement for that booking.',
                ]],
                ['2. Accounts and guest access', [
                    'You must provide accurate information and keep account credentials secure. You are responsible for activity completed through your authenticated account unless you promptly report suspected unauthorised access.',
                    'Reserva may require email or phone verification where enabled and may restrict access when security, identity, payment or operational checks have not been completed.',
                    'Guest booking without registration may be available, but the same booking-owner and additional-adult identification requirements apply.',
                ]],
                ['3. Acceptable use', [
                    'You must not misuse the website, attempt unauthorised access, interfere with service operation, submit false identity or booking information, manipulate pricing or availability, abuse verification tools or use the service for unlawful activity.',
                    'Automated or repeated verification attempts that appear suspicious may be limited or logged without exposing internal records.',
                ]],
                ['4. Property information and availability', [
                    'Reserva aims to present accurate property descriptions, amenities, capacity, images, pricing and availability. Availability remains subject to live booking, maintenance, operational restrictions and final confirmation.',
                    'Images and descriptions illustrate the selected property or category but do not create a promise beyond the features expressly recorded for the confirmed reservation.',
                ]],
                ['5. Bookings, payments and records', [
                    'Bookings are governed by the Booking Terms, the conditions shown for the selected rate and any invoice or payment instruction issued for the reservation.',
                    'Each booking or formal extension has a separate booking reference, payment record, invoice, receipt and verification record.',
                    'Rooms, apartments and payments cannot be transferred between bookings. A different residence or additional stay requires a new booking or approved formal extension.',
                ]],
                ['6. Digital check-in and guest conduct', [
                    'Guests may be required to validate the booking, provide identification and guest details, accept house rules and check-in terms, and complete required acknowledgements before receiving access instructions.',
                    'The booking owner is responsible for the conduct of guests included in the reservation and for compliance with occupancy, safety, property and service rules communicated for the stay.',
                ]],
                ['7. Third-party services', [
                    'Payments, messaging, maps or other enabled functions may depend on third-party providers. Their availability and processing may be subject to their own systems and terms.',
                    'Reserva remains responsible for its own booking records but cannot guarantee uninterrupted operation of an external network or provider.',
                ]],
                ['8. Service changes and availability', [
                    'Reserva may maintain, secure, improve, suspend or modify digital features where reasonably required for safety, reliability, compliance or system integrity.',
                    'Administrative configuration may change published content, available providers, service options or website presentation without changing confirmed booking rights already recorded for a guest, except where required by law or agreed with the guest.',
                ]],
                ['9. Governing documents and contact', [
                    'These Terms and Conditions should be read together with the Booking Terms, Cancellation Policy, Privacy Policy and any property-specific or booking-specific conditions shown before confirmation.',
                    'Questions about these terms or an existing booking should be sent through the official contact or guest-support channels, with the booking reference included where applicable.',
                ]],
            ],
        ],
    ];

    $policy = $policyPages[$key];
@endphp

<section class="az-policy-section az-policy-document">
    <div class="site-container az-policy-layout">
        <aside class="az-policy-aside" aria-label="Policy information">
            <span class="eyebrow">{{ $policy['label'] }}</span>
            <h2>{{ $title }}</h2>
            <p>{{ $policy['summary'] }}</p>
            <dl>
                <div><dt>Last updated</dt><dd>{{ $policy['updated'] }}</dd></div>
                <div><dt>Applies to</dt><dd>Reserva website, guest accounts and bookings</dd></div>
            </dl>
            <a class="az-text-link" href="{{ route('public.contact') }}">Ask a policy question <span class="material-symbols-outlined">arrow_forward</span></a>
        </aside>

        <article class="az-policy-content">
            @foreach($policy['sections'] as [$heading, $paragraphs])
                <section>
                    <h3>{{ $heading }}</h3>
                    @foreach($paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach

            <div class="az-policy-contact-note">
                <span class="material-symbols-outlined" aria-hidden="true">support_agent</span>
                <div>
                    <h3>Need help with an existing booking?</h3>
                    <p>Contact Reserva through an official support channel and include your booking reference. Sensitive identification documents should only be submitted through the secure upload flow provided for your booking.</p>
                </div>
            </div>
        </article>
    </div>
</section>
