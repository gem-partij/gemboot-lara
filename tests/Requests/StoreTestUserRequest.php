<?php

namespace Gemboot\Tests\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTestUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->header('X-Test-Deny') !== 'yes';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower($this->input('email'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
        ];
    }
}
