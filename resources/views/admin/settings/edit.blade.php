@extends('admin.layouts.app')

@section('title', 'Brand settings')
@section('section-label', 'Brand system')

@section('content')
    <div class="az-admin-page-head">
        <div>
            <span class="az-eyebrow">Resavar identity</span>
            <h1>Brand settings</h1>
            <p>The production Resavar logos are bundled with the application and cannot be replaced from the CMS.</p>
        </div>
    </div>

    <section class="az-admin-card">
        <div class="az-admin-card__header">
            <div>
                <h2>Identity details</h2>
                <p>Update public-facing brand text without changing the approved logo assets.</p>
            </div>
        </div>

        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.settings.update') }}">
            @csrf
            @method('PUT')

            <label class="az-field">
                <span>Site name</span>
                <input name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'Resavar') }}" required>
            </label>

            <label class="az-field">
                <span>Tagline</span>
                <input name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? 'Exceptional Stays, Everywhere.') }}">
            </label>

            <label class="az-field az-span-2">
                <span>Operating regions</span>
                <input name="operating_regions" value="{{ old('operating_regions', $settings['operating_regions'] ?? '') }}">
            </label>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save brand settings</button>
            </div>
        </form>
    </section>
@endsection
