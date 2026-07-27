<style>
.az-toast-region{position:fixed;top:18px;right:18px;z-index:2147483000;display:grid;gap:10px;width:min(390px,calc(100vw - 36px));pointer-events:none;font-family:inherit}
.az-toast{--tone:#173f35;--soft:#edf5f2;position:relative;display:grid!important;grid-template-columns:38px minmax(0,1fr) 32px!important;align-items:center!important;gap:12px!important;width:100%!important;min-height:72px!important;max-height:180px!important;margin:0!important;padding:14px!important;overflow:hidden!important;box-sizing:border-box!important;color:#173f35!important;background:#fffdf9!important;border:1px solid rgba(23,63,53,.13)!important;border-left:4px solid var(--tone)!important;border-radius:14px!important;box-shadow:0 18px 48px rgba(18,42,36,.18)!important;pointer-events:auto!important;transform:translate3d(0,0,0);animation:azToastIn .22s ease-out both}
.az-toast--success{--tone:#237a57;--soft:#e8f5ee}.az-toast--error{--tone:#b83b3b;--soft:#fbecec}.az-toast--warning{--tone:#b7791f;--soft:#fff4dd}.az-toast--info{--tone:#356c8c;--soft:#e9f3f8}
.az-toast__icon{display:grid!important;width:38px!important;height:38px!important;place-items:center!important;color:var(--tone)!important;background:var(--soft)!important;border-radius:50%!important}.az-toast__icon .material-symbols-outlined{font-size:21px!important}
.az-toast__content{min-width:0!important}.az-toast__content strong{display:block!important;margin:0 0 2px!important;color:#173f35!important;font-size:14px!important;line-height:1.25!important}.az-toast__content p{display:block!important;margin:0!important;overflow-wrap:anywhere!important;color:#68736f!important;font-size:13px!important;line-height:1.4!important}
.az-toast__close{display:grid!important;width:32px!important;height:32px!important;place-items:center!important;padding:0!important;color:#68736f!important;background:transparent!important;border:0!important;border-radius:50%!important;cursor:pointer!important}.az-toast__close:hover{color:#173f35!important;background:#eef2ef!important}.az-toast__close .material-symbols-outlined{font-size:19px!important}
.az-toast.is-leaving{animation:azToastOut .2s ease-in forwards}@keyframes azToastIn{from{opacity:0;transform:translate3d(24px,-4px,0) scale(.98)}to{opacity:1;transform:none}}@keyframes azToastOut{to{opacity:0;transform:translate3d(24px,0,0) scale(.98)}}
@media(max-width:640px){.az-toast-region{top:10px;right:10px;left:10px;width:auto}.az-toast{grid-template-columns:36px minmax(0,1fr) 30px!important;min-height:66px!important;padding:12px!important}.az-toast__icon{width:36px!important;height:36px!important}}
@media(prefers-reduced-motion:reduce){.az-toast,.az-toast.is-leaving{animation:none}}
</style>

<div class="az-toast-region" data-az-toast-region data-toast-region aria-live="polite" aria-atomic="true">
@php
    $toastItems = collect([
        ['success', session('success'), 'Completed', 'check_circle'],
        ['error', session('error'), 'Action failed', 'error'],
        ['warning', session('warning'), 'Please note', 'warning'],
        ['info', session('info') ?? session('status'), 'Information', 'info'],
    ])->filter(fn ($item) => filled($item[1]));
    if ($errors->any()) $toastItems->push(['error', $errors->first(), 'Please review the form', 'error']);
@endphp
@foreach($toastItems as [$type,$message,$title,$icon])
<div class="az-toast az-toast--{{ $type }}" data-az-toast role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <div class="az-toast__icon" aria-hidden="true"><span class="material-symbols-outlined">{{ $icon }}</span></div>
    <div class="az-toast__content"><strong>{{ $title }}</strong><p>{{ $message }}</p></div>
    <button type="button" class="az-toast__close" data-az-toast-close aria-label="Dismiss notification"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
</div>
@endforeach
</div>
<script>
(function(){
  function init(root){(root||document).querySelectorAll('[data-az-toast]:not([data-ready])').forEach(function(t){t.dataset.ready='1';var timer;function close(){if(t.classList.contains('is-leaving'))return;clearTimeout(timer);t.classList.add('is-leaving');setTimeout(function(){t.remove()},210)}var b=t.querySelector('[data-az-toast-close]');if(b)b.addEventListener('click',close);t.addEventListener('mouseenter',function(){clearTimeout(timer)});t.addEventListener('mouseleave',function(){timer=setTimeout(close,2500)});timer=setTimeout(close,5500)})}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){init(document)});else init(document);
  document.addEventListener('livewire:navigated',function(){init(document)});
  window.AzariToast=function(message,type){var region=document.querySelector('[data-az-toast-region]');if(!region||!message)return;type=['success','error','warning','info'].includes(type)?type:'info';var titles={success:'Completed',error:'Action failed',warning:'Please note',info:'Information'},icons={success:'check_circle',error:'error',warning:'warning',info:'info'};var t=document.createElement('div');t.className='az-toast az-toast--'+type;t.dataset.azToast='';t.setAttribute('role',type==='error'?'alert':'status');t.innerHTML='<div class="az-toast__icon"><span class="material-symbols-outlined">'+icons[type]+'</span></div><div class="az-toast__content"><strong>'+titles[type]+'</strong><p></p></div><button type="button" class="az-toast__close" data-az-toast-close aria-label="Dismiss notification"><span class="material-symbols-outlined">close</span></button>';t.querySelector('p').textContent=String(message);region.appendChild(t);init(region)};
})();
</script>
