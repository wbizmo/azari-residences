<div
    class="drawer-layer"
    id="mobile-navigation"
    data-drawer
    hidden
    aria-hidden="true"
>
    <div class="drawer-backdrop" data-drawer-close></div>

    <aside class="drawer-panel" role="dialog" aria-modal="true" aria-label="Mobile navigation">
        <div class="drawer-header">
            
            <?php if (isset($component)) { $__componentOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8741a05e11b0c77d19ec61b6b35b26b3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.brand-logo','data' => ['dark' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('brand-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['dark' => true]); ?>
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
        

            <button type="button" class="modal-close" data-drawer-close aria-label="Close navigation">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
        </div>

        <nav class="mobile-navigation" aria-label="Mobile navigation">
            <a href="<?php echo e(route('home')); ?>">Home</a>
            <a href="<?php echo e(route('home')); ?>#residences">Apartments and rooms</a>
            <a href="<?php echo e(route('home')); ?>#availability">Check availability</a>
            <a href="<?php echo e(route('home')); ?>#services">Services</a>
            <a href="<?php echo e(route('home')); ?>#guide">Local guide</a>
            <a href="<?php echo e(route('home')); ?>#about">About Azari</a>
            <a href="<?php echo e(route('home')); ?>#contact">Contact</a>
            <button type="button" data-modal-open="verification-modal" data-drawer-close>
                Verify booking
            </button>
        </nav>

        <div class="drawer-actions">
            <?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('dashboard')); ?>" class="button button-primary button-block">Guest dashboard</a>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>" class="button button-secondary button-block">Login</a>
                <a href="<?php echo e(route('register')); ?>" class="button button-primary button-block">Create account</a>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php /**PATH /home/runner/workspace/resources/views/public/partials/drawer-root.blade.php ENDPATH**/ ?>