<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title' => 'Azari Residences',
    'description' => null,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'title' => 'Azari Residences',
    'description' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
                
            
            
        <title><?php echo e($title ?? 'Azari Residences'); ?></title>
    
        
        
            
    <link rel="icon" href="<?php echo e(!empty($siteSettings['favicon_url']) ? $siteSettings['favicon_url'] : route('public.favicon')); ?>">
    <meta name="description" content="<?php echo e($description ?? 'Private, fully serviced residences in Lagos with direct booking and dedicated guest support.'); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,400&family=IBM+Plex+Mono:wght@400;500&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo $__env->yieldPushContent('head'); ?>
</head>

<body class="public-site <?php echo e($bodyClass); ?>">

    <!-- AZARI_PRELOADER_START -->
    
<div
        class="azari-preloader"
        data-public-preloader
        role="status"
        aria-label="Loading"
    >
        <span class="azari-preloader__spinner" aria-hidden="true"></span>
</div>
    
    <!-- AZARI_PRELOADER_END -->
    
    
            

            <a class="skip-link"
         href="#main-content">Skip to main content</a>

    <?php echo $__env->make('public.partials.navigation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <main id="main-content">
        <?php echo e($slot); ?>

    </main>

    <?php echo $__env->make('public.partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <button
        type="button"
        class="back-to-top"
        data-back-to-top
        aria-label="Back to top"
        title="Back to top"
    >
        <span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>
    </button>

    <div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>

    <?php echo $__env->make('public.partials.modal-root', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('public.partials.drawer-root', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/runner/workspace/resources/views/components/public/layout.blade.php ENDPATH**/ ?>