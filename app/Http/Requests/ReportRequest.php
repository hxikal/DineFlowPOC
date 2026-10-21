<?php

namespace App\Http\Requests;

use App\Services\BookingReports;
use App\Services\OutletContext;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && in_array($this->user()->role, ['admin', 'user'], true);
    }

    protected function prepareForValidation(): void
    {
        $date = $this->session()->get('dining_date', now()->toDateString());
        $this->merge(['from' => $this->input('from') ?: $date, 'to' => $this->input('to') ?: $date,
            'sort' => $this->input('sort') ?: 'dining_date', 'direction' => $this->input('direction') ?: 'desc']);
    }

    public function rules(): array
    {
        $outletId = app(OutletContext::class)->current($this)?->getKey() ?? 0;

        return ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(['booked', 'cancelled'])], 'q' => ['nullable', 'string', 'max:100'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'payment_method_id')],
            'table_id' => ['nullable', 'integer', Rule::exists('dining_tables', 'table_id')->where('outlet_id', $outletId)],
            'section_id' => ['nullable', 'integer', Rule::exists('outlet_sections', 'section_id')->where('outlet_id', $outletId)],
            'sort' => [Rule::in(BookingReports::SORTS)], 'direction' => [Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'], 'outlet_context' => ['nullable', 'integer']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('outlet_context') && (int) $this->input('outlet_context') !== (int) $this->session()->get('outlet_id')) {
                $validator->errors()->add('outlet_context', __('report.context_changed'));
            }
        }];
    }

    public function attributes(): array
    {
        return collect(['from', 'to', 'status', 'payment_method_id', 'table_id', 'section_id', 'q', 'sort', 'direction'])->mapWithKeys(fn ($key) => [$key => __('report.'.$key)])->all();
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        if ($validator->errors()->has('outlet_context') && ! $this->expectsJson()) {
            throw new HttpResponseException(redirect()->route('reports.index')->withErrors($validator));
        }

        parent::failedValidation($validator);
    }
}
