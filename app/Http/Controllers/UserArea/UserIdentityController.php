<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\IdentityAuditHistory;
use App\Models\IdentityType;
use App\Models\UserIdentityDocument;
use App\Services\Identity\DojahService;
use App\Services\Identity\IdentityDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserIdentityController extends Controller
{
    public function index(Request $request, DojahService $dojah): View
    {
        return view('user.identity.index', [
            'currentIdentity' => $request->user()->currentIdentity()->with('identityType')->first(),
            'history' => $request->user()->identityDocuments()->with('identityType')->latest()->paginate(10),
            'identityTypes' => IdentityType::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'dojahEnabled' => $dojah->enabled(),
            'dojahVerification' => $dojah->latestForUser($request->user()),
        ]);
    }

    public function store(Request $request, IdentityDocumentService $service): RedirectResponse
    {
        $data = $request->validate([
            'identity_type_id' => ['required', Rule::exists('identity_types', 'id')->where('is_active', true)],
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:10240'],
        ]);
        $type = IdentityType::query()->findOrFail($data['identity_type_id']);
        $service->replaceUserIdentity($request->user(), $type, $request->file('document'));
        return back()->with('success', 'Your government ID was stored securely.');
    }

    public function download(Request $request, UserIdentityDocument $document): StreamedResponse
    {
        abort_unless($document->user_id === $request->user()->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
        IdentityAuditHistory::query()->create([
            'actor_id' => $request->user()->id,
            'document_type' => 'user',
            'document_id' => $document->id,
            'action' => 'downloaded_by_owner',
        ]);
        return Storage::disk($document->disk)->download($document->path, $document->original_name, ['Cache-Control' => 'no-store, private']);
    }
}
