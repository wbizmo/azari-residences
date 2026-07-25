{{-- Shared shell assets: fixed site-wide favicon and no-flash icon bootstrap. --}}
<link rel="icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('images/azari-favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/azari-favicon.png') }}">
<meta name="theme-color" content="#173f35">
<style id="azari-icon-critical-css">
    html:not(.azari-icons-ready) .material-symbols-outlined,
    html:not(.azari-icons-ready) .material-symbols-rounded,
    html:not(.azari-icons-ready) .material-symbols-sharp {
        visibility: hidden !important;
    }
</style>
<script>
(function () {
    var root = document.documentElement;
    var reveal = function () { root.classList.add('azari-icons-ready'); };
    var fallback = window.setTimeout(reveal, 1800);

    if (!document.fonts || !document.fonts.load) {
        window.clearTimeout(fallback);
        reveal();
        return;
    }

    Promise.race([
        document.fonts.load('24px "Material Symbols Outlined"'),
        new Promise(function (resolve) { window.setTimeout(resolve, 1200); })
    ]).then(function () {
        window.clearTimeout(fallback);
        reveal();
    }).catch(reveal);
})();
</script>
