@props(['emblem' => false])
<a href="{{ route('dashboard') }}" {{ $attributes->class(['brand-lockup', 'brand-emblem-only'=>$emblem]) }} aria-label="Hive’s Best dashboard"><img src="{{ asset('images/brand/'.($emblem?'logo-emblem.png':'logo-full.png')).'?v='.config('branding.version') }}" alt="Hive’s Best Honey" width="{{ $emblem?'320':'640' }}" height="{{ $emblem?'245':'530' }}"></a>
