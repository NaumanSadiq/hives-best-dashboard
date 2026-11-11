<section class="panel current-stock-card"><div class="panel-heading"><h2>Current Stock · All Dates</h2><a class="btn btn-light btn-sm" href="{{ route('stock') }}">View Stock <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
<p class="small muted">Current available and reserved balances · all dates</p>
@php($poolBalances=\Illuminate\Support\Facades\DB::table('batches')->select('stock_key')->selectRaw('SUM(physical-reserved) available, SUM(reserved) reserved')->groupBy('stock_key')->get()->keyBy('stock_key'))
<div class="current-stock-pools">@forelse($products->unique('stock_key') as $pool)
@php($balance=$poolBalances->get($pool->stock_key))
<div class="current-stock-pool"><strong>{{ Str::headline($pool->stock_key) }}</strong><span><b>{{ number_format($balance->available??0) }} {{ $pool->kind==='honey'?'g':'units' }}</b> available <span class="muted">· {{ number_format($balance->reserved??0) }} {{ $pool->kind==='honey'?'g':'units' }} reserved</span></span></div>
@empty<p class="small muted mb-0">No stock pools yet. Add a product in Stock to get started.</p>@endforelse</div></section>
