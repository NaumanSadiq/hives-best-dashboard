<section class="panel"><div class="panel-heading"><h2>Batch investment & realised returns</h2></div><p class="muted">Delivered revenue and profit use the selected delivery dates. Investment and remaining value show each batch’s current corrected basis across all dates.</p>
<div class="batch-cards">
@forelse($batches as $b)
@php($investment=$b->purchase+$b->packaging+$b->branding+$b->other)
@php($loss=\App\Support\Money::allocate($investment,max(0,$b->original_quantity-$b->physical-($b->dispatched_quantity??0)),$b->original_quantity))
@if((int)$b->physical===0) @php($loss=max(0,$investment-($b->consumed_cost??0))) @endif
@php($remaining=max(0,$investment-($b->consumed_cost??0)-$loss))
@php($unit=$products->firstWhere('stock_key',$b->stock_key)?->kind==='honey'?'g':'units')
<article class="batch-card" wire:key="batch-return-{{ $b->id }}"><header><div><h3>{{ $b->reference }}</h3><p class="muted">{{ Str::headline($b->stock_key) }} · Received {{ $b->received_on }}</p></div><button class="btn btn-light btn-sm" wire:click="viewBatchHistory({{ $b->id }})">View History</button></header>
<dl class="batch-figures"><div><dt>Total investment</dt><dd>{{ $money($investment) }}</dd></div><div><dt>Remaining stock value · current</dt><dd>{{ $money($remaining) }}</dd></div><div><dt>Delivered merchandise revenue</dt><dd>{{ $money($b->revenue) }}</dd></div><div><dt>Realised merchandise profit</dt><dd>{{ $money(($b->revenue??0)-($b->realised_cost??0)) }}</dd></div></dl>
<details><summary>View Breakdown</summary><dl class="preview-grid mt-3">
@foreach(['purchase'=>'Purchase','packaging'=>'Packaging','branding'=>'Branding','other'=>'Other direct costs'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $money($b->$field) }}</dd></div>@endforeach
@foreach(['original_quantity'=>'Received quantity','physical'=>'On hand · current','reserved'=>'Reserved · current'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ number_format($b->$field) }} {{ $unit }}</dd></div>@endforeach
<div><dt>Available · current</dt><dd>{{ number_format($b->physical-$b->reserved) }} {{ $unit }}</dd></div><div><dt>Dispatched, net returns · all dates</dt><dd>{{ number_format($b->dispatched_quantity??0) }} {{ $unit }}</dd></div><div><dt>Delivered quantity · selected dates</dt><dd>{{ number_format($b->sold??0) }} {{ $unit }}</dd></div><div><dt>Realised stock costs · selected dates</dt><dd>{{ $money($b->realised_cost) }}</dd></div><div><dt>Loss costs · current</dt><dd>{{ $money($loss) }}</dd></div></dl><p class="muted">Investment = purchase + packaging + branding + other costs. Merchandise profit excludes delivery and courier charges, which appear in business totals. Internal costs include audited corrections.</p><button class="btn btn-sage btn-sm" wire:click="batchForm({{ $b->id }})">Edit Batch</button></details></article>
@empty<p class="muted">Receive a batch to see its investment and returns.</p>@endforelse
</div>{{ $batches->links() }}</section>
