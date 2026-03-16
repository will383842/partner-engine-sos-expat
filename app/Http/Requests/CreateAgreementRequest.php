<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'status' => 'sometimes|in:draft,active,paused',
            'discount_type' => 'required|in:none,fixed,percent',
            'discount_value' => 'required_unless:discount_type,none|integer|min:0',
            'discount_max_cents' => 'nullable|integer|min:0',
            'discount_label' => 'nullable|string|max:255',
            'commission_per_call_lawyer' => 'sometimes|integer|min:0',
            'commission_per_call_expat' => 'sometimes|integer|min:0',
            'commission_type' => 'sometimes|in:fixed,percent',
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'max_subscribers' => 'nullable|integer|min:1',
            'max_calls_per_subscriber' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'notes' => 'nullable|string',
        ];
    }
}
