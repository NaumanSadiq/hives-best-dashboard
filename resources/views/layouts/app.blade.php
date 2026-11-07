<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><x-favicon /><title>{{ ucfirst($page ?? 'Dashboard') }} · Hive’s Best</title>@vite(['resources/css/app.css','resources/js/app.js']) @livewireStyles</head>
<body>
<div class="app-frame">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="offcanvas-header d-lg-none"><h2 class="h5">Hive’s Best</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button></div>
        <div class="brand-emblem" aria-hidden="true"><i aria-hidden="true" class="bi bi-flower2"></i><span class="little-bee">✦</span></div>
        <a href="{{ route('dashboard') }}" class="wordmark">Hive’s Best<span>GOODNESS, NATURALLY.</span></a>
        <div class="admin-greeting">Hi, {{ Str::before(auth()->user()->name,' ') }}! <span aria-hidden="true">☀</span></div>
        <nav class="nav flex-column">
            @foreach(['dashboard'=>['house-door-fill','Dashboard'],'orders'=>['bag-heart-fill','Orders'],'stock'=>['box-seam-fill','Stock']] as $key=>$nav)
            <a href="{{ route($key) }}" class="nav-link {{ ($page ?? '')===$key ? 'active':'' }}" @if(($page ?? '')===$key) aria-current="page" @endif><i aria-hidden="true" class="bi bi-{{ $nav[0] }}"></i>{{ $nav[1] }}</a>
            @endforeach
        </nav>
        <div class="sidebar-note"><span class="clay-sun">✿</span><strong>A little care.<br>A lot of goodness.</strong><p>Your daily hive of honey<br>and happy orders.</p><a class="btn btn-peach btn-sm" href="{{ route('stock') }}">Tend your stock <i aria-hidden="true" class="bi bi-arrow-right"></i></a></div>
        <div class="sidebar-foot"><span class="online-dot"></span> Private admin workspace</div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="logout"><i aria-hidden="true" class="bi bi-box-arrow-left"></i> Sign out</button></form>
    </aside>
    <main class="main-content">{{ $slot }}</main>
</div>
@livewireScripts
</body></html>
