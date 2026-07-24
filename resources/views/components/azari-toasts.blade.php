<div class="az-toast-region" data-toast-region aria-live="polite" aria-atomic="true">
@foreach(['success','error','warning','info','status'] as $type)@if(session($type))<div class="az-toast az-toast--{{ $type==='status'?'success':$type }}" data-server-toast><span>{{ session($type) }}</span><button type="button" aria-label="Dismiss">×</button></div>@endif @endforeach
@if($errors->any())<div class="az-toast az-toast--error" data-server-toast><span>{{ $errors->first() }}</span><button type="button" aria-label="Dismiss">×</button></div>@endif
</div>
