<?php
namespace App\Http\Controllers\UserArea;
use App\Http\Controllers\Controller;
use App\Services\Security\SupportAttachmentGuard;
use App\Models\{AuditLog,SiteSetting,SupportTicket,SupportTicketMessage};
use App\Notifications\PremiumMailNotification;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\{DB,Notification,Storage,URL};
use Throwable;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
class SupportTicketController extends Controller {
 public function index(Request $r):View{$tickets=SupportTicket::with('booking')->where('user_id',$r->user()->id)->latest()->paginate(10)->withQueryString();$bookings=$r->user()->bookings()->latest()->get();$contact=['email'=>SiteSetting::valueFor('customer_dashboard_contact_email',SiteSetting::valueFor('public_contact_email',config('mail.from.address'))),'phone'=>SiteSetting::valueFor('contact_phone'),'whatsapp'=>SiteSetting::valueFor('whatsapp_number'),'hours'=>SiteSetting::valueFor('support_hours'),'response'=>SiteSetting::valueFor('expected_response_time')];return view('user.support.index',compact('tickets','bookings','contact'));}
 public function store(Request $r): RedirectResponse
 {
     $d = $r->validate([
         'booking_id' => 'nullable|exists:bookings,id',
         'category' => 'required|in:booking,payment,receipt_invoice,property,service_request,account,identity_document,technical_issue,other',
         'severity' => 'required|in:unable_to_check_in,payment_taken_no_confirmation,property_unavailable,safety,general',
         'subject' => 'required|string|max:160',
         'description' => 'required|string|max:10000',
         'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
     ]);

     if (! empty($d['booking_id'])
         && ! $r->user()->bookings()->whereKey($d['booking_id'])->exists()) {
         abort(403);
     }

     // Scan before opening a support case. Scanner failures must not leave
     // empty orphan tickets or public/unscanned evidence behind.
     $attachment = app(SupportAttachmentGuard::class)->store($r->file('attachment'));
     $deadline = now()->addMinutes(match ($d['severity']) {
         'safety' => 15,
         'unable_to_check_in' => 30,
         'payment_taken_no_confirmation', 'property_unavailable' => 60,
         default => 1440,
     });

     try {
         $ticket = DB::transaction(function () use ($r, $d, $attachment, $deadline): SupportTicket {
             $ticket = SupportTicket::query()->create([
                 'reference' => SupportTicket::nextReference(),
                 'user_id' => $r->user()->id,
                 'booking_id' => $d['booking_id'] ?? null,
                 'category' => $d['category'],
                 'subject' => $d['subject'],
                 'status' => 'open',
                 'severity' => $d['severity'],
                 'sla_due_at' => $deadline,
                 'response_due_at' => $deadline,
             ]);

             $ticket->messages()->create([
                 'user_id' => $r->user()->id,
                 'body' => $d['description'],
                 ...$attachment,
             ]);
             AuditLog::record('support_ticket.created', $ticket);
             return $ticket;
         }, 3);
     } catch (Throwable $exception) {
         if ($attachment['attachment_path'] !== null) {
             Storage::disk('private')->delete($attachment['attachment_path']);
         }
         throw $exception;
     }

     // Notification outages must not roll back an already committed case.
     try {
         $r->user()->notify(new PremiumMailNotification(
             'support-ticket-created',
             'Support request received',
             ["Your support reference is {$ticket->reference}.", 'Our team will reply in your account.'],
             'View support request',
             route('user.support.show', $ticket),
             ['support_ticket_id' => $ticket->id]
         ));

         $destination = SiteSetting::valueFor('support_destination_email', env('AZARI_SUPPORT_TICKET_EMAIL'));
         if (filter_var($destination, FILTER_VALIDATE_EMAIL)) {
             Notification::route('mail', $destination)->notify(new PremiumMailNotification(
                 'support-ticket-admin-alert',
                 'New support request '.$ticket->reference,
                 [$ticket->subject, 'Open the administrator support queue to review it.'],
                 'Open support queue',
                 route('azari.admin.support.show', $ticket),
                 ['support_ticket_id' => $ticket->id]
             ));
         }
     } catch (Throwable $exception) {
         report($exception);
     }

     return redirect()->route('user.support.show', $ticket)
         ->with('success', 'Support request submitted.');
 }
 public function show(Request $r,SupportTicket $ticket):View{$this->own($r,$ticket);$ticket->load('booking');$messages=$ticket->publicMessages()->with('user')->oldest()->paginate(15,['*'],'messages_page')->withQueryString();return view('user.support.show',compact('ticket','messages'));}
 public function attachment(Request $r,SupportTicket $ticket,SupportTicketMessage $message):StreamedResponse{$this->own($r,$ticket);abort_unless($message->support_ticket_id===$ticket->id&&!$message->internal&&$message->attachment_path,404);abort_unless(Storage::disk('private')->exists($message->attachment_path),404);AuditLog::record('support_ticket.attachment_downloaded',$ticket,[],[],['message_id'=>$message->id]);return Storage::disk('private')->download($message->attachment_path,$message->attachment_name?:'attachment');}
 public function reply(Request $r, SupportTicket $ticket): RedirectResponse
 {
     $this->own($r, $ticket);
     $d = $r->validate([
         'body' => 'required|string|max:10000',
         'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
     ]);
     $attachment = app(SupportAttachmentGuard::class)->store($r->file('attachment'));

     try {
         DB::transaction(function () use ($r, $ticket, $d, $attachment): void {
             $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
             abort_unless((int) $locked->user_id === (int) $r->user()->id, 403);
             abort_if($locked->status === 'closed', 422, 'Reopen the ticket before replying.');

             $message = $locked->messages()->create([
                 'user_id' => $r->user()->id,
                 'body' => $d['body'],
                 ...$attachment,
             ]);
             $update = ['status' => 'awaiting_staff'];
             if ($locked->status === 'resolved') {
                 $update['resolved_at'] = null;
                 $update['sla_alerted_at'] = null;
                 $update['sla_due_at'] = now()->addMinutes(match ($locked->severity) {
                     'safety' => 15,
                     'unable_to_check_in' => 30,
                     'payment_taken_no_confirmation', 'property_unavailable' => 60,
                     default => 1440,
                 });
             }
             $locked->update($update);
             AuditLog::record('support_ticket.user_replied', $locked, [], [], [
                 'message_id' => $message->id,
             ]);
         }, 3);
     } catch (Throwable $exception) {
         if ($attachment['attachment_path'] !== null) {
             Storage::disk('private')->delete($attachment['attachment_path']);
         }
         throw $exception;
     }

     return back()->with('success', 'Reply sent.');
 }
 public function close(Request $r, SupportTicket $ticket): RedirectResponse
 {
     $this->own($r, $ticket);
     DB::transaction(function () use ($r, $ticket): void {
         $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
         abort_unless((int) $locked->user_id === (int) $r->user()->id, 403);
         if ($locked->status === 'closed') {
             return;
         }
         $locked->update(['status' => 'closed', 'closed_at' => now()]);
         AuditLog::record('support_ticket.closed', $locked);
     }, 3);
     return back()->with('success', 'Ticket closed.');
 }

 public function reopen(Request $r, SupportTicket $ticket): RedirectResponse
 {
     $this->own($r, $ticket);
     DB::transaction(function () use ($r, $ticket): void {
         $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
         abort_unless((int) $locked->user_id === (int) $r->user()->id, 403);
         abort_unless(in_array($locked->status, ['closed', 'resolved'], true), 422);

         $deadline = now()->addMinutes(match ($locked->severity) {
             'safety' => 15,
             'unable_to_check_in' => 30,
             'payment_taken_no_confirmation', 'property_unavailable' => 60,
             default => 1440,
         });
         $locked->update([
             'status' => 'open',
             'closed_at' => null,
             'resolved_at' => null,
             'sla_alerted_at' => null,
             'sla_due_at' => $deadline,
             'response_due_at' => $deadline,
         ]);
         AuditLog::record('support_ticket.reopened', $locked);
     }, 3);
     return back()->with('success', 'Ticket reopened.');
 }
 private function own(Request $r,SupportTicket $ticket):void{abort_unless($ticket->user_id===$r->user()->id,403);}
}
