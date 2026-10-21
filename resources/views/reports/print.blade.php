<!DOCTYPE html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('report.reports') }} · DineFlow</title>@vite(['resources/css/app.css'])</head><body class="print-body"><main class="print-sheet report-print"><div class="print-actions"><button class="button primary" onclick="window.print()">{{ __('report.print') }}</button></div><header class="print-heading"><strong>DineFlow</strong><span>{{ $outlet?->outlet_name ?? __('report.choose_outlet') }}</span></header><h1>{{ __('report.reports') }}</h1><p>{{ $filters['from'] }} — {{ $filters['to'] }}</p><p class="muted">{{ __('report.generated') }}: {{ $generatedAt->format('d/m/Y H:i:s') }} Asia/Kuala_Lumpur</p>
@include('reports.applied-filters')
@include('reports.summary')
<p class="report-note spaced">{{ __('report.history_note') }}</p>
@include('reports.table',['printing'=>true])
</main></body></html>
