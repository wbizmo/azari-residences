@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading"><div><span>CMS</span><h1>Branding and identity</h1></div></div>
    <form class="admin-form" method="POST" action="{{ route('azari.admin.settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <label>Site name<input name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'Resavar') }}" required></label>
        <label>Tagline<input name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}"></label>
        <label>Operating regions<input name="operating_regions" value="{{ old('operating_regions', $settings['operating_regions'] ?? 'Nigeria and Rwanda') }}"></label>
        <label>Main logo<input type="file" name="site_logo" accept="image/*"></label>
        <label>Favicon<input type="file" name="favicon" accept=".png,.ico,.svg,image/*"></label>
        <button class="button button-primary" type="submit">Save branding</button>
    </form>
@endsection
