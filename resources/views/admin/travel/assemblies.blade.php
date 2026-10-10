@extends('admin.layouts.app')
@section('title','Trip recovery queue')
@push('head')
<style>
.resavar-admin-tools{width:min(1120px,100%);margin:0 auto;padding:clamp(16px,2.5vw,28px);color:#052058}
.resavar-admin-tools .az-user-panel{background:#FFFFFF;color:#052058;border:1px solid #D8E2EE;border-radius:14px;overflow:hidden}
.resavar-admin-tools h1,.resavar-admin-tools h2,.resavar-admin-tools p,.resavar-admin-tools label{color:#052058}
.resavar-admin-tools :is(input,select,textarea){display:block;width:100%;max-width:100%;min-height:42px;border:1px solid #9DAEC9;border-radius:8px;background:#FFFFFF;color:#052058;padding:9px 11px;box-sizing:border-box}
.resavar-admin-tools :is(button[type=submit],.resavar-admin-button){display:inline-flex;justify-content:center;align-items:center;gap:8px;min-height:42px;padding:9px 15px;background:#052058;color:#FFFFFF;border:1px solid #052058;border-radius:9px;font-weight:700;cursor:pointer}
.resavar-admin-tools :is(button[type=submit],.resavar-admin-button):hover{background:#FFFFFF;color:#052058}
.resavar-admin-tools :is(button,input,select,textarea,a):focus-visible{outline:3px solid #365A91;outline-offset:2px}
.resavar-admin-tools table{width:100%;border-collapse:collapse;display:block;overflow-x:auto}
.resavar-admin-tools :is(th,td){padding:10px 12px;border-bottom:1px solid #D8E2EE;text-align:left;white-space:normal}
@media(max-width:650px){.resavar-admin-tools .az-form-grid{display:grid;grid-template-columns:1fr}.resavar-admin-tools :is(button[type=submit],.resavar-admin-button){width:100%}}
@media print{.resavar-admin-tools{padding:0}.resavar-admin-tools button{display:none!important}}
</style>
@endpush
@section('content')
<div class="resavar-admin-tools">
    <h1>Trip assembly reconciliation</h1>
    <p>Read-only, auditable reconciliation. Never retry a supplier charge here: use the independent product's authorized service.</p>
    @forelse($assemblies as $assembly)
        <section style="margin:16px 0;padding:16px;border:1px solid #bbb">
            <h2>{{ $assembly->itinerary?->name ?? 'Trip' }} · {{ $assembly->status }}</h2>
            <p>Assembly {{ $assembly->id }} · Revision {{ $assembly->revision }}</p>
            @foreach($assembly->currency_totals as $currency=>$amounts)
                <p>{{ $currency }} · quoted {{ \App\Support\MinorMoney::display($amounts['quoted_minor'], $currency) }} ·
                    verified paid {{ \App\Support\MinorMoney::display($amounts['collected_minor'], $currency) }} ·
                    verified refunded {{ \App\Support\MinorMoney::display($amounts['refunded_minor'], $currency) }}</p>
            @endforeach
            <form method="POST" action="{{ route('azari.admin.travel.assemblies.prepare',$assembly) }}">
                @csrf <button type="submit">Prepare item recovery checkpoints</button>
            </form>
            @foreach($assembly->steps as $step)
                <div style="padding:10px;border-top:1px solid #ddd">
                    <strong>{{ ucfirst($step->item_type) }}</strong> #{{ $step->item_id }} · {{ $step->status }}
                    @if($step->status!=='preexisting_stay')
                        <form method="POST" action="{{ route('azari.admin.travel.steps.reconcile',$step) }}">
                            @csrf <button type="submit">Verify supplier result (no charge)</button>
                        </form>
                        @if(in_array($step->status,['verified_provider','needs_provider','reconciliation_required'],true))
                            <form method="POST" action="{{ route('azari.admin.travel.steps.compensate',$step) }}">
                                @csrf <button type="submit">Queue product-specific compensation</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach
            <form method="POST" action="{{ route('azari.admin.travel.assemblies.review',$assembly) }}">
                @csrf <button type="submit">Reconcile current item snapshot (no charges)</button>
            </form>
        </section>
    @empty <p>No outstanding trip assembly reviews.</p>
    @endforelse
    {{ $assemblies->links() }}
</div>
@endsection
