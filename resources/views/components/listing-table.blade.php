@props(['columns', 'label', 'bounded' => true])
<div {{ $attributes->class(['records-scroll shared-listing-scroll']) }} @if($bounded) x-ref="records" @endif tabindex="0" role="region" aria-label="{{ $label }}">
<table class="table listing-table"><colgroup>@foreach($columns as $column)<col @if(is_array($column)&&isset($column['width'])) style="width:{{ $column['width'] }}" @endif>@endforeach</colgroup><thead><tr>@foreach($columns as $column)<th scope="col" class="{{ is_array($column)&&($column['numeric']??false)?'listing-numeric':'' }}">{{ is_array($column)?$column['label']:$column }}</th>@endforeach</tr></thead><tbody>{{ $slot }}</tbody></table>
</div>
