<div>
@php($money = fn($v) => \App\Support\Money::format($v))
<header class="topbar"><div class="d-flex align-items-center gap-3"><x-brand emblem class="mobile-brand d-lg-none" /><button class="btn icon-button d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Open menu"><i aria-hidden="true" class="bi bi-list"></i></button><h1>{{ ucfirst($page) }}</h1></div><label class="search-pill"><i aria-hidden="true" class="bi bi-search"></i><x-input aria-label="Search orders by number or customer" wire:model.live.debounce.350ms="search" placeholder="Search orders, customers..." /></label><a class="icon-button notification" href="{{ route('orders') }}" aria-label="{{ $dispatchCount }} orders awaiting dispatch"><i aria-hidden="true" class="bi bi-bell"></i>@if($dispatchCount)<span>{{ $dispatchCount }}</span>@endif</a><a class="icon-button avatar" href="{{ route('dashboard') }}" aria-label="Admin dashboard"><i aria-hidden="true" class="bi bi-person"></i></a></header>
<div wire:loading.delay class="loading-pill" role="status"><span class="spinner-border spinner-border-sm"></span> Updating your hive…</div>
@if($notice)<div class="alert alert-success d-flex justify-content-between" role="status">{{ $notice }}<button class="btn-close" aria-label="Dismiss notification" wire:click="$set('notice','')"></button></div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check these details.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

@if($page==='dashboard')<div class="dashboard-actions"><p class="muted mb-0">Monitor sales, payments and inventory.</p><div class="d-flex gap-2"><button class="btn btn-peach" wire:click="newOrder">New Order</button><button class="btn btn-sage" wire:click="batchForm">Receive Stock</button></div></div>@endif

@include('partials.page-toolbar')

@if($page==='dashboard')
<nav class="nav nav-tabs dashboard-tabs" role="tablist" aria-label="Dashboard sections" x-data @keydown.arrow-right.prevent="const tabs=[...$el.querySelectorAll('[role=tab]')];const next=tabs[(tabs.indexOf(document.activeElement)+1)%tabs.length];next.focus();next.click()" @keydown.arrow-left.prevent="const tabs=[...$el.querySelectorAll('[role=tab]')];const next=tabs[(tabs.indexOf(document.activeElement)+tabs.length-1)%tabs.length];next.focus();next.click()" @keydown.home.prevent="$el.querySelector('[role=tab]').focus();$el.querySelector('[role=tab]').click()" @keydown.end.prevent="const tabs=$el.querySelectorAll('[role=tab]');tabs[tabs.length-1].focus();tabs[tabs.length-1].click()">
@foreach(['overview'=>'Overview','charts'=>'Charts','finance'=>'Finance','stock'=>'Stock & Batches','customers'=>'Customers'] as $key=>$label)<button type="button" id="tab-{{ $key }}" role="tab" class="nav-link {{ $dashboardTab===$key?'active':'' }}" aria-selected="{{ $dashboardTab===$key?'true':'false' }}" aria-controls="pane-{{ $key }}" tabindex="{{ $dashboardTab===$key?'0':'-1' }}" wire:click="selectDashboardTab('{{ $key }}')">{{ $label }}</button>@endforeach</nav>
<section id="pane-overview" role="tabpanel" aria-labelledby="tab-overview" tabindex="0" @if($dashboardTab!=='overview') hidden @endif>
<div class="current-balances">@include('partials.savings-card') @include('partials.current-stock')</div>
@include('partials.financial-summary')
</section>
@endif

@if($page==='dashboard')
<section id="pane-charts" role="tabpanel" aria-labelledby="tab-charts" tabindex="0" @if($dashboardTab!=='charts') hidden @endif>
@include('partials.dashboard-charts')
@include('partials.order-list')
@endif

@if($page==='dashboard')</section>
<section id="pane-finance" role="tabpanel" aria-labelledby="tab-finance" tabindex="0" @if($dashboardTab!=='finance') hidden @endif>@include('partials.dashboard-finance')</section>
<section id="pane-stock" role="tabpanel" aria-labelledby="tab-stock" tabindex="0" @if($dashboardTab!=='stock') hidden @endif>@include('partials.dashboard-stock')</section>
<section id="pane-customers" role="tabpanel" aria-labelledby="tab-customers" tabindex="0" @if($dashboardTab!=='customers') hidden @endif>@include('partials.top-customers')</section>
@endif
@if($page==='stock') @include('partials.stock') @endif
@if($page==='customers') @include('partials.customers') @endif


@if($page==='orders')
@include('partials.order-list')
@endif



@if($form || $selectedOrder)<div class="modal-backdrop show"></div><div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="dialog-title" x-data="hiveDialog" x-on:keydown.tab="trap($event)" x-on:keydown.escape="$wire.closeDialog()" wire:key="dialog-{{ $form }}-{{ $selected }}"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h4" id="dialog-title">{{ $selectedOrder ? $selectedOrder->number : ['order'=>($editing?'Edit Order':'New Order'),'product'=>'Product','batch'=>($editingBatch?'Edit Batch':'Receive Stock'),'history'=>'Batch History','breakdown'=>'View Breakdown','payment-correction'=>'Correct payment entry','customer'=>'Customer details','customer-edit'=>'Edit customer','order-amounts'=>'Edit Order amounts','savings'=>'Total Box Savings','savings-history'=>'Savings history'][$form] }}</h2><button class="btn-close" aria-label="Close dialog" wire:click="closeDialog"></button></div><div class="modal-body">
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
@if($form==='order-amounts') @include('partials.order-amounts') @endif
@if($form==='savings') @include('partials.savings-form') @endif
@if($form==='savings-history') @include('partials.savings-history') @endif
@if($form==='order') @include('partials.order-form') @endif
@if($form==='product' || $form==='batch') @include('partials.stock-form') @endif
@if($form==='breakdown') @include('partials.metric-breakdown') @endif
@if($form==='payment-correction') @include('partials.payment-correction') @endif
@if($form==='history') @include('partials.batch-history', ['historyId'=>$historyBatch]) @endif
@if($form==='customer') @include('partials.customer-detail') @endif
@if($form==='customer-edit') @include('partials.customer-edit') @endif
@if($selectedOrder) @include('partials.order-detail') @endif
</div></div></div></div>@endif
</div>
