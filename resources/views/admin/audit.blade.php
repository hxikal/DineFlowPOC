@extends('layouts.app')
@section('content')
<section class="page-heading"><div><p class="eyebrow">P2 / ADMIN</p><h1><x-adminword key="audit" /></h1></div></section><section class="panel"><div class="table-wrap"><table><thead><tr>@foreach(['date','actor','action','reference','before','after'] as $field)<th><x-adminword :key="$field" /></th>@endforeach</tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>#{{ $log->user_id }}</td><td>{{ $log->action }}</td><td>{{ $log->reference_type }} #{{ $log->reference_id }}</td><td><pre>{{ json_encode($log->before_values,JSON_UNESCAPED_UNICODE) }}</pre></td><td><pre>{{ json_encode($log->after_values,JSON_UNESCAPED_UNICODE) }}</pre></td></tr>@empty<tr><td colspan="6"><x-adminword key="empty" /></td></tr>@endforelse</tbody></table></div><div class="pagination"><x-pager :paginator="$logs" /></div></section>
@endsection

