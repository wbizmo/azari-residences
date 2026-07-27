@extends('admin.layout')
@section('title','Promotions and content')
@section('content')
<section class="az-premium-page-head">
    <div><span class="az-premium-kicker"><span class="material-symbols-outlined">campaign</span> Homepage marketing</span><h1>Promotions</h1><p>Create scheduled promotional content and choose whether it should appear as the single homepage popup. When several qualify, the featured item with the lowest sort order is selected.</p></div>
</section>

<section class="az-premium-card">
    <div class="az-premium-card-head"><div><h2>Create promotion</h2><p>Use a concise title, a useful summary and one clear call to action.</p></div></div>
    <form method="post" action="{{ route('azari.admin.promotions.store') }}" enctype="multipart/form-data" class="az-premium-form">
        @csrf
        <label class="az-premium-field"><span>Title</span><input name="title" value="{{ old('title') }}" required maxlength="160"></label>
        <label class="az-premium-field"><span>Content type</span><select name="type">@foreach(['promotion','featured_offer','local_guide','testimonial','seasonal_message','booking_notice','homepage_section'] as $t)<option value="{{ $t }}" @selected(old('type','promotion')===$t)>{{ ucwords(str_replace('_',' ',$t)) }}</option>@endforeach</select></label>
        <label class="az-premium-field is-full"><span>Summary</span><textarea name="summary" maxlength="500">{{ old('summary') }}</textarea></label>
        <label class="az-premium-field is-full"><span>Body</span><textarea name="body">{{ old('body') }}</textarea></label>
        <label class="az-premium-field"><span>Call-to-action label</span><input name="cta_label" value="{{ old('cta_label') }}" maxlength="80" placeholder="Explore the offer"></label>
        <label class="az-premium-field"><span>Call-to-action URL</span><input name="cta_url" value="{{ old('cta_url') }}" maxlength="500" placeholder="/availability"></label>
        <label class="az-premium-field"><span>Starts at</span><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label>
        <label class="az-premium-field"><span>Ends at</span><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"></label>
        <label class="az-premium-field"><span>Sort order</span><input type="number" min="0" name="sort_order" value="{{ old('sort_order',0) }}"></label>
        <label class="az-premium-field"><span>Popup image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="az-premium-toggle-row"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active',true))> Active</label><label><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured'))> Featured priority</label><label><input type="checkbox" name="show_on_homepage" value="1" @checked(old('show_on_homepage',true))> Show as homepage popup</label></div>
        <div class="az-premium-actions"><button class="az-premium-button"><span class="material-symbols-outlined">add</span>Create promotion</button></div>
    </form>
</section>

@foreach($promotions as $p)
<section class="az-premium-card">
    <div class="az-premium-card-head"><div><h2>{{ $p->title }}</h2><p>{{ ucwords(str_replace('_',' ',$p->type)) }} · Updated {{ $p->updated_at->diffForHumans() }}</p></div><span class="az-premium-status {{ $p->is_active ? '' : 'is-muted' }}">{{ $p->is_active ? 'Active' : 'Inactive' }}</span></div>
    <form method="post" action="{{ route('azari.admin.promotions.update',$p) }}" enctype="multipart/form-data" class="az-premium-form">
        @csrf @method('put')
        <label class="az-premium-field"><span>Title</span><input name="title" value="{{ $p->title }}" required maxlength="160"></label>
        <label class="az-premium-field"><span>Content type</span><select name="type">@foreach(['promotion','featured_offer','local_guide','testimonial','seasonal_message','booking_notice','homepage_section'] as $t)<option value="{{ $t }}" @selected($p->type===$t)>{{ ucwords(str_replace('_',' ',$t)) }}</option>@endforeach</select></label>
        <label class="az-premium-field is-full"><span>Summary</span><textarea name="summary">{{ $p->summary }}</textarea></label>
        <label class="az-premium-field is-full"><span>Body</span><textarea name="body">{{ $p->body }}</textarea></label>
        <label class="az-premium-field"><span>Call-to-action label</span><input name="cta_label" value="{{ $p->cta_label }}"></label>
        <label class="az-premium-field"><span>Call-to-action URL</span><input name="cta_url" value="{{ $p->cta_url }}"></label>
        <label class="az-premium-field"><span>Starts at</span><input type="datetime-local" name="starts_at" value="{{ optional($p->starts_at)->format('Y-m-d\\TH:i') }}"></label>
        <label class="az-premium-field"><span>Ends at</span><input type="datetime-local" name="ends_at" value="{{ optional($p->ends_at)->format('Y-m-d\\TH:i') }}"></label>
        <label class="az-premium-field"><span>Sort order</span><input type="number" min="0" name="sort_order" value="{{ $p->sort_order }}"></label>
        <label class="az-premium-field"><span>Replace popup image</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="az-premium-toggle-row"><label><input type="checkbox" name="is_active" value="1" @checked($p->is_active)> Active</label><label><input type="checkbox" name="is_featured" value="1" @checked($p->is_featured)> Featured priority</label><label><input type="checkbox" name="show_on_homepage" value="1" @checked($p->show_on_homepage)> Show as homepage popup</label></div>
        <div class="az-premium-actions"><button class="az-premium-button"><span class="material-symbols-outlined">save</span>Save changes</button></div>
    </form>
</section>
@endforeach
<div class="az-pagination-block">{{ $promotions->onEachSide(1)->links() }}</div>
@endsection
