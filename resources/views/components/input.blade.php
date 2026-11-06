@props(['type'=>'text'])
@php($model=$attributes->wire('model')->value())
@if($type==='date')
<x-date {{ $attributes }} />
@else
<span class="field-wrap"><input type="{{ $type }}" {{ $attributes->class(['is-invalid'=>$model && $errors->has($model)]) }} @if($model) aria-describedby="error-{{ $model }}" aria-invalid="{{ $errors->has($model)?'true':'false' }}" @endif>
@if($model)<span class="invalid-feedback" id="error-{{ $model }}" role="status">@error($model){{ $message }}@enderror</span>@endif</span>
@endif
