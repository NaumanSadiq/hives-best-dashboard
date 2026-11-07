@php($history=\Illuminate\Support\Facades\DB::table('batches')->find($historyId))
<h3 class="h5">{{ $history->reference }}</h3>
<p class="muted">Times in Asia/Karachi. Cost revisions restate internal reports; issued customer invoices retain their snapshots.</p>
@php($events=\Illuminate\Support\Facades\DB::table('batch_changes as c')->leftJoin('users as u','u.id','=','c.user_id')->where('c.batch_id',$historyId)->select('c.*','u.name as actor')->orderByDesc('c.id')->get())
<div class="audit-timeline">
@foreach($events as $change)
<details class="audit-event" open><summary>{{ \Carbon\Carbon::parse($change->created_at,'Asia/Karachi')->format('d M Y, H:i:s') }} · {{ $change->actor ?: 'Administrator not recorded' }} · {{ Str::headline($change->kind) }}</summary>
<p>{{ $change->reason }}</p><dl class="preview-grid">
@foreach(json_decode($change->after,true) as $field=>$value)
@php($old=json_decode($change->before,true)[$field]??null)
@if(in_array($field,['reference','stock_key','received_on','original_quantity','physical','purchase','packaging','branding','other']) && (string)$old!==(string)$value)
<div><dt>{{ Str::headline($field) }}</dt><dd>{{ $old===null?'Not recorded':(in_array($field,['purchase','packaging','branding','other'])?$money($old):$old) }} → {{ in_array($field,['purchase','packaging','branding','other'])?$money($value):$value }}</dd></div>
@endif @endforeach
@if($change->effects) @php($effect=json_decode($change->effects,true))
<div><dt>Receipt / physical movements</dt><dd>{{ number_format($effect['received_movement']) }} / {{ number_format($effect['physical_movement']) }} base units</dd></div>
<div><dt>Investment</dt><dd>{{ $money($effect['investment_before']) }} → {{ $money($effect['investment_after']) }}</dd></div>
<div><dt>Delivered gross profit change at correction</dt><dd>{{ $money($effect['delivered_profit_change']) }}</dd></div>
@endif</dl>
@if($change->effects)<details><summary>Allocation cost revisions</summary><div class="allocation-revisions">@foreach($effect['allocations'] as $a) @if($a['before']!==$a['after'])<p>{{ $a['order'] }} · {{ number_format($a['quantity']) }} base units{{ $a['returned']?' · returned':'' }}: {{ $money($a['before']) }} → {{ $money($a['after']) }}</p>@endif @endforeach</div></details>@endif
</details>
@endforeach
@foreach(\Illuminate\Support\Facades\DB::table('stock_adjustments')->where('batch_id',$historyId)->whereNull('batch_change_id')->orderByDesc('id')->get() as $movement)
<article class="audit-event"><strong>{{ $movement->created_at }} · Earlier stock adjustment</strong><p>{{ number_format($movement->quantity) }} base units · {{ $movement->reason }}</p><small>Administrator and previous count were not recorded.</small></article>
@endforeach
@unless($events->contains('kind','created'))<article class="audit-event"><strong>Earlier batch receipt · {{ $history->received_on }}</strong><p>Original creation actor, reason and full receipt snapshot were not recorded. Current values may include later corrections.</p></article>@endunless
</div>
<button class="btn btn-sage mt-3" wire:click="batchForm({{ $historyId }})">Edit Batch</button>
