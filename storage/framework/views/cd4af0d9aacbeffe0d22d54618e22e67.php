<header class="site-header" data-site-header>
    <div class="site-container nav-shell">
        
            <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
        

        <nav class="desktop-navigation" aria-label="Primary navigation">
            <a href="<?php echo e(route('home')); ?>#residences">Residences</a>
            <a href="<?php echo e(route('home')); ?>#about">About</a>

            <div class="nav-popover" data-popover>
                <button
                    type="button"
                    class="nav-popover-trigger"
                    data-popover-trigger
                    aria-expanded="false"
                    aria-controls="services-popover"
                >
                    Services
                    <span class="material-symbols-outlined" aria-hidden="true">keyboard_arrow_down</span>
                </button>

                <div class="nav-popover-panel" id="services-popover" data-popover-panel hidden>
                    <a href="<?php echo e(route('home')); ?>#services">
                        <span class="material-symbols-outlined">concierge</span>
                        Concierge
                    </a>
                    <a href="<?php echo e(route('home')); ?>#services">
                        <span class="material-symbols-outlined">cleaning_services</span>
                        Housekeeping
                    </a>
                    <a href="<?php echo e(route('home')); ?>#services">
                        <span class="material-symbols-outlined">restaurant</span>
                        Restaurant
                    </a>
                    <a href="<?php echo e(route('home')); ?>#services">
                        <span class="material-symbols-outlined">airport_shuttle</span>
                        Airport transfers
                    </a>
                </div>
            </div>

            <a href="<?php echo e(route('home')); ?>#guide">Local guide</a>
            <a href="<?php echo e(route('home')); ?>#contact">Contact</a>
        </nav>

        <div class="nav-actions">
            <button
                type="button"
                class="nav-text-action"
                data-modal-open="verification-modal"
            >
                Verify booking
            </button>

            <?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('dashboard')); ?>" class="nav-text-action">Dashboard</a>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>" class="nav-text-action">Login</a>
            <?php endif; ?>

            <a href="<?php echo e(route('home')); ?>#availability" class="button button-brass">Book now</a>

            <button
                type="button"
                class="mobile-menu-button"
                data-drawer-open="mobile-navigation"
                aria-label="Open navigation"
            >
                <span class="material-symbols-outlined" aria-hidden="true">menu</span>
            </button>
        </div>
    </div>
</header>
<?php /**PATH /home/runner/workspace/resources/views/public/partials/navigation.blade.php ENDPATH**/ ?>