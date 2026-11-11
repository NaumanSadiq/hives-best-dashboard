<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><x-favicon /><title>{{ ucfirst($page ?? 'Dashboard') }} · Hive’s Best</title>@vite(['resources/css/app.css','resources/js/app.js']) @livewireStyles</head>
<body>
<div class="app-frame">
    <aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="offcanvas-header d-lg-none"><span class="visually-hidden">Main navigation</span><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button></div>
        <x-brand />
        <div class="admin-greeting">Hi, {{ Str::before(auth()->user()->name,' ') }}! <span aria-hidden="true">☀</span></div>
        <nav class="nav flex-column">
            @foreach(['dashboard'=>['grid','Dashboard'],'orders'=>['bag','Orders'],'stock'=>['box-seam','Stock'],'customers'=>['people','Customers']] as $key=>$nav)
            <a href="{{ route($key) }}" class="nav-link {{ ($page ?? '')===$key ? 'active':'' }}" @if(($page ?? '')===$key) aria-current="page" @endif><i aria-hidden="true" class="bi bi-{{ $nav[0] }}"></i>{{ $nav[1] }}</a>
            @endforeach
        </nav>
        <div class="sidebar-foot"><span class="online-dot"></span> Private admin workspace</div>
        <form method="post" action="{{ route('logout') }}">@csrf<button class="logout"><i aria-hidden="true" class="bi bi-box-arrow-left"></i> Sign out</button></form>
    </aside>
    <main class="main-content">{{ $slot }}</main>
</div>
@livewireScripts
</body></html>
