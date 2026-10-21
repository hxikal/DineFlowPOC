<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\DiningTable;
use App\Models\OutletSection;
use App\Models\PaymentMethod;
use App\Services\BookingReports;
use App\Services\OutletContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function data(ReportRequest $request): array
    {
        $outlet = app(OutletContext::class)->current($request);
        $filters = $request->safe()->except(['page', 'outlet_context']);
        $links = array_filter($filters, fn ($value) => $value !== null && $value !== '');
        $links['outlet_context'] = $outlet?->getKey() ?? 0;
        $filterNames = [];
        foreach (['payment_method_id' => [PaymentMethod::class, 'method_name'], 'table_id' => [DiningTable::class, 'table_code'], 'section_id' => [OutletSection::class, 'section_name']] as $key => [$model, $column]) {
            if (! empty($filters[$key])) {
                $filterNames[$key] = $model::findOrFail($filters[$key])->$column;
            }
        }

        return compact('outlet', 'filters', 'links', 'filterNames');
    }

    public function index(ReportRequest $request, BookingReports $reports): View
    {
        $data = $this->data($request);

        return DB::transaction(function () use ($reports, $data): View {
            $query = $reports->query($data['outlet']?->getKey(), $data['filters']);

            return view('reports.index', $data + ['summary' => $reports->summary($query),
                'bookings' => $reports->ordered($query, $data['filters'])->with(['assignments.table', 'paymentMethod'])->paginate(20)->appends($data['links']),
                'payments' => PaymentMethod::orderBy('method_name')->get(),
                'tables' => DiningTable::where('outlet_id', $data['outlet']?->getKey() ?? 0)->orderBy('table_code')->get(),
                'sections' => OutletSection::where('outlet_id', $data['outlet']?->getKey() ?? 0)->orderBy('sort_order')->orderBy('section_name')->get(), 'reports' => $reports]);
        });
    }

    public function analytics(ReportRequest $request, BookingReports $reports): View
    {
        $data = $this->data($request);

        return DB::transaction(function () use ($data, $reports): View {
            $query = $reports->query($data['outlet']?->getKey(), $data['filters']);

            return view('reports.analytics', $data + ['summary' => $reports->summary($query)] + $reports->analytics($query));
        });
    }

    public function print(ReportRequest $request, BookingReports $reports): View
    {
        $data = $this->data($request);

        return DB::transaction(function () use ($data, $reports): View {
            $query = $reports->query($data['outlet']?->getKey(), $data['filters']);

            return view('reports.print', $data + ['summary' => $reports->summary($query),
                'bookings' => $reports->ordered($query, $data['filters'])->with(['assignments.table', 'paymentMethod'])->get(),
                'reports' => $reports, 'generatedAt' => now()]);
        });
    }

    public function csv(ReportRequest $request, BookingReports $reports): StreamedResponse
    {
        $data = $this->data($request);

        return response()->streamDownload(function () use ($data, $reports): void {
            DB::transaction(function () use ($data, $reports): void {
                $stream = fopen('php://output', 'wb');
                fwrite($stream, "\xEF\xBB\xBF");
                $write = fn (array $row) => fputcsv($stream, $row, ',', '"', '', "\r\n");
                $write(array_map(fn ($key) => __('report.'.$key), ['outlet', 'code', 'dining_date', 'guest', 'contact', 'tables', 'pax', 'status', 'payment_method_id', 'remark', 'cancel_reason']));
                try {
                    $query = $reports->ordered($reports->query($data['outlet']?->getKey(), $data['filters']), $data['filters']);
                    foreach ($query->with(['assignments.table', 'paymentMethod'])->lazy(250) as $booking) {
                        $write([$reports->csvText($data['outlet']->outlet_name), $reports->csvText($booking->booking_code), $booking->dining_date->toDateString(),
                            $reports->csvText($booking->customer_name), $reports->csvText($booking->contact_number), $reports->csvText($reports->tableCodes($booking)), $booking->pax,
                            __('report.'.$booking->booking_status), $reports->csvText($booking->paymentMethod->method_name), $reports->csvText($booking->remark), $reports->csvText($booking->cancellation_reason)]);
                    }
                } finally {
                    fclose($stream);
                }
            });
        }, 'dineflow-'.$data['filters']['from'].'-'.$data['filters']['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
