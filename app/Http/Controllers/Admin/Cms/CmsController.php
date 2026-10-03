<?php

namespace App\Http\Controllers\Admin\Cms;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\Promotion;
use App\Models\SiteSetting;
use App\Models\ThemeRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmsController extends Controller
{
    public function index(): View
    {
        return view('admin.cms.index', [
            'settings' => SiteSetting::query()->pluck('value', 'key'),
            'navigation' => NavigationItem::query()->orderBy('location')->orderBy('sort_order')->paginate(10, ['*'], 'navigation_page')->withQueryString(),
            'sections' => HomepageSection::query()->orderBy('sort_order')->paginate(10, ['*'], 'sections_page')->withQueryString(),
            'media' => MediaAsset::query()->where('is_archived', false)->latest()->paginate(12, ['*'], 'media_page')->withQueryString(),
            'themes' => ThemeRevision::query()->latest()->paginate(10, ['*'], 'themes_page')->withQueryString(),
            'promotion' => Promotion::query()->where('type', 'promotion')->latest('updated_at')->first(),
        ]);
    }

    public function branding(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'business_name' => ['nullable', 'string', 'max:160'],
            'site_tagline' => ['nullable', 'string', 'max:180'],
            'support_email' => ['nullable', 'email:rfc', 'max:190'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'physical_address' => ['nullable', 'string', 'max:500'],
            'site_logo' => ['nullable', 'image', 'max:6144'],
            'light_logo' => ['nullable', 'image', 'max:6144'],
            'dark_logo' => ['nullable', 'image', 'max:6144'],
            'footer_logo' => ['nullable', 'image', 'max:6144'],
            'social_image' => ['nullable', 'image', 'max:6144'],
        ]);

        foreach ([
            'site_name', 'business_name', 'site_tagline',
            'support_email', 'support_phone', 'physical_address',
        ] as $key) {
            SiteSetting::put($key, $data[$key] ?? null, 'text', 'branding');
        }

        foreach (['site_logo', 'light_logo', 'dark_logo', 'footer_logo', 'social_image'] as $key) {
            if (! $request->hasFile($key)) {
                continue;
            }

            $old = SiteSetting::valueFor($key);
            if ($old) {
                Storage::disk('public')->delete($old);
            }

            SiteSetting::put(
                $key,
                $request->file($key)->store('site/branding', 'public'),
                'image',
                'branding'
            );
        }

        return back()->with('status', 'Branding and business identity updated.');
    }

    public function theme(Request $request): RedirectResponse
    {
        $settings = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'surface' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'muted' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'heading_font' => ['required', Rule::in(['Montserrat'])],
            'body_font' => ['required', Rule::in(['Montserrat'])],
            'radius' => ['required', 'integer', 'between:0,30'],
            'hero_overlay' => ['required', 'integer', 'between:0,95'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);

        $revision = ThemeRevision::query()->create([
            'name' => $settings['name'],
            'settings' => collect($settings)->except(['name', 'status'])->all(),
            'status' => $settings['status'],
            'created_by' => $request->user()->id,
            'published_at' => $settings['status'] === 'published' ? now() : null,
        ]);

        if ($revision->status === 'published') {
            DB::transaction(function () use ($revision): void {
                ThemeRevision::query()
                    ->whereKeyNot($revision->id)
                    ->where('status', 'published')
                    ->update(['status' => 'archived']);

                foreach ($revision->settings as $key => $value) {
                    SiteSetting::put("theme_{$key}", $value, 'theme', 'theme');
                }
            });
        }

        return back()->with('status', 'Theme revision saved.');
    }

    public function navigationStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:500'],
            'location' => ['required', Rule::in(['header', 'footer', 'mobile'])],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'icon' => ['nullable', 'string', 'max:80'],
            'parent_id' => ['nullable', 'exists:navigation_items,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = (int) NavigationItem::query()
            ->where('location', $data['location'])
            ->max('sort_order') + 10;
        $data['is_active'] = $request->boolean('is_active');

        NavigationItem::query()->create($data);

        return back()->with('status', 'Navigation item created.');
    }

    public function navigationDelete(NavigationItem $navigationItem): RedirectResponse
    {
        $navigationItem->delete();

        return back()->with('status', 'Navigation item removed.');
    }

    public function navigationSort(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer', 'exists:navigation_items,id'],
        ]);

        foreach ($data['items'] as $index => $id) {
            NavigationItem::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }

        return back()->with('status', 'Navigation order updated.');
    }

    public function sectionStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'alpha_dash', 'max:100', 'unique:homepage_sections,key'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['hero', 'content', 'properties', 'services', 'gallery', 'cta'])],
            'heading' => ['nullable', 'string', 'max:240'],
            'body' => ['nullable', 'string', 'max:10000'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        HomepageSection::query()->create([
            'key' => $data['key'],
            'name' => $data['name'],
            'type' => $data['type'],
            'content' => collect($data)->only(['heading', 'body', 'button_label', 'button_url'])->all(),
            'status' => $data['status'],
            'is_active' => $request->boolean('is_active'),
            'sort_order' => ((int) HomepageSection::query()->max('sort_order')) + 10,
        ]);

        return back()->with('status', 'Homepage section created.');
    }

    public function sectionDelete(HomepageSection $homepageSection): RedirectResponse
    {
        $homepageSection->delete();

        return back()->with('status', 'Homepage section deleted.');
    }

    public function sectionSort(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['integer', 'exists:homepage_sections,id'],
        ]);

        foreach ($data['items'] as $index => $id) {
            HomepageSection::query()->whereKey($id)->update(['sort_order' => ($index + 1) * 10]);
        }

        return back()->with('status', 'Homepage section order updated.');
    }

    public function seo(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seo_title' => ['required', 'string', 'max:70'],
            'seo_description' => ['required', 'string', 'max:180'],
            'canonical_domain' => ['nullable', 'url', 'max:255'],
            'robots_index' => ['nullable', 'boolean'],
            'robots_follow' => ['nullable', 'boolean'],
            'og_title' => ['nullable', 'string', 'max:100'],
            'og_description' => ['nullable', 'string', 'max:220'],
            'twitter_title' => ['nullable', 'string', 'max:100'],
            'twitter_description' => ['nullable', 'string', 'max:220'],
        ]);

        foreach ($data as $key => $value) {
            SiteSetting::put($key, $value, 'seo', 'seo');
        }

        SiteSetting::put('robots_index', $request->boolean('robots_index') ? '1' : '0', 'boolean', 'seo');
        SiteSetting::put('robots_follow', $request->boolean('robots_follow') ? '1' : '0', 'boolean', 'seo');

        return back()->with('status', 'SEO and social metadata updated.');
    }

    public function mediaStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg,pdf,mp4,webm', 'max:20480'],
            'title' => ['nullable', 'string', 'max:180'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        $file = $request->file('file');
        $path = $file->store('media', 'public');

        MediaAsset::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'title' => $data['title'] ?? null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        return back()->with('status', 'Media uploaded.');
    }

    public function mediaArchive(MediaAsset $mediaAsset): RedirectResponse
    {
        $mediaAsset->update(['is_archived' => true]);

        return back()->with('status', 'Media archived.');
    }
    public function promotion(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'show_on_homepage' => ['nullable', 'boolean'],
        ]);

        $promotion = DB::transaction(function () use ($request, $data): Promotion {
            $promotion = Promotion::query()
                ->where('type', 'promotion')
                ->latest('updated_at')
                ->first() ?? new Promotion();

            $promotion->fill([
                'title' => $data['title'],
                'slug' => $promotion->slug ?: Str::slug($data['title']).'-homepage',
                'type' => 'promotion',
                'summary' => null,
                'body' => $data['body'],
                'cta_label' => filled($data['cta_label'] ?? null) ? trim($data['cta_label']) : null,
                'cta_url' => filled($data['cta_url'] ?? null) ? trim($data['cta_url']) : null,
                'is_active' => $request->boolean('show_on_homepage'),
                'is_featured' => true,
                'show_on_homepage' => $request->boolean('show_on_homepage'),
                'starts_at' => null,
                'ends_at' => null,
                'sort_order' => 0,
            ]);
            $promotion->save();

            Promotion::query()
                ->where('type', 'promotion')
                ->whereKeyNot($promotion->getKey())
                ->delete();

            return $promotion;
        });

        return back()
            ->with('status', 'Homepage promotion settings saved.')
            ->with('active_cms_tab', 'promotion');
    }
}
