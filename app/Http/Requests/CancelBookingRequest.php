<?php

namespace App\Http\Requests;

use App\Services\BookingManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge(BookingManager::cancelRules(), ['form_outlet_id' => ['nullable', 'integer']]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('form_outlet_id') && (int) $this->input('form_outlet_id') !== (int) $this->session()->get('outlet_id')) {
                $validator->errors()->add('form_outlet_id', __('booking.context_changed'));
            }
        }];
    }
}
