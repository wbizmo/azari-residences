<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'variant' => 'header',
    'dark' => false,
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
    'variant' => 'header',
    'dark' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $settings = isset($siteSettings) && is_array($siteSettings)
        ? $siteSettings
        : [];

    $logoUrl = trim((string) ($settings['logo_url'] ?? ''));
    $siteName = trim((string) ($settings['site_name'] ?? 'Azari Residences'));

    $isFooter = $variant === 'footer';
?>

<a
    href="<?php echo e(url('/')); ?>"
    <?php echo e($attributes->class([
        'brand',
        'brand-dark' => $dark,
        'azari-brand',
        'azari-brand--footer' => $isFooter,
        'azari-brand--header' => ! $isFooter,
    ])); ?>

    aria-label="<?php echo e($siteName); ?> home"
>
    <?php if($logoUrl !== ''): ?>
        <span
            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                'brand-logo-slot',
                'footer-logo-slot' => $isFooter,
                'azari-brand__logo-slot',
            ]); ?>"
        >
            <img
                src="<?php echo e($logoUrl); ?>"
                alt="<?php echo e($siteName); ?>"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'brand-image',
                    'footer-brand-image' => $isFooter,
                    'azari-brand__image',
                ]); ?>"
                loading="<?php echo e($isFooter ? 'lazy' : 'eager'); ?>"
                decoding="async"
            >
        </span>
    <?php else: ?>
        <span
            class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                'brand-logo-slot',
                'footer-logo-slot' => $isFooter,
                'azari-brand__fallback-slot',
            ]); ?>"
            aria-hidden="true"
        >
            <span class="material-symbols-outlined">hotel_class</span>
        </span>

        <span class="brand-copy azari-brand__copy">
            <strong>Azari</strong>
            <small>Residences</small>
        </span>
    <?php endif; ?>
</a>
<?php /**PATH /home/runner/workspace/resources/views/components/brand-logo.blade.php ENDPATH**/ ?>