@extends('components.public.layout')

@section('title', $title.' | Resavar')

@section('content')
<main class="az-editorial-page az-standard-page az-standard-page--{{ $key }}">
    <section class="az-editorial-hero">
        <div class="site-container az-editorial-hero__grid">
            <div class="az-editorial-hero__copy">
                <h1 class="az-editorial-title">{{ $title }}</h1>
                <p>{{ $intro }}</p>

                @if(in_array($key, ['concierge', 'housekeeping', 'restaurant', 'airport-transfers', 'services'], true))
                    <a class="button button-primary" href="{{ route('availability.index') }}">Plan your stay</a>
                @elseif($key === 'contact')
                    <a class="button button-primary" href="#contact-form">Send an enquiry</a>
                @elseif($key === 'local-guide')
                    <a class="button button-primary" href="#guide">Explore the guide</a>
                @elseif($key === 'about')
                    <a class="button button-primary" href="{{ route('public.apartments') }}">Explore hotels & residences</a>
                @endif
            </div>

            <figure class="az-editorial-hero__media">
                <img src="{{ asset('images/'.$image) }}" alt="{{ $title }} at Resavar">
            </figure>
        </div>
    </section>

    @if($key === 'about')
        <section class="az-story-section">
            <div class="site-container az-story-grid">
                <div>
                    <span class="eyebrow">Our approach</span>
                    <h2>Hospitality that feels composed, private and dependable.</h2>
                </div>

                <div>
                    <p>Resavar brings together carefully selected homes, consistent preparation and responsive guest support. Our focus is not simply where guests sleep, but how confidently they can arrive, settle in and move through every day of their stay.</p>
                    <p>From business travel and relocation to longer visits and private city breaks, each residence is managed around comfort, discretion and thoughtful service.</p>
                </div>
            </div>

            <div class="site-container az-value-grid">
                @foreach([
                    ['verified_user', 'Dependable standards', 'Every residence is prepared and managed to a consistent hospitality standard.'],
                    ['home_work', 'Private comfort', 'The freedom and privacy of a home without losing professional guest support.'],
                    ['support_agent', 'Responsive care', 'A team available to coordinate requests before arrival and throughout the stay.']
                ] as [$icon, $heading, $copy])
                    <article>
                        <span class="material-symbols-outlined">{{ $icon }}</span>
                        <h3>{{ $heading }}</h3>
                        <p>{{ $copy }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @elseif($key === 'contact')
        @php
            $contactPhone = '+250799643143';
            $contactPhoneDisplay = '+250 799 643 143';
            $contactDomain = preg_replace('/^www\./i', '', request()->getHost());
            $contactEmail = 'hello@'.$contactDomain;
        @endphp

        <section class="az-contact-section" id="contact-form">
            <div class="site-container az-contact-grid">
                <div class="az-contact-intro">
                    <span class="eyebrow">We are here to help</span>
                    <h2>Tell us what you need.</h2>
                    <p>Use the form for booking questions, guest support, partnerships or general enquiries.</p>

                    <div class="az-contact-note">
                        <span class="material-symbols-outlined" aria-hidden="true">mail</span>
                        <p>
                            
                            <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.
                        </p>
                    </div>

                    <div class="az-contact-note">
                        <span class="material-symbols-outlined" aria-hidden="true">call</span>
                        <p>
                            
                            <a href="tel:{{ $contactPhone }}">{{ $contactPhoneDisplay }}</a>.
                        </p>
                    </div>

                    <div class="az-contact-note">
                        <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
                        <p>Include a booking reference when your enquiry concerns an existing stay.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('public.contact.submit') }}" class="az-contact-form">
                    @csrf

                    <div class="az-form-pair">
                        <label>
                            <span>Full name</span>
                            <input name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>

                        <label>
                            <span>Email address</span>
                            <input type="email" name="email" value="{{ old('email') }}" required>
                            @error('email')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>
                    </div>

                    <div class="az-form-pair">
                        <label>
                            <span>Phone number</span>
                            <input name="phone" value="{{ old('phone') }}">
                        </label>

                        <label>
                            <span>Subject</span>
                            <input name="subject" value="{{ old('subject') }}" required>
                            @error('subject')
                                <small>{{ $message }}</small>
                            @enderror
                        </label>
                    </div>

                    <label>
                        <span>Message</span>
                        <textarea name="message" rows="7" required>{{ old('message') }}</textarea>
                        @error('message')
                            <small>{{ $message }}</small>
                        @enderror
                    </label>

                    <button class="button button-primary" type="submit">
                        Send message
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </form>
            </div>
        </section>
    @elseif($key === 'local-guide')
        <section class="az-guide-section" id="guide">
            <div class="site-container">
                <header class="az-section-heading">
                    <span class="eyebrow">Live like a local</span>
                    <h2>Useful places, memorable experiences.</h2>
                    <p>Our local guide is designed to help guests navigate both essential needs and the experiences that make a city worth discovering.</p>
                </header>

                <div class="az-guide-grid">
                    @foreach([
                        ['restaurant', 'Dining', 'Restaurants, cafés and memorable local flavours.'],
                        ['museum', 'Culture', 'Art, attractions, events and places with a story.'],
                        ['shopping_bag', 'Shopping', 'Everyday essentials, premium retail and local markets.'],
                        ['local_hospital', 'Essentials', 'Hospitals, pharmacies, transport and emergency services.'],
                        ['business_center', 'Business', 'Commercial districts, meeting locations and practical workday support.'],
                        ['nightlife', 'After hours', 'Evening venues, entertainment and city nightlife.']
                    ] as [$icon, $heading, $copy])
                        <article>
                            <span class="material-symbols-outlined">{{ $icon }}</span>
                            <h3>{{ $heading }}</h3>
                            <p>{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif(in_array($key, ['services', 'concierge', 'housekeeping', 'restaurant', 'airport-transfers'], true))
        @php
            $serviceContent = [
                'services' => [
                    ['concierge', 'Personal concierge', 'Reservations, recommendations and thoughtful arrangements.'],
                    ['cleaning_services', 'Housekeeping', 'Scheduled care and practical in-stay support.'],
                    ['restaurant', 'Dining support', 'Restaurant recommendations and reservation coordination.'],
                    ['airport_shuttle', 'Airport transfers', 'Reliable arrival and departure transport.']
                ],
                'concierge' => [
                    ['event_available', 'Reservations', 'Dining, experiences and practical arrangements.'],
                    ['map', 'Local knowledge', 'Recommendations shaped around your plans and preferences.'],
                    ['support_agent', 'Guest assistance', 'Responsive support before arrival and during your stay.']
                ],
                'housekeeping' => [
                    ['cleaning_services', 'Residence cleaning', 'Professional servicing scheduled around your stay.'],
                    ['bed', 'Fresh linen', 'Linen and towel replacement when required.'],
                    ['local_laundry_service', 'Laundry support', 'Coordinated laundry pickup and return.']
                ],
                'restaurant' => [
                    ['menu_book', 'Curated dining', 'Recommendations for different tastes and occasions.'],
                    ['event_seat', 'Reservations', 'Table coordination subject to availability.'],
                    ['nutrition', 'Dietary needs', 'Support communicating dietary requirements and requests.']
                ],
                'airport-transfers' => [
                    ['flight_land', 'Airport pickup', 'Coordinated arrival collection and destination transfer.'],
                    ['flight_takeoff', 'Airport drop-off', 'Timely departure planning and transport.'],
                    ['luggage', 'Luggage planning', 'Vehicle selection informed by passenger and luggage count.']
                ],
            ][$key];
        @endphp

        <section class="az-service-detail-section">
            <div class="site-container az-service-detail-grid">
                <div class="az-service-detail-copy">
                    <span class="eyebrow">Considered guest care</span>
                    <h2>Support designed around a smoother stay.</h2>
                    <p>Availability can depend on residence, location, dates and notice period. Service requests will be connected to live booking workflows in the dedicated guest-services sprint.</p>

                    <a class="az-text-link" href="{{ route('availability.index') }}">
                        Start with your stay
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </a>
                </div>

                <div class="az-service-feature-list">
                    @foreach($serviceContent as [$icon, $heading, $copy])
                        <article>
                            <span class="material-symbols-outlined">{{ $icon }}</span>
                            <div>
                                <h3>{{ $heading }}</h3>
                                <p>{{ $copy }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @elseif(in_array($key, ['booking-terms', 'cancellation-policy', 'privacy-policy', 'terms'], true))
        @include('public.pages.partials.policies')
    @else
        <section class="az-policy-section">
            <div class="site-container">
                <div class="az-policy-card">
                    <span class="eyebrow">Guest information</span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $intro }}</p>
                    <p>For assistance, contact the Resavar guest-support team through an official channel.</p>
                </div>
            </div>
        </section>
    @endif
</main>
@endsection