<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240', // 10MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Le fichier ne doit pas dépasser 10 Mo.',
            'file.mimes' => 'Le fichier doit être au format CSV ou XLSX.',
        ];
    }
}
