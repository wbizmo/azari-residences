<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingConversation;
use App\Models\Property;
use App\Models\PropertyOperationsTask;
use App\Models\PropertyStaffMembership;
use App\Models\Review;
use App\Models\User;
use App\Notifications\PremiumMailNotification;
use App\Services\Owners\PropertyAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerPhaseTwoController extends Controller
{
    public function staff(Request $request, Property $property, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'operations.manage');

        $members = PropertyStaffMembership::query()
            ->where('property_id', $property->id)
            ->whereNull('revoked_at')
            ->orderBy('role')
            ->get();

        $users = User::query()->whereIn('id', $members->pluck('user_id'))->get()->keyBy('id');

        return view('user.owner.staff', compact('property', 'members', 'users'));
    }

    public function invite(Request $request, Property $property, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'operations.manage');

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'role' => ['required', Rule::in(['manager', 'front_desk', 'inventory_editor', 'finance_viewer', 'support_agent'])],
        ]);

        $invitee = User::query()->where('email', mb_strtolower($data['email']))->first();
        if (! $invitee || ! $invitee->email_verified_at) {
            return back()->withErrors(['email' => 'The collaborator must have a verified Resavar account before invitation.']);
        }

        abort_if((int) $invitee->id === (int) $property->owner_id, 422, 'The property owner already has full access.');

        $token = Str::random(64);
        $hash = hash('sha256', $token);

        DB::transaction(function () use ($property, $request, $invitee, $data, $hash): void {
            DB::table('property_staff_invitations')
                ->where('property_id', $property->id)
                ->where('email', $invitee->email)
                ->whereNull('accepted_at')
                ->delete();

            DB::table('property_staff_invitations')->insert([
                'property_id' => $property->id,
                'email' => $invitee->email,
                'role' => $data['role'],
                'capabilities' => null,
                'token_hash' => $hash,
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addHours(48),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Notification::route('mail', $invitee->email)->notify(new PremiumMailNotification(
            'property-staff-invite',
            'You were invited to help manage '.$property->name,
            ['A property owner invited your verified Resavar account as '.str_replace('_', ' ', $data['role']).'.', 'The invitation expires in 48 hours.'],
            'Review invitation',
            route('user.owner.staff.accept', ['token' => $token]),
            ['property_id' => $property->id]
        ));

        AuditLog::record('property_staff.invited', $property, [], ['invitee_user_id' => $invitee->id, 'role' => $data['role']]);

        return back()->with('success', 'Invitation sent.');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $hash = hash('sha256', $token);

        DB::transaction(function () use ($hash, $request): void {
            // Serialize redemption: two requests must never accept one invitation twice.
            $invite = DB::table('property_staff_invitations')
                ->where('token_hash', $hash)
                ->lockForUpdate()
                ->first();

            abort_unless(
                $invite && ! $invite->accepted_at
                    && now()->lessThan(\Carbon\CarbonImmutable::parse($invite->expires_at)),
                404
            );

            abort_unless(
                hash_equals(mb_strtolower((string) $invite->email), mb_strtolower((string) $request->user()->email)),
                403
            );

            // Re-check account verification and the existence of the property
            // before granting any access, even if its state changed after the invite.
            abort_unless($request->user()->hasVerifiedEmail(), 403);
            $property = Property::query()->whereKey($invite->property_id)
                ->whereNotNull('owner_id')->firstOrFail();
            $inviter = User::query()->find($invite->invited_by);
            abort_unless(
                $inviter && app(PropertyAccessService::class)->can($inviter, $property, 'operations.manage'),
                403,
                'The invitation issuer no longer has property management access.'
            );

            PropertyStaffMembership::query()->updateOrCreate(
                ['property_id' => $invite->property_id, 'user_id' => $request->user()->id],
                [
                    'role' => $invite->role,
                    'capabilities' => json_decode($invite->capabilities ?: '[]', true),
                    'invited_by' => $invite->invited_by,
                    'accepted_at' => now(),
                    'revoked_at' => null,
                ]
            );

            $affected = DB::table('property_staff_invitations')
                ->where('id', $invite->id)
                ->whereNull('accepted_at')
                ->update(['accepted_at' => now(), 'updated_at' => now()]);

            abort_unless($affected === 1, 409, 'Invitation already redeemed.');
        }, 3);

        return redirect()->route('user.owner.dashboard')->with('success', 'Property staff access accepted.');
    }

    public function revoke(Request $request, Property $property, PropertyStaffMembership $membership, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'operations.manage');
        abort_unless((int) $membership->property_id === (int) $property->id, 404);
        abort_if((int) $membership->user_id === (int) $property->owner_id, 422);

        DB::transaction(function () use ($property, $membership): void {
            $membership->update(['revoked_at' => now()]);
            $staffEmail = User::query()->whereKey($membership->user_id)->value('email');
            if ($staffEmail) {
                DB::table('property_staff_invitations')
                    ->where('property_id', $property->getKey())
                    ->where('email', $staffEmail)
                    ->whereNull('accepted_at')
                    ->delete();
            }

            AuditLog::record('property_staff.revoked', $property, [], [
                'user_id' => $membership->user_id,
                'pending_invitations_revoked' => true,
            ]);
        }, 3);

        return back()->with('success', 'Collaborator access revoked.');
    }

    public function operations(Request $request, Property $property, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'operations.manage');

        // A single property-scoped SQL aggregate avoids scanning all tasks
        // in PHP and includes tasks beyond the current paginated page.
        $now = now();
        $metrics = PropertyOperationsTask::query()
            ->where('property_id', $property->id)
            ->selectRaw("COUNT(*) AS total")
            ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') THEN 1 ELSE 0 END) AS active")
            ->selectRaw("SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked")
            ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') AND assigned_to IS NULL THEN 1 ELSE 0 END) AS unassigned")
            ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) AS overdue", [$now])
            ->selectRaw("SUM(CASE WHEN status IN ('open','in_progress','blocked') AND priority IN ('high','urgent') THEN 1 ELSE 0 END) AS high_priority")
            ->first();

        $tasks = PropertyOperationsTask::query()
            ->where('property_id', $property->id)
            ->orderByRaw("CASE WHEN status IN ('open','in_progress') THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->paginate(25);

        $bookings = Booking::query()
            ->where('property_id', $property->id)
            ->whereIn('status', ['approved', 'paid', 'confirmed', 'checked_in'])
            ->whereDate('check_out', '>=', today())
            ->orderBy('check_in')
            ->limit(100)
            ->get();

        $staffMemberships = PropertyStaffMembership::query()
            ->where('property_id', $property->id)
            ->whereNull('revoked_at')
            ->whereNotNull('accepted_at')
            ->get();

        $staffUsers = User::query()
            ->whereIn('id', $staffMemberships->pluck('user_id')->push($property->owner_id)->filter()->unique())
            ->orderBy('name')
            ->get();

        return view('user.owner.operations', compact('property', 'tasks', 'bookings', 'staffUsers', 'metrics'));
    }

    public function createTask(Request $request, Property $property, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'operations.manage');

        $data = $request->validate([
            'booking_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(['arrival', 'housekeeping', 'inspection', 'maintenance', 'handover'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (! empty($data['booking_id'])) {
            abort_unless(Booking::query()->whereKey($data['booking_id'])->where('property_id', $property->id)->exists(), 422);
        }

        if (! empty($data['assigned_to'])) {
            $assignable = (int) $data['assigned_to'] === (int) $property->owner_id
                || PropertyStaffMembership::query()
                    ->where('property_id', $property->id)
                    ->where('user_id', $data['assigned_to'])
                    ->whereNull('revoked_at')
                    ->whereNotNull('accepted_at')
                    ->exists();
            abort_unless($assignable, 422);
        }

        $task = PropertyOperationsTask::query()->create([
            ...$data,
            'property_id' => $property->id,
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        AuditLog::record('property_operations.task_created', $task);

        return back()->with('success', 'Operations task created.');
    }

    public function updateTask(Request $request, Property $property, PropertyOperationsTask $task, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'operations.manage');
        abort_unless((int) $task->property_id === (int) $property->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'blocked', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        if (! empty($data['assigned_to'])) {
            $assignable = (int) $data['assigned_to'] === (int) $property->owner_id
                || PropertyStaffMembership::query()
                    ->where('property_id', $property->id)
                    ->where('user_id', $data['assigned_to'])
                    ->whereNull('revoked_at')
                    ->whereNotNull('accepted_at')
                    ->exists();
            abort_unless($assignable, 422);
        }

        $before = $task->only(['status', 'notes', 'assigned_to']);
        $expected = $data['version'];
        unset($data['version']);

        $changed = DB::transaction(function () use ($property, $task, $data, $expected, $before): bool {
            // SQL compare-and-swap avoids a stale form overwriting an edit made
            // by another property collaborator. Only a successful update is audited.
            $updated = PropertyOperationsTask::query()
                ->whereKey($task->getKey())
                ->where('property_id', $property->getKey())
                ->where('version', $expected)
                ->update([
                    ...$data,
                    'completed_at' => $data['status'] === 'completed' ? now() : null,
                    'version' => $expected + 1,
                    'updated_at' => now(),
                ]);

            if ($updated !== 1) {
                return false;
            }

            $task->refresh();
            AuditLog::record('property_operations.task_updated', $task, $before, $task->only(['status', 'notes', 'assigned_to']));

            return true;
        }, 3);

        if (! $changed) {
            return back()->withErrors(['status' => 'This task was changed by another team member. Reload the operations board before updating.']);
        }

        return back()->with('success', 'Task updated.');
    }


    public function reviews(Request $request, Property $property, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'messages.manage');

        $reviews = Review::query()
            ->where('property_id', $property->id)
            ->where('status', 'approved')
            ->latest()
            ->paginate(20);

        return view('user.owner.reviews', compact('property', 'reviews'));
    }

    public function replyReview(Request $request, Property $property, Review $review, PropertyAccessService $access): RedirectResponse
    {
        $access->assert($request->user(), $property, 'messages.manage');
        abort_unless((int) $review->property_id === (int) $property->id && $review->status === 'approved', 404);

        $data = $request->validate(['reply' => ['required', 'string', 'min:2', 'max:2000']]);
        $before = $review->owner_reply;
        $review->update([
            'owner_reply' => $data['reply'],
            'owner_replied_at' => now(),
            'owner_reply_status' => 'pending',
        ]);

        AuditLog::record('review.owner_replied', $review, ['owner_reply' => $before], ['owner_reply' => $review->owner_reply]);

        return back()->with('success', 'Property response submitted for moderation.');
    }

    public function conversations(Request $request, Property $property, PropertyAccessService $access): View
    {
        $access->assert($request->user(), $property, 'messages.manage');

        $conversations = BookingConversation::query()
            ->where('property_id', $property->id)
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount(['messages as guest_unread_count' => fn ($q) => $q
                ->where('sender_type', 'guest')->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->paginate(25);

        return view('user.owner.conversations', compact('property', 'conversations'));
    }
}
