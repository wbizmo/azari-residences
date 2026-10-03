<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => SiteSetting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_tagline' => ['nullable', 'string', 'max:180'],
            'operating_regions' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (['site_name', 'site_tagline', 'operating_regions'] as $key) {
            SiteSetting::put($key, $data[$key] ?? null, 'text', 'branding');
        }

        return back()->with('status', 'Branding settings updated.');
    }
}
