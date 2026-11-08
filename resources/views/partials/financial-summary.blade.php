<section class="financial-grid" aria-label="Financial summary">
@foreach([
 'sales'=>['peach','heart','Sales from delivered orders','revenue','Product sales after discounts plus delivery charged to customers. Includes delivered orders whose payment may still be pending.','Delivery date · selected range'],
 'cash'=>['sage','wallet2','Cash received','cash','Recorded customer payments minus recorded refunds. Can include advance payments for pending orders.','Payment / refund date · selected range'],
 'pending'=>['honey','clock','Payments still due','pending','What customers still owe on active orders. Cancelled orders are excluded.','Current balance · all dates'],
 'investment'=>['sage','box-seam','Stock investment','investment','The full cost of received batches, including stock still on hand. This is not just the cost of stock sold.','Batch received date · selected range'],
 'profit'=>['blue','graph-up-arrow','Gross profit','profit','Based on delivered sales, including amounts customers still owe.','Delivery date · selected range'],
] as $kind=>[$color,$icon,$title,$value,$description,$basis])
<article class="financial-card {{ $color }}" data-metric="{{ $kind }}"><header><span class="metric-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><h2>{{ $title }}</h2></header>
<strong class="metric-amount {{ $summary[$value]<0?'text-danger':'' }}">{{ $money($summary[$value]) }}</strong>@if($kind==='profit' && $summary['profit']<0)<span class="loss-label">Loss — costs exceed delivered sales</span>@endif
<p>{{ $description }}</p>
<dl class="metric-calculation">
@if($kind==='sales')<div><dt>Products after discounts</dt><dd>{{ $money($summary['merchandise']) }}</dd></div><div><dt>+ Delivery charged</dt><dd>{{ $money($summary['delivery']) }}</dd></div>
@elseif($kind==='cash')<div><dt>Customer payments</dt><dd>{{ $money($summary['received_cash']) }}</dd></div><div><dt>− Refunds recorded</dt><dd>{{ $money($summary['refunded_cash']) }}</dd></div>
@elseif($kind==='pending')<div><dt>Active order totals</dt><dd>{{ $money($summary['active_total']) }}</dd></div><div><dt>− Payments applied, capped per order</dt><dd>{{ $money($summary['active_applied']) }}</dd></div>
@elseif($kind==='investment')@foreach(['purchase'=>'Purchase','packaging'=>'+ Packaging','branding'=>'+ Branding','other'=>'+ Other direct costs'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $money($summary['investment_'.$field]) }}</dd></div>@endforeach
@else<div><dt>Delivered sales revenue</dt><dd>{{ $money($summary['revenue']) }}</dd></div><div><dt>− Product cost, including allocated packaging and branding</dt><dd>{{ $money($summary['cost']) }}</dd></div><div><dt>− Actual courier costs</dt><dd>{{ $money($summary['courier']) }}</dd></div>@endif
<div class="calculation-result"><dt>= {{ $title }}</dt><dd>{{ $money($summary[$value]) }}</dd></div></dl>
@if($kind==='profit')<p class="metric-note">Gross profit differs from cash received: some delivered orders are unpaid, and some payments are advances. Audited batch cost corrections are included.</p>@endif
@if($kind==='cash' && $summary['cancelled_cash']>0)<p class="metric-note cash-attention">Includes {{ $money($summary['cancelled_cash']) }} net payments on cancelled orders. Cancelling an order does not record a refund.</p>@endif
<footer><span>{{ $basis }}</span><button class="btn btn-light" wire:click="showBreakdown('{{ $kind }}')" aria-label="View Breakdown: {{ $title }}">View Breakdown <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button></footer></article>
@endforeach
</section>
<section class="panel pack-summary" data-metric="packs"><div class="panel-heading"><div><h2>Delivered sales and honey packs</h2><p class="muted mb-0">Delivery date · selected range. Counts come from saved order-line quantities and sizes.</p></div><button class="btn btn-light" wire:click="showBreakdown('packs')" aria-label="View Breakdown: Delivered sales and honey packs">View Breakdown</button></div>
<dl class="pack-totals"><div><dt>Delivered orders</dt><dd>{{ number_format($packSummary['orders']) }}</dd></div><div><dt>1 kg honey packs</dt><dd>{{ number_format($packSummary['kg_packs']) }}</dd></div><div><dt>500 g honey packs</dt><dd>{{ number_format($packSummary['half_packs']) }}</dd></div><div><dt>Total honey weight</dt><dd>{{ rtrim(rtrim(number_format($packSummary['grams']/1000,3,'.',','),'0'),'.') }} kg</dd></div></dl>
<p class="weight-calculation">{{ number_format($packSummary['kg_packs']) }} × 1 kg + {{ number_format($packSummary['half_packs']) }} × 0.5 kg @foreach($packSummary['other'] as $size) + {{ number_format($size->packs) }} × {{ number_format($size->pack_quantity) }} g @endforeach = {{ number_format($packSummary['grams']) }} g honey ({{ number_format($packSummary['honey_packs']) }} packs).</p>
@if($packSummary['other']->isNotEmpty())<p>Other honey sizes: @foreach($packSummary['other'] as $size){{ number_format($size->packs) }} × {{ number_format($size->pack_quantity) }} g{{ $loop->last?'':'; ' }}@endforeach.</p>@endif
@if($packSummary['snacks'])<p>Snacks, shown separately: {{ number_format($packSummary['snacks']) }} packs.</p>@endif
<p class="muted mb-0">Pending, dispatched and cancelled orders are excluded. Returns are supported only after full-order cancellation, so those orders and their packs are excluded entirely.</p></section>
