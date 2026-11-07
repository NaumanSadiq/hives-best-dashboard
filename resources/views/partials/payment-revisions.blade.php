@php($revisions=\Illuminate\Support\Facades\DB::table('payment_changes as c')->leftJoin('users as u','u.id','=','c.user_id')->where('receipt_id',$receiptId)->select('c.*','u.name as actor')->orderByDesc('c.id')->get())
@if($revisions->isNotEmpty())<details class="mt-3"><summary>Payment correction history ({{ $revisions->count() }})</summary>
@foreach($revisions as $revision) @php($prior=json_decode($revision->before,true)) @php($next=json_decode($revision->after,true))
<article class="audit-event"><strong>{{ $revision->created_at }} Asia/Karachi · {{ $revision->actor?:'Administrator not recorded' }}</strong><p>{{ $revision->reason }}</p><dl class="preview-grid">
@foreach(['amount'=>'Amount','paid_on'=>'Payment date','reference'=>'Reference'] as $field=>$label) @if((string)($prior[$field]??'')!==(string)($next[$field]??''))<div><dt>{{ $label }}</dt><dd>{{ $field==='amount'?$money($prior[$field]):($prior[$field]?:'Not recorded') }} → {{ $field==='amount'?$money($next[$field]):($next[$field]?:'Not recorded') }}</dd></div>@endif @endforeach
<div><dt>All-dates cash change</dt><dd>{{ $money($revision->cash_change) }}</dd></div></dl></article>@endforeach
</details>@endif
