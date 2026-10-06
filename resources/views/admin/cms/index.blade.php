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
        <button type="button" data-tab-target="promotion">Promotion</button>
        <button type="button" data-tab-target="seo">SEO</button>
        <button type="button" data-tab-target="media">Media</button>
    </div>

    <section class="az-admin-panel is-active" data-tab-panel="branding">
        <div class="az-form-section-head">
            <span class="material-symbols-outlined">branding_watermark</span>
            <div>
                <h2>Resavar identity</h2>
                <p>The approved production logos are bundled with the application and are not replaceable from the CMS.</p>
            </div>
        </div>

        <div class="az-cms-brand-preview">
            <div class="az-cms-brand-preview__light">
                <img src="{{ asset('images/logo-light.png') }}" alt="Resavar">
                <span>Primary logo on white</span>
            </div>
            <div class="az-cms-brand-preview__dark">
                <img src="{{ asset('images/logo-dark.png') }}" alt="Resavar">
                <span>White logo on deep navy</span>
            </div>
        </div>

        <form class="admin-form az-form-grid" method="POST" enctype="multipart/form-data" action="{{ route('azari.admin.cms.branding') }}">
            @csrf
            @method('PUT')

            <label class="az-field">
                <span>Website name</span>
                <input type="text" name="site_name" value="{{ old('site_name', $settings['site_name'] ?? 'Resavar') }}" required>
            </label>

            <label class="az-field">
                <span>Registered business name</span>
                <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name'] ?? 'Resavar Luxury Properties LTD') }}">
            </label>

            <label class="az-field az-span-2">
                <span>Tagline</span>
                <input type="text" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? 'Exceptional Stays, Everywhere.') }}">
            </label>

            <label class="az-field">
                <span>Support email</span>
                <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}">
            </label>

            <label class="az-field">
                <span>Support phone</span>
                <input type="tel" name="support_phone" value="{{ old('support_phone', $settings['support_phone'] ?? '') }}">
            </label>

            <label class="az-field az-span-2">
                <span>Physical address</span>
                <textarea name="physical_address" rows="3">{{ old('physical_address', $settings['physical_address'] ?? '') }}</textarea>
            </label>

            <label class="az-upload az-span-2">
                <span class="material-symbols-outlined">share</span>
                <strong>Social sharing image</strong>
                <small data-file-label>Optional JPG, PNG, WEBP or SVG</small>
                <input type="file" name="social_image" accept="image/*">
            </label>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save identity details</button>
            </div>
        </form>
    </section>

    <section class="az-admin-panel" data-tab-panel="theme">
        <div class="az-form-section-head">
            <span class="material-symbols-outlined">palette</span>
            <div>
                <h2>Resavar design system</h2>
                <p>The live interface is locked to deep navy, black and white with Montserrat typography.</p>
            </div>
        </div>

        <div class="az-cms-palette" aria-label="Approved interface palette">
            <div class="az-cms-palette__navy"><span></span><strong>Deep navy</strong><code>#052058</code></div>
            <div class="az-cms-palette__black"><span></span><strong>Black</strong><code>#000000</code></div>
            <div class="az-cms-palette__white"><span></span><strong>White</strong><code>#FFFFFF</code></div>
        </div>

        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.themes.store') }}">
            @csrf

            <label class="az-field az-span-2">
                <span>Revision name</span>
                <input type="text" name="name" value="{{ old('name', 'Resavar navy system') }}" required>
            </label>

            <div class="az-field">
                <span>Typography</span>
                <div class="az-cms-readonly">Montserrat · headings and body</div>
            </div>

            <div class="az-field">
                <span>Interface palette</span>
                <div class="az-cms-readonly">Deep navy · black · white</div>
            </div>

            <label class="az-range-field">
                <span>Border radius <output>10px</output></span>
                <input type="range" name="radius" min="0" max="30" value="{{ old('radius', 10) }}">
            </label>

            <label class="az-range-field">
                <span>Hero overlay <output>26%</output></span>
                <input type="range" name="hero_overlay" min="0" max="95" value="{{ old('hero_overlay', 26) }}">
            </label>

            <fieldset class="az-segmented az-span-2">
                <legend>Save state</legend>
                <label><input type="radio" name="status" value="draft" checked><span>Draft</span></label>
                <label><input type="radio" name="status" value="published"><span>Publish live</span></label>
            </fieldset>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save design revision</button>
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

        @if($themes->hasPages())
            <div class="az-pagination-block">{{ $themes->onEachSide(1)->links() }}</div>
        @endif
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

        @if($navigation->hasPages())
            <div class="az-pagination-block">{{ $navigation->onEachSide(1)->links() }}</div>
        @endif
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

        @if($sections->hasPages())
            <div class="az-pagination-block">{{ $sections->onEachSide(1)->links() }}</div>
        @endif

    <section class="az-admin-panel" data-tab-panel="promotion">
        <form class="admin-form az-form-grid az-cms-promotion-form" method="POST" action="{{ route('azari.admin.cms.promotion') }}">
            @csrf
            @method('PUT')

            <div class="az-form-section-head">
                <span class="material-symbols-outlined">campaign</span>
                <div>
                    <h2>Homepage promotion</h2>
                    <p>Manage the single promotional modal shown only on the homepage. A heading and description are always required. The button is optional; leave its text or URL empty and no promotion button will be displayed.</p>
                </div>
            </div>

            <label class="az-field az-span-2">
                <span>Promotion heading</span>
                <input name="title" maxlength="160" required value="{{ old('title', $promotion?->title) }}" placeholder="A special stay, thoughtfully offered">
            </label>

            <label class="az-field az-span-2">
                <span>Promotion description</span>
                <textarea name="body" rows="6" maxlength="10000" required placeholder="Describe the promotion guests will see.">{{ old('body', $promotion?->body) }}</textarea>
            </label>

            <label class="az-field">
                <span>Button text <small>(optional)</small></span>
                <input name="cta_label" maxlength="80" value="{{ old('cta_label', $promotion?->cta_label) }}" placeholder="View offer">
            </label>

            <label class="az-field">
                <span>Button URL <small>(optional)</small></span>
                <input name="cta_url" maxlength="500" value="{{ old('cta_url', $promotion?->cta_url) }}" placeholder="https://example.com/offer">
            </label>

            <div class="az-promotion-switch az-span-2">
                <span class="az-promotion-switch__copy">
                    <strong>Show promotional modal on the homepage</strong>
                    <small>Enable this switch to display the promotion on <code>/</code>. Disable it to keep the saved content without showing the modal.</small>
                </span>
                <label class="az-promotion-switch__control" for="show_on_homepage" aria-label="Show promotional modal on the homepage">
                    <input id="show_on_homepage" type="checkbox" name="show_on_homepage" value="1" @checked(old('show_on_homepage', (bool) ($promotion?->show_on_homepage ?? $promotion?->is_active)))>
                    <span class="az-promotion-switch__track" aria-hidden="true"><span></span></span>
                </label>
            </div>

            <div class="az-form-actions az-span-2">
                <button class="button button-primary" type="submit">Save homepage promotion</button>
            </div>
        </form>

        <article class="az-promotion-current">
            <div class="az-form-section-head">
                <span class="material-symbols-outlined">visibility</span>
                <div>
                    <h2>Current promotion content</h2>
                    <p>This is the content currently saved for the homepage modal.</p>
                </div>
            </div>

            @if($promotion)
                <dl class="az-promotion-preview-list">
                    <div><dt>Status</dt><dd>{{ ($promotion->show_on_homepage ?? $promotion->is_active) ? 'Enabled on homepage' : 'Disabled' }}</dd></div>
                    <div><dt>Heading</dt><dd>{{ $promotion->title }}</dd></div>
                    <div><dt>Description</dt><dd>{!! nl2br(e($promotion->body)) !!}</dd></div>
                    <div><dt>Button text</dt><dd>{{ $promotion->cta_label ?: 'No button text saved' }}</dd></div>
                    <div><dt>Button URL</dt><dd>{{ $promotion->cta_url ?: 'No button URL saved' }}</dd></div>
                    <div><dt>Button visibility</dt><dd>{{ filled($promotion->cta_label) && filled($promotion->cta_url) ? 'Visible and opens in a new tab' : 'Hidden because both button fields are required for display' }}</dd></div>
                </dl>
            @else
                <div class="production-empty-state">No homepage promotion has been saved yet.</div>
            @endif
        </article>
    </section>

    <section class="az-admin-panel" data-tab-panel="seo">
        <form class="admin-form az-form-grid" method="POST" action="{{ route('azari.admin.cms.seo') }}">
            @csrf
            @method('PUT')
            <div class="az-form-section-head"><span class="material-symbols-outlined">travel_explore</span><div><h2>SEO and social metadata</h2><p>Control search previews, indexing and social sharing.</p></div></div>
            <label class="az-field az-span-2"><span>SEO title</span><input name="seo_title" maxlength="70" value="{{ old('seo_title', $settings['seo_title'] ?? 'Resavar') }}" required></label>
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
        @if($media->hasPages())
            <div class="az-pagination-block">{{ $media->onEachSide(1)->links() }}</div>
        @endif
    </section>
@endsection

@push('head')
<style>
.az-cms-brand-preview{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0 0 24px}
.az-cms-brand-preview>div{min-height:170px;display:flex;flex-direction:column;align-items:flex-start;justify-content:space-between;gap:20px;padding:22px;border:1px solid rgba(5,32,88,.16);border-radius:14px}
.az-cms-brand-preview__light{background:#FFFFFF;color:#052058}
.az-cms-brand-preview__dark{background:#052058;color:#FFFFFF}
.az-cms-brand-preview img{display:block;width:min(210px,80%);height:auto;max-height:72px;object-fit:contain;object-position:left center}
.az-cms-brand-preview span{font-size:10px;font-weight:800;letter-spacing:.10em;text-transform:uppercase}
.az-cms-palette{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 24px}
.az-cms-palette>div{display:grid;grid-template-columns:42px 1fr;grid-template-areas:"swatch title" "swatch code";gap:2px 12px;align-items:center;padding:14px;border:1px solid rgba(5,32,88,.16);border-radius:12px;background:#FFFFFF;color:#052058}
.az-cms-palette>div>span{grid-area:swatch;width:42px;height:42px;border-radius:9px;border:1px solid rgba(5,32,88,.18)}
.az-cms-palette__navy>span{background:#052058}.az-cms-palette__black>span{background:#000000}.az-cms-palette__white>span{background:#FFFFFF}
.az-cms-palette strong{grid-area:title;font-size:11px}.az-cms-palette code{grid-area:code;color:#052058;font-size:10px}
.az-cms-readonly{min-height:44px;display:flex;align-items:center;padding:0 12px;border:1px solid rgba(5,32,88,.18);border-radius:9px;background:#FFFFFF;color:#052058;font-size:12px;font-weight:700}
.az-cms-promotion-form,.az-promotion-current{max-width:100%;min-width:0}
.az-promotion-switch{display:flex;align-items:center;justify-content:space-between;gap:24px;width:100%;min-width:0;padding:20px;border:1px solid rgba(5,32,88,.16);border-radius:14px;background:#FFFFFF;color:#052058;box-sizing:border-box}
.az-promotion-switch__copy{display:grid;min-width:0;gap:5px}.az-promotion-switch__copy strong,.az-promotion-switch__copy small{color:#052058}
.az-promotion-switch__control{position:relative;display:inline-flex;align-items:center;flex:0 0 auto;cursor:pointer}
.az-promotion-switch__control input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.az-promotion-switch__track{position:relative;display:block;width:48px;height:26px;border:1px solid #052058;border-radius:999px;background:#FFFFFF;transition:.18s ease}
.az-promotion-switch__track span{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#052058;transition:.18s ease}
.az-promotion-switch__control input:checked+.az-promotion-switch__track{background:#052058}
.az-promotion-switch__control input:checked+.az-promotion-switch__track span{transform:translateX(22px);background:#FFFFFF}
.az-promotion-current{margin-top:22px;padding:clamp(20px,3vw,30px);border:1px solid rgba(5,32,88,.16);border-radius:14px;background:#FFFFFF}
.az-promotion-preview-list{display:grid;margin:18px 0 0;border:1px solid rgba(5,32,88,.14);border-radius:12px;overflow:hidden}
.az-promotion-preview-list>div{display:grid;grid-template-columns:minmax(150px,.32fr) minmax(0,1fr);gap:18px;padding:15px 17px;border-bottom:1px solid rgba(5,32,88,.12)}
.az-promotion-preview-list>div:last-child{border-bottom:0}.az-promotion-preview-list dt,.az-promotion-preview-list dd{color:#052058}.az-promotion-preview-list dd{min-width:0;margin:0;overflow-wrap:anywhere;line-height:1.6}
@media(max-width:700px){.az-cms-brand-preview,.az-cms-palette{grid-template-columns:1fr}.az-promotion-switch{align-items:flex-start}.az-promotion-preview-list>div{grid-template-columns:1fr;gap:6px}}
</style>
@endpush
