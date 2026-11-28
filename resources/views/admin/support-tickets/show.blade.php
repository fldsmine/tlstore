@extends('admin.layout')
@section('content')
<div class="eyebrow">Customer support · {{ $ticket->ticket_number }}</div>
<div class="section-head"><div><h2 class="page-title">{{ $ticket->subject }}</h2><p class="page-subtitle">{{ $ticket->ownerName() }} · {{ $ticket->contactEmail() ?: 'no email' }} · {{ str($ticket->category)->replace('_',' ')->title() }}@if($ticket->isGuest()) · <strong>submitted through the public contact form</strong>@endif</p></div><a class="button secondary" href="{{ route('admin.support-tickets.index') }}">← Inbox</a></div>
<section class="grid detail-grid" style="margin-bottom:16px">
    <div class="card"><h2 style="margin-top:0;font-size:15px">Ticket status</h2>
        <form method="POST" action="{{ route('admin.support-tickets.update',$ticket) }}">@csrf @method('PATCH')
            <div class="form-grid">
                <div class="field"><label>Status</label><select class="select" name="status">@foreach(['open'=>'Open','in_progress'=>'In progress','waiting_on_user'=>'Waiting on user','resolved'=>'Resolved','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected($ticket->status===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label>Priority</label><select class="select" name="priority">@foreach(['low'=>'Low','normal'=>'Normal','high'=>'High','urgent'=>'Urgent'] as $value=>$label)<option value="{{ $value }}" @selected($ticket->priority===$value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <button class="button" style="margin-top:12px">Save status</button>
        </form>
    </div>
    <div class="card"><h2 style="margin-top:0;font-size:15px">Customer</h2><div class="detail-row"><span>Name</span><strong>{{ $ticket->user?->full_name ?: ($ticket->guest_name ?: '—') }}</strong></div><div class="detail-row"><span>Username</span><strong>{{ $ticket->user?->username ?: '—' }}</strong></div><div class="detail-row"><span>Email</span><strong>{{ $ticket->contactEmail() ?: '—' }}</strong></div><div class="detail-row"><span>Source</span><strong>{{ $ticket->isGuest() ? 'Public contact form (no account)' : 'Signed-in ticket' }}</strong></div>@if($ticket->isGuest() && $ticket->ip_address)<div class="detail-row"><span>Source IP</span><strong class="mono">{{ $ticket->ip_address }}</strong></div>@endif<div class="detail-row"><span>Created</span><strong>{{ $ticket->created_at?->format('Y-m-d H:i') }}</strong></div></div>
</section>
<section class="card" style="margin-bottom:16px"><h2 style="margin-top:0;font-size:15px">Conversation</h2>
    @foreach($ticket->messages as $message)
        @php($fromAdmin=$message->sender_type==='admin')
        <div style="max-width:850px;margin:12px 0 12px {{ $fromAdmin?'auto':'0' }};padding:13px 15px;border:1px solid var(--line);border-radius:14px;background:{{ $fromAdmin?'#edf6f0':'#fff' }}">
            <div class="section-head" style="margin-bottom:7px"><strong>{{ $fromAdmin?($message->senderAdmin?->full_name??'TLStore Support'):($message->senderUser?->full_name??$ticket->user?->full_name??($ticket->guest_name?:'Guest')) }}</strong><span class="muted">{{ $message->created_at?->format('M j, Y · H:i') }}</span></div>
            <div style="white-space:pre-wrap">{{ $message->message }}</div>
        </div>
    @endforeach
</section>
@if($ticket->status!=='closed')
    <section class="card"><h2 style="margin-top:0;font-size:15px">{{ $ticket->isGuest() ? 'Reply by email' : 'Reply to user' }}</h2>@if($ticket->isGuest())<p class="muted" style="margin-top:-4px">This ticket was submitted without an account, so your reply is emailed to {{ $ticket->contactEmail() ?: 'the guest' }}.</p>@endif<form method="POST" action="{{ route('admin.support-tickets.reply',$ticket) }}">@csrf<textarea class="textarea" name="message" required minlength="2" maxlength="5000" placeholder="Write a helpful reply…">{{ old('message') }}</textarea><button class="button" style="margin-top:12px">Send reply</button></form></section>
@else
    <div class="flash">This ticket is closed. Change its status above to reopen it before replying.</div>
@endif
@endsection
