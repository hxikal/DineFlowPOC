<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed']];
    }
}
