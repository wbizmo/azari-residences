@extends('admin.layouts.app')
@section('content')
<section class="admin-page"><header class="admin-page-header"><div><span class="eyebrow">Availability</span><h1>{{ $month->format('F Y') }}</h1></div><a class="button button-secondary" href="{{ route('azari.admin.s56.bookings.index') }}">Booking operations</a></header>
<form method="GET" class="az-filter-bar"><input type="month" name="month" value="{{ $month->format('Y-m') }}"><button class="button button-primary">View month</button></form>
<div class="az-s56-admin-grid"><article class="admin-card"><h2>Bookings</h2>@forelse($bookings as $b)<p><strong>{{ $b->reference }}</strong><br>{{ $b->property?->name }}<br>{{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M') }}</p>@empty<p>No bookings.</p>@endforelse{{ $bookings->links() }}</article>
<article class="admin-card"><h2>Active holds</h2>@forelse($holds as $h)<p><strong>{{ $h->property?->name }}</strong><br>{{ $h->check_in->format('d M') }} – {{ $h->check_out->format('d M') }}</p>@empty<p>No holds.</p>@endforelse{{ $holds->links() }}</article>
<article class="admin-card"><h2>Maintenance</h2>@forelse($maintenance as $m)<p><strong>{{ $m->title }}</strong><br>{{ $m->property?->name }}<br>{{ $m->starts_on->format('d M') }} – {{ $m->ends_on->format('d M') }}</p>@empty<p>No maintenance periods.</p>@endforelse{{ $maintenance->links() }}</article></div>
</section>@endsection
