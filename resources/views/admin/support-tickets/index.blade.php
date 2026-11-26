@extends('admin.layout')
@section('content')
<div class="eyebrow">Customer support</div>
<div class="section-head"><div><h2 class="page-title">Support inbox</h2><p class="page-subtitle">Review user requests, reply, and track resolution status.</p></div></div>
<section class="card">
    <form class="toolbar" method="GET">
        <div class="search"><input class="input" type="search" name="q" value="{{ request('q') }}" placeholder="Search tickets, users or email"><button class="button secondary">Search</button></div>
        <select class="select" name="status" style="max-width:210px" onchange="this.form.submit()">
            @foreach(['all'=>'All statuses','open'=>'Open','in_progress'=>'In progress','waiting_on_user'=>'Waiting on user','resolved'=>'Resolved','closed'=>'Closed'] as $value=>$label)
                <option value="{{ $value }}" @selected(request('status','all')===$value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Ticket</th><th>User</th><th>Category</th><th>Status</th><th>Last activity</th><th></th></tr></thead><tbody>
    @forelse($tickets as $ticket)
        <tr>
            <td><strong>{{ $ticket->subject }}</strong><div class="muted mono">{{ $ticket->ticket_number }}</div></td>
            <td>{{ $ticket->user?->full_name ?: $ticket->user?->username }}<div class="muted">{{ $ticket->user?->email }}</div></td>
            <td>{{ str($ticket->category)->replace('_',' ')->title() }}</td>
            <td><span class="badge {{ in_array($ticket->status,['resolved','closed'],true)?'green':(in_array($ticket->status,['open','in_progress'],true)?'gold':'') }}">{{ str($ticket->status)->replace('_',' ')->title() }}</span></td>
            <td>{{ $ticket->last_message_at?->format('Y-m-d H:i') ?? $ticket->created_at?->format('Y-m-d H:i') }}</td>
            <td><a class="button secondary small" href="{{ route('admin.support-tickets.show',$ticket) }}">Open ticket</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted" style="text-align:center;padding:30px">No tickets match this view.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination"><span>Showing {{ $tickets->firstItem()??0 }}–{{ $tickets->lastItem()??0 }} of {{ $tickets->total() }}</span>{{ $tickets->links() }}</div>
</section>
@endsection
