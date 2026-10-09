<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index()
    {
        return view('admin.vouchers.index', [
            'vouchers' => Voucher::withCount('redemptions')->latest()->paginate(10),
            'properties' => Property::orderBy('name')->get(),
            // A server-provided code also makes the read-only field usable without JS.
            'suggestedCode' => $this->generateCode(),
        ]);
    }

    public function store(Request $request)
    {
        // Treat any submitted code as untrusted; the unique DB index remains
        // the final protection against concurrent duplicate submissions.
        if (! $request->filled('code')) {
            $request->merge(['code' => $this->generateCode()]);
        }

        $data = $this->validated($request);
        $properties = $data['property_ids'] ?? [];
        unset($data['property_ids']);

        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = $request->user()
            && User::whereKey($request->user()->getKey())->exists()
            ? $request->user()->getKey()
            : null;

        $voucher = Voucher::create($data);
        $voucher->properties()->sync($properties);
        AuditLog::record('voucher.created', $voucher);

        return back()->with('success', 'Voucher created.');
    }

    public function update(Request $request, Voucher $voucher)
    {
        $data = $this->validated($request, $voucher);
        $properties = $data['property_ids'] ?? [];
        unset($data['property_ids']);

        $data['code'] = strtoupper($data['code']);
        $voucher->update($data);
        $voucher->properties()->sync($properties);
        AuditLog::record('voucher.updated', $voucher);

        return back()->with('success', 'Voucher updated.');
    }

    private function generateCode(): string
    {
        // 72 bits of CSPRNG entropy. The preview is not a reservation: creation
        // still checks uniqueness and the database has a unique index.
        do {
            $code = 'RSV-'.strtoupper(bin2hex(random_bytes(9)));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    private function validated(Request $request, ?Voucher $voucher = null): array
    {
        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:64', Rule::unique('vouchers', 'code')->ignore($voucher)],
            'name' => 'required|string|max:160',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'maximum_discount' => 'nullable|numeric|min:0.01',
            'minimum_booking_value' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'total_usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'required|integer|min:1',
            'is_active' => 'required|boolean',
            'property_ids' => 'nullable|array',
            'property_ids.*' => 'integer|exists:properties,id',
        ]);
    }
}
