<section class="report-summary" aria-label="{{ __('report.summary') }}">
@foreach(['booked'=>'booked','cancelled'=>'cancelled'] as $status=>$prefix)
<div class="panel report-total"><span class="badge {{ $status==='cancelled' ? 'badge-cancelled':'' }}"><x-reportword :key="$status" /></span><div><strong data-total="{{ $status }}-bookings">{{ number_format($summary[$status]['bookings']) }}</strong> <x-reportword key="total" /><span class="report-total-pax"><strong data-total="{{ $status }}-pax">{{ number_format($summary[$status]['pax']) }}</strong> <x-reportword key="pax" /></span></div></div>
@endforeach
</section>
