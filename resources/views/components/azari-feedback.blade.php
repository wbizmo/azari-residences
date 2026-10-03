<style>
/* Compact classic Toastify presentation with Azari theme colours. */
.az-toast-region{position:fixed;z-index:2147483000;top:16px;right:16px;display:flex;flex-direction:column;align-items:flex-end;gap:8px;width:min(360px,calc(100vw - 32px));pointer-events:none;font-family:inherit}
.az-toast{--az-toast-tone:#052058;position:relative;display:flex!important;align-items:center!important;gap:9px!important;width:auto!important;max-width:100%!important;min-height:44px!important;margin:0!important;padding:10px 11px 10px 13px!important;overflow:hidden!important;box-sizing:border-box!important;color:#FFFFFF!important;background:var(--az-toast-tone)!important;border:0!important;border-radius:7px!important;box-shadow:0 8px 24px rgba(5, 32, 88, .22)!important;pointer-events:auto!important;animation:azToastIn .2s ease-out both}
.az-toast--success{--az-toast-tone:#052058}.az-toast--error{--az-toast-tone:#052058}.az-toast--warning{--az-toast-tone:#052058}.az-toast--info{--az-toast-tone:#052058}
.az-toast__icon{display:grid!important;flex:0 0 auto!important;width:20px!important;height:20px!important;place-items:center!important;color:#FFFFFF!important;background:transparent!important;border-radius:0!important}.az-toast__icon .material-symbols-outlined{font-size:19px!important;line-height:1!important;font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 20}
.az-toast__content{min-width:0!important;max-width:286px!important}.az-toast__content strong{display:none!important}.az-toast__content p{display:block!important;margin:0!important;overflow-wrap:anywhere!important;color:#FFFFFF!important;font-size:13px!important;font-weight:600!important;line-height:1.35!important}
.az-toast__close{display:grid!important;flex:0 0 auto!important;width:24px!important;height:24px!important;place-items:center!important;padding:0!important;color:rgba(255, 255, 255, .82)!important;background:transparent!important;border:0!important;border-radius:4px!important;cursor:pointer!important}.az-toast__close:hover{color:#FFFFFF!important;background:rgba(255, 255, 255, .14)!important}.az-toast__close .material-symbols-outlined{font-size:18px!important;line-height:1!important}
.az-toast.is-leaving{animation:azToastOut .18s ease-in forwards}@keyframes azToastIn{from{opacity:0;transform:translate3d(18px,0,0)}to{opacity:1;transform:none}}@keyframes azToastOut{to{opacity:0;transform:translate3d(18px,0,0)}}
@media(max-width:640px){.az-toast-region{top:10px;right:10px;left:10px;align-items:stretch;width:auto}.az-toast{width:100%!important;min-height:42px!important;padding:9px 10px 9px 12px!important;border-radius:6px!important}.az-toast__content{max-width:none!important}.az-toast__content p{font-size:12.5px!important;line-height:1.3!important}}
@media(prefers-reduced-motion:reduce){.az-toast,.az-toast.is-leaving{animation:none}}
</style>

<div class="az-toast-region" data-az-toast-region data-toast-region aria-live="polite" aria-atomic="false">
@php
    $toastItems = collect([
        ['success', session('success'), 'Completed', 'check_circle'],
        ['error', session('error'), 'Action failed', 'error'],
        ['warning', session('warning'), 'Please note', 'warning'],
        ['info', session('info') ?? session('status'), 'Information', 'info'],
    ])->filter(fn ($item) => filled($item[1]));
    if ($errors->any()) $toastItems->push(['error', $errors->first(), 'Please review the form', 'error']);
@endphp
@foreach($toastItems as [$type, $message, $title, $icon])
<div class="az-toast az-toast--{{ $type }}" data-az-toast role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <div class="az-toast__icon" aria-hidden="true"><span class="material-symbols-outlined">{{ $icon }}</span></div>
    <div class="az-toast__content"><strong>{{ $title }}</strong><p>{{ $message }}</p></div>
    <button type="button" class="az-toast__close" data-az-toast-close aria-label="Dismiss notification"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
</div>
@endforeach
</div>
<script>
(function(){
  function init(root){(root||document).querySelectorAll('[data-az-toast]:not([data-ready])').forEach(function(t){t.dataset.ready='1';var timer;function close(){if(t.classList.contains('is-leaving'))return;clearTimeout(timer);t.classList.add('is-leaving');setTimeout(function(){t.remove()},190)}var b=t.querySelector('[data-az-toast-close]');if(b)b.addEventListener('click',close);t.addEventListener('mouseenter',function(){clearTimeout(timer)});t.addEventListener('mouseleave',function(){timer=setTimeout(close,2500)});timer=setTimeout(close,4500)})}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){init(document)});else init(document);
  document.addEventListener('livewire:navigated',function(){init(document)});
  window.AzariToast=function(message,type){var region=document.querySelector('[data-az-toast-region]');if(!region||!message)return;type=['success','error','warning','info'].includes(type)?type:'info';var titles={success:'Completed',error:'Action failed',warning:'Please note',info:'Information'},icons={success:'check_circle',error:'error',warning:'warning',info:'info'};var t=document.createElement('div');t.className='az-toast az-toast--'+type;t.dataset.azToast='';t.setAttribute('role',type==='error'?'alert':'status');t.innerHTML='<div class="az-toast__icon" aria-hidden="true"><span class="material-symbols-outlined">'+icons[type]+'</span></div><div class="az-toast__content"><strong>'+titles[type]+'</strong><p></p></div><button type="button" class="az-toast__close" data-az-toast-close aria-label="Dismiss notification"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>';t.querySelector('p').textContent=String(message);region.appendChild(t);init(region)};
})();
</script>
