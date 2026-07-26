
        @extends('admin.layout')
        @section('title', 'Reports')
        @section('content')
    <div class="space-y-6">
 <div><h1 class="text-3xl font-semibold">Reports and exports</h1><p class="text-sm opacity-70">Azari operational timezone: {{ config('azari.timezone','Africa/Lagos') }}</p></div>
 <form method="GET" class="grid gap-3 rounded-2xl border p-4 md:grid-cols-4">
  <select name="type">@foreach($types as $item)<option value="{{ $item }}" @selected($type===$item)>{{ str($item)->replace('_',' ')->title() }}</option>@endforeach</select>
  <input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}">
  <input name="status" value="{{ request('status') }}" placeholder="Status"><input name="provider" value="{{ request('provider') }}" placeholder="Provider">
  <input name="currency" value="{{ request('currency') }}" placeholder="Currency"><input name="booking_id" value="{{ request('booking_id') }}" placeholder="Booking ID">
  <button class="rounded-xl bg-emerald-950 px-4 py-3 text-white">Apply filters</button>
 </form>
 <div class="flex flex-wrap gap-2">
 @foreach(['csv','excel','pdf'] as $format)<a class="rounded-xl border px-4 py-2" href="{{ route('azari.admin.reports.export',array_merge(request()->query(),['format'=>$format])) }}">{{ strtoupper($format) }}</a>@endforeach
 <a class="rounded-xl border px-4 py-2" target="_blank" href="{{ route('azari.admin.reports.print',request()->query()) }}">Print</a>
 </div>
 <div class="overflow-x-auto rounded-2xl border"><table class="min-w-full text-sm">
 @if($rows->count())<thead><tr>@foreach(array_keys($rows->first()->getAttributes()) as $key)<th class="p-3 text-left">{{ str($key)->replace('_',' ')->title() }}</th>@endforeach</tr></thead>
 <tbody>@foreach($rows as $row)<tr class="border-t">@foreach($row->getAttributes() as $value)<td class="p-3">{{ is_scalar($value)||is_null($value)?$value:json_encode($value) }}</td>@endforeach</tr>@endforeach</tbody>
 @else<tbody><tr><td class="p-8 text-center">No report records match the selected filters.</td></tr></tbody>@endif
 </table></div>{{ $rows->links() }}
</div>
        
@endsection

    