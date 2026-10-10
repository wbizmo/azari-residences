@extends('layouts.user')
@section('title','Suggested stays')
@section('kicker','Explore stays')
@section('page_title','Suggested stays')
@section('content')
<section class="az-user-panel">
  <header class="az-user-panel-header"><div><h2 class="az-user-panel-title">Available stays for your dates</h2><p class="az-user-panel-subtitle">{{ $personalized ? 'Explanations use only your own recent and saved preferences.' : 'Showing neutral availability-based recommendations.' }} Prices are indicative and rechecked at checkout.</p></div></header>
  <div class="az-user-panel-body az-user-list">
    @forelse($items as $item)
      <a class="az-user-list-item" href="{{ $item['url'] }}">
        <div><h3>{{ $item['name'] }}</h3><p>{{ $item['reason'] }}</p></div>
        <div><strong>{{ $item['currency'] }} {{ number_format((float) $item['total'], 2) }}</strong><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></div>
      </a>
    @empty
      <div class="az-user-empty"><p>No available stays match those dates. Try a different search.</p><a href="{{ route('home') }}" class="az-user-button az-user-button--light">Explore stays</a></div>
    @endforelse
  </div>
</section>
@endsection
