@extends('layouts.app')
@section('content')
<section class="page-heading"><div><p class="eyebrow">DineFlow / {{ $outlet?->outlet_name }}</p><h1><x-reportword key="analytics" /></h1><p class="muted">{{ $filters['from'] }} — {{ $filters['to'] }}</p></div><a class="button" href="{{ route('reports.index',$links) }}"><x-reportword key="back_report" /></a></section>
@if(!$outlet)<p class="alert"><x-reportword key="choose_outlet" /></p>@endif
@include('reports.applied-filters')
@include('reports.summary')
<p class="muted spaced report-note"><x-reportword key="analytics_note" /></p>
<section class="panel spaced"><div class="panel-heading"><h2><x-reportword key="trend" /></h2></div><div class="table-wrap"><table><thead><tr>@foreach(['dining_date','status','total','pax'] as $key)<th><x-reportword :key="$key" /></th>@endforeach</tr></thead><tbody>
@php($maxBookings = max(1,(int) $trend->max('bookings')))
@forelse($trend as $row)<tr><td>{{ $row->dining_date->format('d/m/Y') }}</td><td><x-reportword :key="$row->booking_status" /></td><td><div class="trend-value"><span class="trend-track" aria-hidden="true"><i class="{{ $row->booking_status==='cancelled' ? 'trend-cancelled':'' }}" style="width:{{ round($row->bookings/$maxBookings*100,2) }}%"></i></span><strong>{{ number_format($row->bookings) }}</strong></div></td><td>{{ number_format($row->pax) }}</td></tr>@empty<tr><td colspan="4"><x-reportword key="empty" /></td></tr>@endforelse
</tbody></table></div></section>
<section class="panel spaced"><div class="panel-heading"><h2><x-reportword key="payments" /></h2></div><div class="table-wrap"><table><thead><tr>@foreach(['payment_method_id','status','total','pax'] as $key)<th><x-reportword :key="$key" /></th>@endforeach</tr></thead><tbody>@forelse($payments as $row)<tr><td>{{ $row->paymentMethod->method_name }}</td><td><x-reportword :key="$row->booking_status" /></td><td>{{ number_format($row->bookings) }}</td><td>{{ number_format($row->pax) }}</td></tr>@empty<tr><td colspan="4"><x-reportword key="empty" /></td></tr>@endforelse</tbody></table></div></section>
@endsection
