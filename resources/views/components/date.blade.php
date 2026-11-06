@php($model=$attributes->wire('model')->value())
@php($max=$attributes->get('max',in_array($model,['from','to'])?null:today()->toDateString()))
<span class="field-wrap date-control">
<span wire:ignore x-data="hiveDate($wire.entangle(@js($model)), @js(today()->toDateString()))">
<input x-ref="control" type="text" placeholder="YYYY-MM-DD" inputmode="numeric" autocomplete="off" {{ $attributes->except(['type','max'])->whereDoesntStartWith('wire:model') }} @if($max) max="{{ $max }}" @endif aria-describedby="error-{{ $model }}" >
</span><span class="invalid-feedback" id="error-{{ $model }}" role="status">@error($model){{ $message }}@enderror</span>
</span>
