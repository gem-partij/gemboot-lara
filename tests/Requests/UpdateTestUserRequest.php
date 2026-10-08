<?php

namespace Gemboot\Tests\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes',
            // The route parameter works as in any FormRequest.
            'email' => ['sometimes', 'email', Rule::unique('gemboot_test_user')->ignore($this->route('user'))],
        ];
    }
}
