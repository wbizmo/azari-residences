<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog,SupportTicket,SupportTicketMessage,User};
use App\Notifications\PremiumMailNotification;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
class SupportTicketController extends Controller {
 public function index(Request $r):View{$q=SupportTicket::with(['user','booking','assignee'])->orderByRaw("CASE WHEN status NOT IN ('resolved','closed') AND sla_due_at IS NOT NULL AND sla_due_at < ? THEN 0 WHEN severity = 'safety' THEN 1 WHEN severity IN ('unable_to_check_in','payment_taken_no_confirmation','property_unavailable') THEN 2 ELSE 3 END",[now()])->orderBy('sla_due_at')->latest();foreach(['status','category','priority','severity','assigned_to'] as $f)if($r->filled($f))$q->where($f,$r->input($f));if($r->filled('search'))$q->where(fn($x)=>$x->where('reference','like','%'.$r->search.'%')->orWhere('subject','like','%'.$r->search.'%'));$now = now();
        $metrics = SupportTicket::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) as unresolved")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('resolved','closed') AND sla_due_at < ? THEN 1 ELSE 0 END) as breached", [$now])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('resolved','closed') AND severity IN ('safety','unable_to_check_in','payment_taken_no_confirmation','property_unavailable') THEN 1 ELSE 0 END) as critical")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('resolved','closed') AND assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('resolved','closed') AND first_responded_at IS NULL AND response_due_at < ? THEN 1 ELSE 0 END) as response_overdue", [$now])
            ->first();
        return view('admin.support.index',[
            'tickets' => $q->paginate(10)->withQueryString(),
            'metrics' => $metrics,
        ]);}
 public function show(SupportTicket $ticket):View{$ticket->load(['booking.property','user','assignee']);$messages=$ticket->messages()->with('user')->oldest()->paginate(15,['*'],'messages_page')->withQueryString();$staff=User::where('is_active',true)->where(fn($q)=>$q->where('is_admin',true)->orWhereNotNull('staff_role'))->orderBy('name')->get();return view('admin.support.show',compact('ticket','staff','messages'));}
 public function update(Request $r,SupportTicket $ticket):RedirectResponse{$d=$r->validate(['status'=>'required|in:open,awaiting_staff,awaiting_guest,in_progress,escalated,resolved,closed','priority'=>'required|in:low,normal,high,urgent','assigned_to'=>['nullable','integer',Rule::exists('users','id')->where('is_active',true)->where(fn($query)=>$query->where('is_admin',true)->orWhereNotNull('staff_role'))],'resolution_note'=>'nullable|string|max:5000']);$old=$ticket->only(['status','priority','assigned_to','resolution_note']);$ticket->fill($d);if(in_array($old['status'],['resolved','closed'],true)&&!in_array($d['status'],['resolved','closed'],true))$ticket->sla_alerted_at=null;if($d['status']==='escalated'&&!$ticket->escalated_at)$ticket->escalated_at=now();if($d['status']==='resolved')$ticket->resolved_at=now();if($d['status']==='closed')$ticket->closed_at=now();$ticket->save();AuditLog::record('support_ticket.updated',$ticket,$old,$ticket->only(array_keys($old)));return back()->with('success','Ticket updated.');}
 public function reply(Request $r,SupportTicket $ticket):RedirectResponse{$d=$r->validate(['body'=>'required|string|max:10000','internal'=>'nullable|boolean','attachment'=>'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx']);$internal=$r->boolean('internal');$file=$r->file('attachment');$message=$ticket->messages()->create(['user_id'=>$r->user()->id,'body'=>$d['body'],'internal'=>$internal,'attachment_path'=>$file?->store('support-attachments','private'),'attachment_name'=>$file?->getClientOriginalName()]);if(!$internal){$ticket->update(['status'=>'awaiting_guest','first_responded_at'=>$ticket->first_responded_at?:now()]);$ticket->user->notify(new PremiumMailNotification('support-ticket-reply','A reply is waiting on your support request',["Our team replied to {$ticket->reference}.",'Sign in to review and respond.'],'View reply',route('user.support.show',$ticket),['support_ticket_id'=>$ticket->id]));}AuditLog::record($internal?'support_ticket.internal_note':'support_ticket.staff_replied',$ticket,[],[],['message_id'=>$message->id]);return back()->with('success',$internal?'Internal note saved.':'Reply sent.');}
 public function attachment(SupportTicket $ticket,SupportTicketMessage $message):StreamedResponse{abort_unless($message->support_ticket_id===$ticket->id&&$message->attachment_path,404);abort_unless(Storage::disk('private')->exists($message->attachment_path),404);AuditLog::record('support_ticket.attachment_downloaded',$ticket,[],[],['message_id'=>$message->id]);return Storage::disk('private')->download($message->attachment_path,$message->attachment_name?:'attachment');}
}
