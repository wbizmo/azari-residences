{{-- Shared Resavar browser assets --}}
<meta name="theme-color" content="#052058">

<style>
    @font-face {
        font-family: 'Material Symbols Outlined';
        font-style: normal;
        font-weight: 100 700;
        font-display: block;
        src: url('{{ asset('fonts/material-symbols-outlined.woff2') }}') format('woff2');
    }
    html:not(.az-icons-ready) .material-symbols-outlined { visibility: hidden; }
    .material-symbols-outlined {
        font-family: 'Material Symbols Outlined' !important;
        font-weight: normal; font-style: normal; font-size: 24px; line-height: 1;
        letter-spacing: normal; text-transform: none; display: inline-block; white-space: nowrap;
        word-wrap: normal; direction: ltr; font-feature-settings: 'liga';
        -webkit-font-feature-settings: 'liga'; -webkit-font-smoothing: antialiased;
        font-variation-settings:'FILL' 0,'wght' 300,'GRAD' 0,'opsz' 24;
    }
</style>
<script>
(() => {
    const root=document.documentElement, revealIcons=()=>root.classList.add('az-icons-ready');
    root.classList.add('az-icons-local');
    if(!document.fonts||typeof document.fonts.load!=='function'){window.addEventListener('load',revealIcons,{once:true});return;}
    Promise.race([document.fonts.load("300 24px 'Material Symbols Outlined'"),new Promise(resolve=>window.setTimeout(resolve,3500))]).then(revealIcons,revealIcons);
})();
</script>
