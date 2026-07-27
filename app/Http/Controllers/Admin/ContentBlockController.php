<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentBlockController extends Controller
{
    public function index(): View
    {
        return view('admin.content.index', [
            'blocks' => ContentBlock::query()->orderBy('page')->orderBy('sort_order')->paginate(config('azari.pagination.per_page', 10), ['*'], 'blocks_page')->withQueryString(),
        ]);
    }

    public function update(Request $request, ContentBlock $contentBlock): RedirectResponse
    {
        $data = $request->validate([
            'value' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $contentBlock->update([
            'value' => $data['value'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', "{$contentBlock->label} updated.");
    }
}
