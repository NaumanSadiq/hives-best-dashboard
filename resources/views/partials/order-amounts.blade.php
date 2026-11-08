<p class="muted">Correct prices and charges. Quantities, stock allocations, payments and issued invoices are retained.</p>
<form wire:submit="previewAmounts">
<div class="row g-3">
@foreach(\Illuminate\Support\Facades\DB::table('order_items')->where('order_id',$amountOrder)->get() as $item)
<div class="col-sm-6"><label class="form-label" for="price-{{ $item->id }}">{{ $item->name }} · {{ $item->quantity }} packs · Unit price (PKR)</label><x-input id="price-{{ $item->id }}" class="form-control" wire:model="amountEdit.prices.{{ $item->id }}" type="number" min="0" step="0.01" required /></div>
@endforeach
@foreach(['discount'=>'Order discount','delivery'=>'Delivery charged to customer','courier'=>'Actual courier cost (separate)'] as $field=>$label)<div class="col-sm-4"><label class="form-label" for="correct-{{ $field }}">{{ $label }} (PKR)</label><x-input id="correct-{{ $field }}" class="form-control" wire:model="amountEdit.{{ $field }}" type="number" min="0" step="0.01" required /></div>@endforeach
<div class="col-12"><label class="form-label" for="amount-reason">Correction reason</label><x-input id="amount-reason" class="form-control" wire:model="amountReason" required minlength="5" /></div></div>
<button class="btn btn-sage mt-3" wire:loading.attr="disabled">Preview correction</button>
</form>
@if($amountReview)
<section class="detail-box mt-3" aria-live="polite"><h3 class="h6">Review before confirming</h3><dl class="totals-list">
<div><dt>Previous total</dt><dd>{{ $money($amountReview['before']['order']['total']) }}</dd></div><div><dt>New total</dt><dd>{{ $money($amountReview['values']['total']) }}</dd></div><div><dt>Payments already recorded</dt><dd>{{ $money($amountReview['before']['paid']) }}</dd></div><div><dt>{{ $amountReview['before']['paid']>$amountReview['values']['total']?'Overpaid / refund due':'Left to pay' }}</dt><dd>{{ $money(abs($amountReview['values']['total']-$amountReview['before']['paid'])) }}</dd></div></dl><p class="small">{{ $amountReview['reason'] }} · No receipt or refund will be created.</p><button class="btn btn-peach" wire:click="saveAmounts" wire:loading.attr="disabled">Confirm amount correction</button></section>
@endif
