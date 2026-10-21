@extends('layouts.app')
@section('content')
<section class="page-heading"><div><p class="eyebrow">DineFlow / {{ $outlet?->outlet_name }}</p><h1><x-reportword key="reports" /></h1><p class="muted"><x-reportword key="intro" /></p></div></section>
@if(!$outlet)<p class="alert"><x-reportword key="choose_outlet" /></p>@endif
@include('reports.filters')
<h2 class="spaced"><x-reportword key="summary" /></h2>
@include('reports.summary')
<section class="panel spaced"><div class="panel-heading report-heading"><div><h2><x-reportword key="results" /> · {{ number_format($bookings->total()) }}</h2><p class="muted">{{ $filters['from'] }} — {{ $filters['to'] }}</p></div><div class="form-actions"><a class="button" href="{{ route('analytics',$links) }}"><x-reportword key="analytics" /></a><a class="button" href="{{ route('reports.print',$links) }}" target="_blank" rel="noopener"><x-reportword key="print" /></a><a class="button primary" href="{{ route('reports.csv',$links) }}"><x-reportword key="csv" /></a></div></div>
@include('reports.table')
<div class="pagination"><x-pager :paginator="$bookings" /></div></section>
<p class="muted spaced report-note"><x-reportword key="history_note" /></p><p class="muted report-note"><x-reportword key="export_note" /></p>
@endsection
