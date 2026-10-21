<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('administer') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($this->route('user'), 'user_id')],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'department' => ['required', Rule::in(['Hostess', 'F&B', 'Marcom'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => [$this->route('user') ? 'nullable' : 'required', 'string', 'min:12', 'max:255', 'confirmed'],
        ];
    }
}
