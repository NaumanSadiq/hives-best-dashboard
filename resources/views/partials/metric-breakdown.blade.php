@php($titles=['cash'=>'Cash received','sales'=>'Sales from delivered orders','pending'=>'Payments still due','investment'=>'Stock investment','profit'=>'Gross profit','packs'=>'Delivered sales and honey packs'])
@php($keys=['cash'=>'cash','sales'=>'revenue','pending'=>'pending','investment'=>'investment','profit'=>'profit'])
<div class="breakdown-intro"><h3 class="h5">{{ $titles[$breakdownKind] }}</h3><p>{{ $breakdownKind==='pending'?'Current balance across all dates':(($appliedFrom||$appliedTo)?($appliedFrom?:'Beginning').' → '.($appliedTo?:'No end date'):'All dates') }} · {{ ['cash'=>'payment and refund dates','sales'=>'delivery dates','profit'=>'delivery dates','packs'=>'delivery dates','investment'=>'batch received dates','pending'=>'active orders'][$breakdownKind] }}</p>
@if($breakdownKind!=='packs')<strong class="breakdown-total">Total across all pages: {{ $money($summary[$keys[$breakdownKind]]) }}</strong>@else<strong>{{ number_format($packSummary['orders']) }} delivered orders · {{ number_format($packSummary['honey_packs']) }} honey packs · {{ number_format($packSummary['grams']) }} g honey · {{ number_format($packSummary['snacks']) }} snack packs, across all pages</strong>@endif</div>
@if($breakdownKind==='cash')
<dl class="preview-grid reconciliation-categories"><div><dt>Recorded customer payments</dt><dd>{{ $money($summary['received_cash']) }}</dd></div><div><dt>− Recorded refunds</dt><dd>{{ $money($summary['refunded_cash']) }}</dd></div><div><dt>Net cash on currently delivered orders</dt><dd>{{ $money($summary['delivered_cash']) }}</dd></div><div><dt>Net advances on pending / dispatched orders</dt><dd>{{ $money($summary['advance_cash']) }}</dd></div><div><dt>Net cash on cancelled orders</dt><dd>{{ $money($summary['cancelled_cash']) }}</dd></div><div><dt>= Cash received</dt><dd>{{ $money($summary['cash']) }}</dd></div></dl>
<p class="cash-attention">Cancellation keeps payment history. It does not refund or transfer money. A refund counts only when recorded. Correct entry is for a recording mistake; use the order’s Actual refund action for money actually returned.</p>
@endif
<div class="breakdown-list">
@forelse($breakdownRows as $entry)
<article class="breakdown-entry">
@if($breakdownKind==='cash')
<header><button class="record-link" wire:click="viewOrder({{ $entry->order_id }})">{{ $entry->number }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button><span class="status-badge status-{{ strtolower($entry->status) }}">{{ $entry->status }}</span></header>
<p class="muted">Entry #{{ $entry->id }} · {{ ucfirst($entry->kind) }}{{ $entry->amount===0?' · corrected to zero':'' }} · Payment date {{ $entry->paid_on }}</p>
<dl class="entry-figures"><div><dt>Received</dt><dd>{{ $money($entry->kind==='receipt'?$entry->amount:0) }}</dd></div><div><dt>Refunded</dt><dd>{{ $money($entry->kind==='refund'?$entry->amount:0) }}</dd></div><div><dt>Net</dt><dd>{{ $money($entry->kind==='receipt'?$entry->amount:-$entry->amount) }}</dd></div></dl>
<p class="muted">Reference: {{ $entry->reference?:'Not recorded' }}<br>Entered {{ $entry->created_at }} Asia/Karachi</p>
<button class="btn btn-light btn-sm" wire:click="editPayment({{ $entry->id }})">Correct entry #{{ $entry->id }}</button>
@include('partials.payment-revisions',['receiptId'=>$entry->id])
@elseif($breakdownKind==='investment')
<header><h4 class="h6">{{ $entry->reference }}</h4><span>Received {{ $entry->received_on }}</span></header>
<dl class="entry-figures">@foreach(['purchase'=>'Purchase','packaging'=>'Packaging','branding'=>'Branding','other'=>'Other direct costs'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ $money($entry->$field) }}</dd></div>@endforeach<div><dt>Total investment</dt><dd>{{ $money($entry->purchase+$entry->packaging+$entry->branding+$entry->other) }}</dd></div></dl><button class="btn btn-light btn-sm" wire:click="viewBatchHistory({{ $entry->id }})">View batch history</button>
@elseif($breakdownKind==='packs')
<header><button class="record-link" wire:click="viewOrder({{ $entry->order_id }})">{{ $entry->number }}</button><span>Delivered {{ $entry->delivered_on }}</span></header><p>{{ $entry->name }} · {{ $entry->category==='honey'?'Honey':'Snack' }}</p><strong>{{ number_format($entry->quantity) }} packs @if($entry->category==='honey') × {{ number_format($entry->pack_quantity) }} g = {{ number_format($entry->quantity*$entry->pack_quantity) }} g honey @else <span>(separate from honey weight)</span> @endif</strong>
@else
<header><button class="record-link" wire:click="viewOrder({{ $entry->id }})">{{ $entry->number }}</button><span>{{ $breakdownKind==='pending'?$entry->status:'Delivered '.$entry->delivered_on }}</span></header>
<dl class="entry-figures">
@if($breakdownKind==='pending')<div><dt>Order total</dt><dd>{{ $money($entry->total) }}</dd></div><div><dt>Net paid</dt><dd>{{ $money($entry->paid) }}</dd></div><div><dt>Still due</dt><dd>{{ $money($entry->balance) }}</dd></div>
@else<div><dt>Products after discounts</dt><dd>{{ $money($entry->subtotal-$entry->discount) }}</dd></div><div><dt>Delivery charged</dt><dd>{{ $money($entry->delivery) }}</dd></div><div><dt>Delivered sales</dt><dd>{{ $money($entry->total) }}</dd></div>@if($breakdownKind==='profit')<div><dt>Allocated stock cost</dt><dd>{{ $money($entry->allocated_cost) }}</dd></div><div><dt>Actual courier</dt><dd>{{ $money($entry->courier) }}</dd></div><div><dt>Gross profit</dt><dd class="{{ $entry->total-$entry->allocated_cost-$entry->courier<0?'text-danger':'' }}">{{ $money($entry->total-$entry->allocated_cost-$entry->courier) }}</dd></div>@endif @endif
</dl>
@endif
</article>
@empty<div class="empty-state"><strong>No records for this view.</strong><p>Try clearing the date range, or record the relevant order, payment or batch.</p></div>@endforelse
</div>
@php($pageRows=$breakdownRows->getCollection())
<div class="breakdown-footer">
@if($breakdownKind==='cash')<p>This page: {{ $money($pageRows->where('kind','receipt')->sum('amount')) }} received − {{ $money($pageRows->where('kind','refund')->sum('amount')) }} refunded = <strong>{{ $money($pageRows->sum(fn($r)=>$r->kind==='receipt'?$r->amount:-$r->amount)) }}</strong> net.</p>
@elseif($breakdownKind==='packs')<p>This page: {{ number_format($pageRows->where('category','honey')->sum('quantity')) }} honey packs, {{ number_format($pageRows->where('category','honey')->sum(fn($r)=>$r->quantity*$r->pack_quantity)) }} g honey. Orders may have lines on more than one page; the delivered-order total above counts each order once.</p>
@else @php($pageTotal=$pageRows->sum(fn($r)=>match($breakdownKind){'pending'=>$r->balance,'investment'=>$r->purchase+$r->packaging+$r->branding+$r->other,'profit'=>$r->total-$r->allocated_cost-$r->courier,default=>$r->total}))<p>This page: <strong>{{ $money($pageTotal) }}</strong>. Total across all pages: <strong>{{ $money($summary[$keys[$breakdownKind]]) }}</strong>.</p>@endif
{{ $breakdownRows->links() }}</div>
