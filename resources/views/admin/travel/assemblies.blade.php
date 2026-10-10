@extends('admin.layouts.app')
@section('title','Trip recovery queue')
@section('content')
<div style="padding:22px">
    <h1>Trip assembly reconciliation</h1>
    <p>Read-only, auditable reconciliation. Never retry a supplier charge here: use the independent product's authorized service.</p>
    @forelse($assemblies as $assembly)
        <section style="margin:16px 0;padding:16px;border:1px solid #bbb">
            <h2>{{ $assembly->itinerary?->name ?? 'Trip' }} · {{ $assembly->status }}</h2>
            <p>Assembly {{ $assembly->id }} · Revision {{ $assembly->revision }}</p>
            @foreach($assembly->currency_totals as $currency=>$amounts)
                <p>{{ $currency }} · quoted {{ number_format($amounts['quoted_minor']/100,2) }} ·
                    verified paid {{ number_format($amounts['collected_minor']/100,2) }} ·
                    verified refunded {{ number_format($amounts['refunded_minor']/100,2) }}</p>
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
