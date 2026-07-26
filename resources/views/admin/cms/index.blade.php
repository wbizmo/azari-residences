@extends('admin.layouts.app')
@section('content')
    <div class="admin-heading">
        <div>
            <span>SPRINT 03</span>
            <h1>CMS and visual identity</h1>
            <p>Manage brand assets, theme revisions, navigation, homepage structure, SEO and reusable media.</p>
        </div>
        <a class="button button-secondary" href="{{ url('/') }}" target="_blank">Preview website</a>
    </div>

    <div class="az-admin-tabs" data-admin-tabs>
        <button type="button" class="is-active" data-tab-target="branding">Branding</button>
        <button type="button" data-tab-target="theme">Theme</button>
        <button type="button" data-tab-target="navigation">Navigation</button>
        <button type="button" data-tab-target="homepage">Homepage</button>
        <button type="button" data-tab-target="seo">SEO</button>
        <button type="button" data-tab-target="media">Media</button>
    </div>

    <section class="az-admin-panel is-active" data-tab-panel="branding">
        <form class="admin-form az-form-grid" method="POST" enctype="multipart/form-data" action="{{ route('azari.admin.cms.branding') }}">
            @csrf
            @method('PUT')
            <div class="az-form-section-head">
                <span class="material-symbols-outlined">branding_watermark</span>
                <div><h2>Brand identity</h2><p>Uploaded assets replace all temporary logo placeholders.</p></div>
            </div>

            <label class="az-field">
                <span>Website name</span>
                <input type="text" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'Azari Residences') }}" required>
            </label>

            <label class="az-field">
                <span>Registered business name</span>
                <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name'] ?? 'Azari Luxury Properties LTD') }}">
            </label>

            <label class="az-field az-span-2">
                <span>Tagline</span>
                <input type="text" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}">
            </label>

            <label class="az-field">
                <span>Support email</span>
                <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}">
            </label>

            <label class="az-field">
                <span>Support phone</span>
                <input type="tel" name="support_phone" value="{{ old('support_phone', $settings['support_phone'] ?? '') }}">
            </label>

            @foreach([
                'site_logo' => 'Primary logo',
                'light_logo' => 'Light logo',
                'dark_logo' => 'Dark logo',
                'footer_logo' => 'Footer logo',
                'favicon' => 'Favicon',
                'social_image' => 'Social sharing image',
            ] as $name => $label)
                <label class="az-upload">
                    <span class="material-symbols-outlined">upload_file</span>
                    <strong>{{ $label }}</strong>
                    <small data-file-label>Select a file</small>
                    <input type="file" name="{{ $name }}" @if($name !== 'favicon') accept="image/*" @endif>
                </label>
            @endforeach

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save branding</button>
            </div>
        </form>
    </section>

    <section class="az-admin-panel" data-tab-panel="theme">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.themes.store') }}">
            @csrf
            <div class="az-form-section-head">
                <span class="material-symbols-outlined">palette</span>
                <div><h2>Theme editor</h2><p>Save drafts or publish a complete visual revision.</p></div>
            </div>

            <label class="az-field az-span-2">
                <span>Revision name</span>
                <input type="text" name="name" value="{{ old('name', 'Azari premium theme') }}" required>
            </label>

            @foreach([
                'primary' => ['Primary', '#12211b'],
                'secondary' => ['Secondary', '#f3ecdd'],
                'accent' => ['Accent', '#bb8a3e'],
                'background' => ['Background', '#f3ecdd'],
                'surface' => ['Surface', '#ffffff'],
                'text' => ['Text', '#1c231f'],
                'muted' => ['Muted text', '#6e7268'],
            ] as $name => [$label, $default])
                <label class="az-color-field">
                    <span>{{ $label }}</span>
                    <span class="az-color-control">
                        <input type="color" name="{{ $name }}" value="{{ old($name, $settings["theme_{$name}"] ?? $default) }}">
                        <output>{{ old($name, $settings["theme_{$name}"] ?? $default) }}</output>
                    </span>
                </label>
            @endforeach

            <label class="az-field">
                <span>Heading font</span>
                <select name="heading_font">
                    @foreach(['Fraunces', 'Cormorant Garamond', 'Playfair Display'] as $font)
                        <option value="{{ $font }}">{{ $font }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-field">
                <span>Body font</span>
                <select name="body_font">
                    @foreach(['Public Sans', 'Inter', 'Manrope'] as $font)
                        <option value="{{ $font }}">{{ $font }}</option>
                    @endforeach
                </select>
            </label>

            <label class="az-range-field">
                <span>Border radius <output>8px</output></span>
                <input type="range" name="radius" min="0" max="30" value="8">
            </label>

            <label class="az-range-field">
                <span>Hero overlay <output>68%</output></span>
                <input type="range" name="hero_overlay" min="0" max="95" value="68">
            </label>

            <fieldset class="az-segmented az-span-2">
                <legend>Save state</legend>
                <label><input type="radio" name="status" value="draft" checked><span>Draft</span></label>
                <label><input type="radio" name="status" value="published"><span>Publish live</span></label>
            </fieldset>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save theme revision</button>
            </div>
        </form>

        <div class="az-card-grid">
            @forelse($themes as $theme)
                <article class="az-summary-card">
                    <span class="az-status az-status--{{ $theme->status }}">{{ ucfirst($theme->status) }}</span>
                    <h3>{{ $theme->name }}</h3>
                    <p>{{ $theme->created_at->diffForHumans() }}</p>
                </article>
            @empty
                <div class="production-empty-state">No theme revisions yet.</div>
            @endforelse
        </div>
    </section>

    <section class="az-admin-panel" data-tab-panel="navigation">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.navigation.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">menu</span><div><h2>Add navigation item</h2><p>Header, footer and mobile navigation are independently configurable.</p></div></div>
            <label class="az-field"><span>Label</span><input name="label" required></label>
            <label class="az-field"><span>URL</span><input name="url" value="/"></label>
            <label class="az-field"><span>Location</span><select name="location"><option value="header">Header</option><option value="footer">Footer</option><option value="mobile">Mobile</option></select></label>
            <label class="az-field"><span>Target</span><select name="target"><option value="_self">Same window</option><option value="_blank">New window</option></select></label>
            <label class="az-field"><span>Material icon</span><input name="icon" placeholder="apartment"></label>
            <label class="az-toggle"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Published</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Add navigation item</button></div>
        </form>

        <form method="POST" action="{{ route('azari.admin.cms.navigation.sort') }}" data-sort-form>
            @csrf
            @method('PUT')
            <div class="az-sortable" data-sortable>
                @foreach($navigation as $item)
                    <article class="az-sort-row" draggable="true" data-sort-id="{{ $item->id }}">
                        <span class="material-symbols-outlined az-drag-handle">drag_indicator</span>
                        <div><strong>{{ $item->label }}</strong><small>{{ $item->location }} · {{ $item->url }}</small></div>
                        <span class="az-status">{{ $item->is_active ? 'Published' : 'Hidden' }}</span>
                        <button class="az-icon-button" type="submit" form="delete-nav-{{ $item->id }}"><span class="material-symbols-outlined">delete</span></button>
                    </article>
                @endforeach
            </div>
            <div data-sort-inputs></div>
            <button class="button button-secondary" type="submit">Save navigation order</button>
        </form>
        @foreach($navigation as $item)
            <form id="delete-nav-{{ $item->id }}" method="POST" action="{{ route('azari.admin.cms.navigation.delete', $item) }}">@csrf @method('DELETE')</form>
        @endforeach
    </section>

    <section class="az-admin-panel" data-tab-panel="homepage">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.sections.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">view_quilt</span><div><h2>Homepage builder</h2><p>Create, publish and reorder homepage sections.</p></div></div>
            <label class="az-field"><span>Internal key</span><input name="key" placeholder="seasonal_offer" required></label>
            <label class="az-field"><span>Section name</span><input name="name" required></label>
            <label class="az-field"><span>Section type</span><select name="type"><option value="content">Content</option><option value="hero">Hero</option><option value="properties">Properties</option><option value="services">Services</option><option value="gallery">Gallery</option><option value="cta">Call to action</option></select></label>
            <label class="az-field"><span>Status</span><select name="status"><option value="draft">Draft</option><option value="published">Published</option></select></label>
            <label class="az-field az-span-2"><span>Heading</span><input name="heading"></label>
            <label class="az-field az-span-2"><span>Body</span><textarea name="body" rows="5"></textarea></label>
            <label class="az-field"><span>Button label</span><input name="button_label"></label>
            <label class="az-field"><span>Button URL</span><input name="button_url"></label>
            <label class="az-toggle az-span-2"><input type="checkbox" name="is_active" value="1" checked><span class="az-toggle-track"></span><span>Visible on homepage</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Create section</button></div>
        </form>

        <form method="POST" action="{{ route('azari.admin.cms.sections.sort') }}" data-sort-form>
            @csrf
            @method('PUT')
            <div class="az-sortable" data-sortable>
                @foreach($sections as $section)
                    <article class="az-sort-row" draggable="true" data-sort-id="{{ $section->id }}">
                        <span class="material-symbols-outlined az-drag-handle">drag_indicator</span>
                        <div><strong>{{ $section->name }}</strong><small>{{ $section->type }} · {{ $section->key }}</small></div>
                        <span class="az-status az-status--{{ $section->status }}">{{ ucfirst($section->status) }}</span>
                        <button class="az-icon-button" type="submit" form="delete-section-{{ $section->id }}"><span class="material-symbols-outlined">delete</span></button>
                    </article>
                @endforeach
            </div>
            <div data-sort-inputs></div>
            <button class="button button-secondary" type="submit">Save section order</button>
        </form>
        @foreach($sections as $section)
            <form id="delete-section-{{ $section->id }}" method="POST" action="{{ route('azari.admin.cms.sections.delete', $section) }}">@csrf @method('DELETE')</form>
        @endforeach
    </section>

    <section class="az-admin-panel" data-tab-panel="seo">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.seo') }}">
            @csrf
            @method('PUT')
            <div class="az-form-section-head"><span class="material-symbols-outlined">travel_explore</span><div><h2>SEO and social metadata</h2><p>Control search previews, indexing and social sharing.</p></div></div>
            <label class="az-field az-span-2"><span>SEO title</span><input name="seo_title" maxlength="70" value="{{ old('seo_title', $settings['seo_title'] ?? 'Azari Residences') }}" required></label>
            <label class="az-field az-span-2"><span>Meta description</span><textarea name="seo_description" maxlength="180" rows="3" required>{{ old('seo_description', $settings['seo_description'] ?? '') }}</textarea></label>
            <label class="az-field az-span-2"><span>Canonical domain</span><input type="url" name="canonical_domain" value="{{ old('canonical_domain', $settings['canonical_domain'] ?? '') }}"></label>
            <label class="az-field"><span>Open Graph title</span><input name="og_title" value="{{ old('og_title', $settings['og_title'] ?? '') }}"></label>
            <label class="az-field"><span>X/Twitter title</span><input name="twitter_title" value="{{ old('twitter_title', $settings['twitter_title'] ?? '') }}"></label>
            <label class="az-field"><span>Open Graph description</span><textarea name="og_description" rows="3">{{ old('og_description', $settings['og_description'] ?? '') }}</textarea></label>
            <label class="az-field"><span>X/Twitter description</span><textarea name="twitter_description" rows="3">{{ old('twitter_description', $settings['twitter_description'] ?? '') }}</textarea></label>
            <label class="az-toggle"><input type="checkbox" name="robots_index" value="1" checked><span class="az-toggle-track"></span><span>Allow indexing</span></label>
            <label class="az-toggle"><input type="checkbox" name="robots_follow" value="1" checked><span class="az-toggle-track"></span><span>Allow link following</span></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Save SEO settings</button></div>
        </form>
    </section>

    <section class="az-admin-panel" data-tab-panel="media">
        <form class="admin-form az-form-grid" method="POST" enctype="multipart/form-data" action="{{ route('azari.admin.cms.media.store') }}">
            @csrf
            <div class="az-form-section-head"><span class="material-symbols-outlined">perm_media</span><div><h2>Media library</h2><p>Upload reusable images, documents and supported video files.</p></div></div>
            <label class="az-upload az-span-2"><span class="material-symbols-outlined">cloud_upload</span><strong>Drop or select media</strong><small data-file-label>JPG, PNG, WEBP, SVG, PDF, MP4 or WEBM</small><input type="file" name="file" required></label>
            <label class="az-field"><span>Title</span><input name="title"></label>
            <label class="az-field"><span>Alternative text</span><input name="alt_text"></label>
            <label class="az-field"><span>Caption</span><textarea name="caption" rows="3"></textarea></label>
            <label class="az-field"><span>Description</span><textarea name="description" rows="3"></textarea></label>
            <div class="az-form-actions az-span-2"><button class="button button-primary">Upload media</button></div>
        </form>

        <div class="az-media-grid">
            @forelse($media as $asset)
                <article class="az-media-card">
                    @if(str_starts_with($asset->mime_type, 'image/'))
                        <img src="{{ $asset->url }}" alt="{{ $asset->alt_text }}">
                    @else
                        <span class="material-symbols-outlined">draft</span>
                    @endif
                    <div><strong>{{ $asset->title ?: $asset->original_name }}</strong><small>{{ number_format($asset->size / 1024, 1) }} KB</small></div>
                    <form method="POST" action="{{ route('azari.admin.cms.media.archive', $asset) }}">@csrf @method('PUT')<button class="az-icon-button"><span class="material-symbols-outlined">archive</span></button></form>
                </article>
            @empty
                <div class="production-empty-state">No media uploaded.</div>
            @endforelse
        </div>
    </section>
<x-azari-pagination-stack :items="get_defined_vars()" />
@endsection
