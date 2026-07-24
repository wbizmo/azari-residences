<div class="az-toast-region" data-az-toast-region aria-live="polite" aria-atomic="true">
@if(session('success'))
<div class="az-toast az-toast--success" data-az-toast><span class="material-symbols-outlined">check_circle</span><div><strong>Completed</strong><p>{{ session('success') }}</p></div><button type="button" data-az-toast-close aria-label="Dismiss"><span class="material-symbols-outlined">close</span></button></div>
@endif
@if(session('error'))
<div class="az-toast az-toast--error" data-az-toast><span class="material-symbols-outlined">error</span><div><strong>Action failed</strong><p>{{ session('error') }}</p></div><button type="button" data-az-toast-close aria-label="Dismiss"><span class="material-symbols-outlined">close</span></button></div>
@endif
@if($errors->any())
<div class="az-toast az-toast--error" data-az-toast><span class="material-symbols-outlined">error</span><div><strong>Please review the form</strong><p>{{ $errors->first() }}</p></div><button type="button" data-az-toast-close aria-label="Dismiss"><span class="material-symbols-outlined">close</span></button></div>
@endif
</div>
