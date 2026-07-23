<footer class="site-footer" id="contact">
    <div class="site-container">
        <div class="footer-primary">
            
            <div class="footer-brand">
                
                    <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => ['variant' => 'footer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'footer']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $attributes = $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3)): ?>
<?php $component = $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3; ?>
<?php unset($__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3); ?>
<?php endif; ?>

    
                
                <p>
        
                    Private, fully serviced residences across Lagos, managed with
                    dedicated support from booking through checkout.
                </p>
            </div>

            <div class="footer-column">
                <h2>Explore</h2>
                <a href="<?php echo e(route('home')); ?>#residences">Apartments</a>
                <a href="<?php echo e(route('home')); ?>#residences">Rooms</a>
                <a href="<?php echo e(route('home')); ?>#availability">Availability</a>
                <a href="<?php echo e(route('home')); ?>#services">Services</a>
            </div>

            <div class="footer-column">
                <h2>Guest support</h2>
                <button type="button" data-modal-open="verification-modal">Verify booking</button>
                <a href="<?php echo e(route('login')); ?>">Guest login</a>
                <a href="<?php echo e(route('register')); ?>">Create account</a>
                <a href="mailto:hello@example.com">Contact support</a>
            </div>

            <div class="footer-column">
                <h2>Policies</h2>
                <a href="#booking-terms">Booking terms</a>
                <a href="#cancellation-policy">Cancellation policy</a>
                <a href="#privacy-policy">Privacy policy</a>
                <a href="#terms">Terms and conditions</a>
            </div>
        </div>

        <div class="footer-newsletter">
            <div>
                <span class="eyebrow">Private invitations</span>
                <h2>Stay close to new residences and seasonal offers.</h2>
            </div>

            <form class="newsletter-form" data-demo-form data-success-message="Thank you. Your interest has been recorded for the newsletter sprint.">
                <label class="sr-only" for="newsletter-email">Email address</label>
                <input
                    class="luxury-input"
                    id="newsletter-email"
                    name="email"
                    type="email"
                    placeholder="Email address"
                    required
                >
                <button class="button button-brass" type="submit">Join the list</button>
            </form>
        </div>

        <div class="footer-legal">
            <span>&copy; <?php echo e(now()->year); ?> Azari Residences.</span>
            <span>Azari Luxury Properties LTD.</span>
            <a href="<?php echo e(route('home')); ?>">Back home</a>
        </div>
    </div>
</footer>
<?php /**PATH /home/runner/workspace/resources/views/public/partials/footer.blade.php ENDPATH**/ ?>