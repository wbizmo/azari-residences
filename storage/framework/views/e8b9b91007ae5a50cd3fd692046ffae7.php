
        
<?php if (isset($component)) { $__componentOriginala9e319ac86714fc28c831535f65425e6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala9e319ac86714fc28c831535f65425e6 = $attributes; } ?>
<?php $component = App\View\Components\PublicSite\Layout::resolve(['title' => 'Azari Residences | Home','description' => $content['hero_body']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('public-site.layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\PublicSite\Layout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    
    
    <section class="azari-home-hero" aria-labelledby="azari-home-hero-title">
        <img
            src="<?php echo e(asset('images/azari-hero.png')); ?>"
            alt="Luxury Azari Residences serviced apartment interior"
            class="azari-home-hero__image"
            width="2048"
            height="1152"
            fetchpriority="high"
        >

        <div class="azari-home-hero__overlay" aria-hidden="true"></div>

        <div class="site-container azari-home-hero__container">
            <div class="azari-home-hero__content">
                <span class="azari-home-hero__eyebrow">
                    <?php echo e($content['hero_eyebrow'] ?? 'Premium serviced residences'); ?>

                </span>

                <h1 id="azari-home-hero-title">
                    <?php echo e($content['hero_title'] ?? 'Exceptional stays, thoughtfully managed.'); ?>

                </h1>

                <p>
                    <?php echo e($content['hero_body'] ?? 'Discover carefully selected serviced apartments and private residences designed around comfort, privacy and dependable hospitality.'); ?>

                </p>

                <div class="azari-home-hero__actions">
                    <a
                        href="#availability"
                        class="button azari-home-hero__primary"
                    >
                        <span>Check availability</span>

                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >arrow_forward</span>
                    </a>

                    <button
                        type="button"
                        class="button azari-home-hero__secondary"
                        data-modal-open="verification-modal"
                    >
                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >verified</span>

                        <span>Verify a booking</span>
                    </button>
                </div>
            </div>
        </div>

        <a
            href="#availability"
            class="azari-home-hero__scroll"
            aria-label="Scroll to availability search"
        >
            <span>Discover</span>

            <span
                class="material-symbols-outlined"
                aria-hidden="true"
            >south</span>
        </a>
    </section>

    <section class="availability-section" id="availability">
        <div class="site-container">
            <div class="availability-card">
                <div class="availability-heading">
                    <span class="eyebrow">Direct booking</span>
                    <h2>Find your residence</h2>
                    <p>
                        Search by dates, guests, destination and residence type.
                    </p>
                </div>

                <form
                    class="availability-form"
                    method="GET"
                    action="<?php echo e(route('availability.search')); ?>"
                    data-availability-form
                    novalidate
                >
                    <div class="search-field search-field-location">
                        <label for="location">Destination</label>

                        <div class="input-shell">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >location_on</span>

                            <input
                                id="location"
                                name="location"
                                type="text"
                                placeholder="Any destination"
                            >
                        </div>
                    </div>

                    <div class="search-field">
                        <label for="check_in">Check in</label>

                        <div class="input-shell">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >calendar_today</span>

                            <input
                                id="check_in"
                                name="check_in"
                                type="date"
                                min="<?php echo e(now()->toDateString()); ?>"
                                required
                            >
                        </div>

                        <span
                            class="field-error"
                            data-error-for="check_in"
                        ></span>
                    </div>

                    <div class="search-field">
                        <label for="check_out">Check out</label>

                        <div class="input-shell">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >event_available</span>

                            <input
                                id="check_out"
                                name="check_out"
                                type="date"
                                min="<?php echo e(now()->addDay()->toDateString()); ?>"
                                required
                            >
                        </div>

                        <span
                            class="field-error"
                            data-error-for="check_out"
                        ></span>
                    </div>

                    <div
                        class="search-field guest-selector"
                        data-guest-selector
                    >
                        <span class="field-label">Guests</span>

                        <button
                            class="input-shell guest-selector-trigger"
                            type="button"
                            data-guest-trigger
                            aria-expanded="false"
                        >
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >group</span>

                            <span data-guest-summary>
                                1 adult · 0 children
                            </span>

                            <span
                                class="material-symbols-outlined guest-chevron"
                                aria-hidden="true"
                            >keyboard_arrow_down</span>
                        </button>

                        <div
                            class="guest-selector-panel"
                            data-guest-panel
                            hidden
                        >
                            <?php $__currentLoopData = [
                                'adults' => ['Adults', 1],
                                'children' => ['Children', 0],
                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $count]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="guest-row">
                                    <div>
                                        <strong><?php echo e($label); ?></strong>
                                    </div>

                                    <div class="stepper">
                                        <button
                                            type="button"
                                            data-stepper="<?php echo e($key); ?>"
                                            data-direction="-1"
                                            aria-label="Reduce <?php echo e(strtolower($label)); ?>"
                                        >
                                            <span
                                                class="material-symbols-outlined"
                                                aria-hidden="true"
                                            >remove</span>
                                        </button>

                                        <output data-count-for="<?php echo e($key); ?>">
                                            <?php echo e($count); ?>

                                        </output>

                                        <button
                                            type="button"
                                            data-stepper="<?php echo e($key); ?>"
                                            data-direction="1"
                                            aria-label="Increase <?php echo e(strtolower($label)); ?>"
                                        >
                                            <span
                                                class="material-symbols-outlined"
                                                aria-hidden="true"
                                            >add</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                            <button
                                class="button button-primary button-block"
                                type="button"
                                data-guest-done
                            >
                                Done
                            </button>
                        </div>

                        <input
                            type="hidden"
                            name="adults"
                            value="1"
                            data-guest-input="adults"
                        >

                        <input
                            type="hidden"
                            name="children"
                            value="0"
                            data-guest-input="children"
                        >
                    </div>

                    <div class="search-field">
                        <label for="property_type">Residence type</label>

                        <div class="input-shell select-shell">
                            <span
                                class="material-symbols-outlined"
                                aria-hidden="true"
                            >apartment</span>

                            <select
                                id="property_type"
                                name="property_type"
                            >
                                <option value="">Any type</option>
                                <option value="apartment">Apartment</option>
                                <option value="room">Room</option>
                                <option value="studio">Studio</option>
                                <option value="penthouse">Penthouse</option>
                            </select>
                        </div>
                    </div>

                    <button
                        class="availability-submit"
                        type="submit"
                    >
                        <span>Search availability</span>

                        <span
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >search</span>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="intro-section" id="about">
        <div class="site-container intro-grid">
            <div class="intro-heading">
                <span class="eyebrow">Nigeria and Rwanda</span>

                <h2>
                    A considered collection of residences.
                </h2>
            </div>

            <div class="intro-copy">
                <p class="intro-lead">
                    Every property is carefully selected, professionally
                    prepared and continuously managed to a consistent
                    hospitality standard.
                </p>

                <p>
                    Each Azari residence combines the privacy and comfort of a
                    personal home with the thoughtful service expected from
                    premium hospitality. From carefully furnished interiors and
                    reliable housekeeping to responsive guest support, every
                    detail is coordinated to make arrival and daily living feel
                    effortless.
                </p>

                <p>
                    Whether you are travelling for business, relocating,
                    planning an extended visit or simply seeking a refined place
                    to stay, our residences are prepared to provide comfort,
                    confidence and a dependable experience from check-in through
                    departure.
                </p>
            </div>
        </div>
    </section>

    <section class="residences-section" id="residences">
        <div class="site-container">
            <div class="section-heading azari-featured-heading">
                <div>
                    <span class="eyebrow">The collection</span>

                    <h2>
                        <?php echo e($content['featured_title'] ?? 'Featured Residences'); ?>

                    </h2>
                </div>

                <a
                    href="<?php echo e(url('/residences')); ?>"
                    class="azari-view-all-residences"
                >
                    <span>View all residences</span>

                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >arrow_forward</span>
                </a>
            </div>

            <div class="residence-grid">
                <?php $__empty_1 = true; $__currentLoopData = $featuredResidences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <article class="residence-card">
                        <a
                            href="<?php echo e(route('properties.show', $property)); ?>"
                            class="residence-image"
                        >
                            <?php if($property->cover_image): ?>
                                <img
                                    src="<?php echo e(Storage::url($property->cover_image)); ?>"
                                    alt="<?php echo e($property->name); ?>"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="residence-image-placeholder">
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >apartment</span>
                                </div>
                            <?php endif; ?>

                            <span class="residence-type">
                                <?php echo e($property->property_type); ?>

                            </span>
                        </a>

                        <div class="residence-content">
                            <span class="residence-location">
                                <span
                                    class="material-symbols-outlined"
                                    aria-hidden="true"
                                >location_on</span>

                                <?php echo e($property->location); ?>,
                                <?php echo e($property->country); ?>

                            </span>

                            <h3><?php echo e($property->name); ?></h3>

                            <div class="property-card-facts">
                                <span>
                                    <?php echo e($property->bedrooms); ?> bedrooms
                                </span>

                                <span>
                                    <?php echo e($property->bathrooms); ?> bathrooms
                                </span>

                                <span>
                                    <?php echo e($property->max_guests); ?> guests
                                </span>
                            </div>

                            <ul class="amenity-list">
                                <?php $__currentLoopData = $property->amenities->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $amenity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <li>
                                        <span
                                            class="material-symbols-outlined"
                                            aria-hidden="true"
                                        ><?php echo e($amenity->icon); ?></span>

                                        <?php echo e($amenity->name); ?>

                                    </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>

                            <div class="residence-footer">
                                <span>
                                    From

                                    <strong>
                                        <?php echo e($property->currency); ?>

                                        <?php echo e(number_format($property->nightly_rate)); ?>

                                    </strong>

                                    / night
                                </span>

                                <a
                                    href="<?php echo e(route('properties.show', $property)); ?>"
                                    aria-label="View <?php echo e($property->name); ?>"
                                >
                                    <span
                                        class="material-symbols-outlined"
                                        aria-hidden="true"
                                    >arrow_outward</span>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="production-empty-state">
                        No featured residences are currently published.
                    </div>
                <?php endif; ?>
            </div>

            <div class="azari-featured-mobile-action">
                <a
                    href="<?php echo e(url('/residences')); ?>"
                    class="azari-view-all-residences"
                >
                    <span>View all residences</span>

                    <span
                        class="material-symbols-outlined"
                        aria-hidden="true"
                    >arrow_forward</span>
                </a>
            </div>
        </div>
    </section>

    <section class="services-section" id="services">
        <div class="site-container">
            <div class="section-heading section-heading-light">
                <div>
                    <span class="eyebrow">Guest services</span>

                    <h2>
                        <?php echo e($content['services_title'] ?? 'Thoughtful services for every stay.'); ?>

                    </h2>
                </div>
            </div>

            <div class="service-grid">
                <?php $__currentLoopData = [
                    [
                        'concierge',
                        'Private concierge',
                        'Personalised assistance before arrival, throughout your stay and until departure. Our concierge team can coordinate reservations, local recommendations and guest requests to ensure a seamless hospitality experience.',
                    ],
                    [
                        'cleaning_services',
                        'Housekeeping',
                        'Professional housekeeping services maintain every residence to hotel-quality standards. Fresh linens, meticulous cleaning and scheduled servicing help every stay remain comfortable from the first night to the last.',
                    ],
                    [
                        'restaurant',
                        'Dining reservations',
                        'Enjoy access to carefully selected restaurants and local dining experiences. Our team can assist with reservations, recommendations and special arrangements tailored to your preferences.',
                    ],
                    [
                        'airport_shuttle',
                        'Airport transfers',
                        'Reliable airport pickup and drop-off services arranged through trusted transportation partners, providing a comfortable journey between the airport and your residence.',
                    ],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $title, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <article class="service-card">
                        <span
                            class="material-symbols-outlined service-icon"
                            aria-hidden="true"
                        ><?php echo e($icon); ?></span>

                        <h3><?php echo e($title); ?></h3>

                        <p><?php echo e($description); ?></p>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </section>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala9e319ac86714fc28c831535f65425e6)): ?>
<?php $attributes = $__attributesOriginala9e319ac86714fc28c831535f65425e6; ?>
<?php unset($__attributesOriginala9e319ac86714fc28c831535f65425e6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala9e319ac86714fc28c831535f65425e6)): ?>
<?php $component = $__componentOriginala9e319ac86714fc28c831535f65425e6; ?>
<?php unset($__componentOriginala9e319ac86714fc28c831535f65425e6); ?>
<?php endif; ?>
<?php /**PATH /home/runner/workspace/resources/views/public/home.blade.php ENDPATH**/ ?>