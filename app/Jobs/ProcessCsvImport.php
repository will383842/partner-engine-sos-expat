<?php

namespace App\Jobs;

use App\Models\Agreement;
use App\Models\CsvImport;
use App\Models\Subscriber;
use App\Models\SubscriberActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        public int $csvImportId,
        public string $partnerFirebaseId,
        public string $filePath,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $csvImport = CsvImport::find($this->csvImportId);
        if (!$csvImport) {
            return;
        }

        try {
            $this->processFile($csvImport);
        } catch (\Exception $e) {
            $csvImport->update([
                'status' => 'failed',
                'error_details' => [['row' => 0, 'error' => $e->getMessage()]],
                'completed_at' => now(),
            ]);

            Log::error('CSV import failed', [
                'import_id' => $this->csvImportId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function processFile(CsvImport $csvImport): void
    {
        $fullPath = Storage::disk('local')->path($this->filePath);

        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$this->filePath}");
        }

        $handle = fopen($fullPath, 'r');
        if (!$handle) {
            throw new \Exception("Cannot open file: {$this->filePath}");
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            throw new \Exception('Empty CSV file');
        }

        // Normalize headers
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Get active agreement
        $agreement = Agreement::where('partner_firebase_id', $this->partnerFirebaseId)
            ->where('status', 'active')
            ->latest()
            ->first();

        $imported = 0;
        $duplicates = 0;
        $errors = 0;
        $errorDetails = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Map columns
            $data = array_combine($header, array_pad($row, count($header), ''));

            // Validate (strict: ISO country codes + supported languages)
            $validator = Validator::make($data, [
                'email' => 'required|email|max:255',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:50',
                'country' => 'nullable|string|size:2|regex:/^[A-Za-z]{2}$/',
                'language' => 'nullable|string|in:fr,en,es,de,it,pt,nl,ar,zh',
                // Per-row SOS-Call expiration override. Accepts YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.
                'expires_at' => 'nullable|date',
                // Hierarchy CSV columns (all optional)
                'group_label' => 'nullable|string|max:120',
                'region' => 'nullable|string|max:120',
                'department' => 'nullable|string|max:120',
                'external_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                $errors++;
                $errorDetails[] = [
                    'row' => $rowNum,
                    'email' => $data['email'] ?? 'N/A',
                    'error' => implode(', ', $validator->errors()->all()),
                ];
                continue;
            }

            $email = strtolower(trim($data['email']));

            // Check duplicate
            $exists = Subscriber::where('partner_firebase_id', $this->partnerFirebaseId)
                ->where('email', $email)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $duplicates++;
                continue;
            }

            // Check max subscribers limit
            if ($agreement && $agreement->max_subscribers) {
                $currentCount = Subscriber::where('agreement_id', $agreement->id)
                    ->whereNull('deleted_at')
                    ->count();

                if ($currentCount >= $agreement->max_subscribers) {
                    $errors++;
                    $errorDetails[] = [
                        'row' => $rowNum,
                        'email' => $email,
                        'error' => 'Maximum subscriber limit reached',
                    ];
                    break; // Stop processing
                }
            }

            // Create subscriber — delegate to SubscriberService to get all the
            // SOS-Call logic (code generation, expires_at cascade, audit log)
            // for free, whether the partner uses the commission model (A) or
            // the B2B flat-fee model (B with sos_call_active=true).
            $tags = [];
            if (!empty($data['tags'] ?? '')) {
                $tags = array_map('trim', explode('|', $data['tags']));
            }

            $createData = [
                'agreement_id' => $agreement?->id,
                'email' => $email,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => !empty($data['country']) ? strtoupper(trim($data['country'])) : null,
                'language' => !empty($data['language']) ? strtolower(trim($data['language'])) : 'fr',
                'tags' => $tags,
            ];

            // Per-row expiration override for SOS-Call subscribers.
            // Supported formats: YYYY-MM-DD, YYYY-MM-DD HH:MM:SS.
            if (!empty($data['expires_at'])) {
                try {
                    $createData['expires_at'] = \Carbon\Carbon::parse($data['expires_at']);
                } catch (\Throwable $e) {
                    // If parsing fails, we fall back to agreement defaults silently.
                }
            }

            // Hierarchy columns (optional)
            foreach (['group_label', 'region', 'department', 'external_id'] as $h) {
                if (!empty($data[$h])) {
                    $createData[$h] = trim((string) $data[$h]);
                }
            }

            $subscriberService = app(\App\Services\SubscriberService::class);
            try {
                // SubscriberService internally dispatches SyncSubscriberToFirestore + emails.
                $subscriberService->create(
                    $this->partnerFirebaseId,
                    $createData,
                    'admin:csv_import',
                    'admin'
                );
            } catch (\Throwable $createErr) {
                $errors++;
                $errorDetails[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'error' => 'Create failed: ' . $createErr->getMessage(),
                ];
                continue;
            }

            $imported++;
        }

        fclose($handle);

        // Update import record
        $csvImport->update([
            'total_rows' => $rowNum - 1,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
            'error_details' => $errorDetails,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Cleanup temp file
        Storage::disk('local')->delete($this->filePath);

        Log::info('CSV import completed', [
            'import_id' => $this->csvImportId,
            'partner_id' => $this->partnerFirebaseId,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ]);
    }
}
