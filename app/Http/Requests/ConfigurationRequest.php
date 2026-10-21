<?php

namespace App\Http\Requests;

use App\Services\ConfigurationManager;
use App\Services\OutletContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    public function rules(): array
    {
        $type = $this->route('type');
        $id = $this->route('record');
        abort_unless(isset(ConfigurationManager::TYPES[$type]), 404);
        $outletId = app(OutletContext::class)->current($this)?->getKey();
        if (in_array($type, ['sections', 'tables'], true)) {
            abort_unless($outletId, 409, __('admin.choose_outlet'));
        }
        $base = ['status' => ['required', Rule::in(['active', 'inactive'])]];
        $sort = ['sort_order' => ['required', 'integer', 'min:0', 'max:2147483647']];

        return match ($type) {
            'outlets' => array_merge($base, $sort, ['outlet_code' => ['required', 'string', 'max:30', Rule::unique('outlets', 'outlet_code')->ignore($id, 'outlet_id')], 'outlet_name' => ['required', 'string', 'max:100', Rule::unique('outlets', 'outlet_name')->ignore($id, 'outlet_id')]]),
            'sections' => array_merge($base, $sort, ['section_code' => ['required', 'string', 'max:20', Rule::unique('outlet_sections', 'section_code')->where('outlet_id', $outletId)->ignore($id, 'section_id')], 'section_name' => ['required', 'string', 'max:100']]),
            'tables' => array_merge($base, $sort, ['table_code' => ['required', 'string', 'max:20', Rule::unique('dining_tables', 'table_code')->where('outlet_id', $outletId)->ignore($id, 'table_id')], 'section_id' => ['nullable', 'integer', Rule::exists('outlet_sections', 'section_id')->where('outlet_id', $outletId)], 'table_number' => ['nullable', 'integer', 'min:1', 'max:2147483647']]),
            'payments' => array_merge($base, ['method_name' => ['required', 'string', 'max:100', Rule::unique('payment_methods', 'method_name')->ignore($id, 'payment_method_id')]]),
        };
    }
}
