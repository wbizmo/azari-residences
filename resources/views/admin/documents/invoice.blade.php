@extends('admin.layouts.app')

@section('content')
<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#052058}.brand{font-size:24px;font-weight:700}.logo{max-height:56px}.row{display:flex;justify-content:space-between}.total{font-size:22px;font-weight:700}</style></head><body>
<div class="row"><div>@if(!empty($logo))<img class="logo" src="{{ $logo }}" alt="Resarva">@else<div class="brand">Resarva</div>@endif</div><div>INVOICE<br>{{ $invoiceNumber ?? '' }}</div></div>
<hr><p>Booking: {{ $booking->booking_number ?? '' }}</p><p class="total">Total: {{ $total ?? '' }}</p><p>{{ $footer ?? '' }}</p>
</body></html>

@endsection
