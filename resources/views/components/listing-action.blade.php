@props(['label', 'icon'])
<button type="button" {{ $attributes->class(['listing-action']) }} title="{{ $label }}" aria-label="{{ $label }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></button>
