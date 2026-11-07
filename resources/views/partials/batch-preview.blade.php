<section class="correction-preview mt-4" aria-label="Correction preview">
<h3 class="h5">Review this correction</h3>
@php($effect=$batchPreview['effects'])
<dl class="preview-grid">
@foreach(['original_quantity'=>'Received quantity','physical'=>'On hand','purchase'=>'Purchase','packaging'=>'Packaging','branding'=>'Branding','other'=>'Other costs','received_on'=>'Received date','reference'=>'Reference','stock_key'=>'Stock pool'] as $field=>$label)
@if((string)$batchPreview['before'][$field] !== (string)$batchPreview['after'][$field])
<div><dt>{{ $label }}</dt><dd>{{ in_array($field,['purchase','packaging','branding','other'])?$money($batchPreview['before'][$field]):$batchPreview['before'][$field] }} → {{ in_array($field,['purchase','packaging','branding','other'])?$money($batchPreview['after'][$field]):$batchPreview['after'][$field] }}</dd></div>
@endif @endforeach
<div><dt>Reserved / available after saving</dt><dd>{{ number_format($effect['reserved']) }} / {{ number_format($effect['available']) }} base units</dd></div>
<div><dt>Total investment</dt><dd>{{ $money($effect['investment_before']) }} → {{ $money($effect['investment_after']) }}</dd></div>
<div><dt>Remaining stock value</dt><dd>{{ $money($effect['remaining_value']) }}</dd></div>
<div><dt>Historical delivered gross profit change</dt><dd>{{ $money($effect['delivered_profit_change']) }}</dd></div>
<div><dt>Receipt correction movement</dt><dd>{{ number_format($effect['received_movement']) }} base units</dd></div>
<div><dt>Later loss / recovery movement</dt><dd>{{ number_format($effect['physical_movement']) }} base units</dd></div>
</dl>
<p>Internal costs and prior profit reports use the corrected basis. Customer selling prices and issued invoices stay unchanged.</p>
<button class="btn btn-peach" wire:click="saveBatch" wire:loading.attr="disabled">Confirm correction</button>
</section>
