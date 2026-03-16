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

            // Validate
            $validator = Validator::make($data, [
                'email' => 'required|email|max:255',
                'first_name' => 'nullable|string|max:100',
                'last_name' => 'nullable|string|max:100',
                'phone' => 'nullable|string|max:50',
                'country' => 'nullable|string|max:2',
                'language' => 'nullable|string|max:5',
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

            // Create subscriber
            $inviteToken = Str::random(64);
            $tags = [];
            if (!empty($data['tags'] ?? '')) {
                $tags = array_map('trim', explode('|', $data['tags']));
            }

            $subscriber = Subscriber::create([
                'partner_firebase_id' => $this->partnerFirebaseId,
                'agreement_id' => $agreement?->id,
                'email' => $email,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'country' => !empty($data['country']) ? strtoupper(trim($data['country'])) : null,
                'language' => !empty($data['language']) ? strtolower(trim($data['language'])) : 'fr',
                'invite_token' => $inviteToken,
                'status' => 'invited',
                'invited_at' => now(),
                'tags' => $tags,
            ]);

            // Dispatch Firestore sync + invitation email
            SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
            SendSubscriberInvitation::dispatch($subscriber);

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
