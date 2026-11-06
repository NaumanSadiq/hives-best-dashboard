@php($model=$attributes->wire('model')->value())
<span class="field-wrap"><textarea {{ $attributes->class(['is-invalid'=>$model && $errors->has($model)]) }} @if($model) aria-describedby="error-{{ $model }}" @endif>{{ $slot }}</textarea>@if($model)<span class="invalid-feedback" id="error-{{ $model }}" role="status">@error($model){{ $message }}@enderror</span>@endif</span>
