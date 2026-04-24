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

            // SOS-Call per-subscriber expiration override.
            // If provided, this client's SOS-Call access expires on this exact date,
            // overriding the agreement's default_subscriber_duration_days.
            // Still capped by agreement.max_subscriber_duration_days if configured.
            'expires_at' => 'nullable|date|after:now',

            // Per-subscriber call quota override (optional).
            // Defaults to agreement.max_calls_per_subscriber if not specified.
            'max_calls_override' => 'nullable|integer|min:0|max:10000',

            // Hierarchy — partner-defined segmentation
            'group_label' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'external_id' => 'nullable|string|max:255',
        ];
    }
}
