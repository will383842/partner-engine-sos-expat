<?php

namespace Database\Factories;

use App\Models\CsvImport;
use Illuminate\Database\Eloquent\Factories\Factory;

class CsvImportFactory extends Factory
{
    protected $model = CsvImport::class;

    public function definition(): array
    {
        return [
            'partner_firebase_id' => 'partner_' . $this->faker->uuid(),
            'uploaded_by' => 'uid_' . $this->faker->uuid(),
            'filename' => 'import_' . $this->faker->word() . '.csv',
            'total_rows' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'errors' => 0,
            'error_details' => [],
            'status' => 'processing',
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(int $imported = 10, int $duplicates = 0, int $errors = 0): static
    {
        return $this->state([
            'status' => 'completed',
            'total_rows' => $imported + $duplicates + $errors,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_details' => [['row' => 0, 'error' => 'Test error']],
            'completed_at' => now(),
        ]);
    }
}
