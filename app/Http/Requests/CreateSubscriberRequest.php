<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'country' => 'nullable|string|size:2',
            'language' => 'sometimes|string|max:5',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'custom_fields' => 'nullable|array',
        ];
    }
}
