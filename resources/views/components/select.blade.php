@php($model=$attributes->wire('model')->value())
<div class="field-wrap">
<div wire:ignore x-data="hiveSelect($wire.entangle(@js($model)){{ $attributes->wire('model')->hasModifier('live') ? '.live' : '' }})">
<select x-ref="control" {{ $attributes->except('wire:model')->whereDoesntStartWith('wire:model') }} aria-describedby="error-{{ $model }}">{{ $slot }}</select>
</div><span class="invalid-feedback" id="error-{{ $model }}" role="status">@error($model){{ $message }}@enderror</span>
</div>
