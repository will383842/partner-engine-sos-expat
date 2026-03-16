<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessCsvImport;
use App\Jobs\SendSubscriberInvitation;
use App\Jobs\SyncSubscriberToFirestore;
use App\Models\Agreement;
use App\Models\CsvImport;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessCsvImportTest extends TestCase
{
    private Agreement $agreement;
    private string $partnerId = 'partner_csv';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SendSubscriberInvitation::class, SyncSubscriberToFirestore::class]);
        Storage::fake('local');

        $this->agreement = Agreement::factory()->create(['partner_firebase_id' => $this->partnerId]);
    }

    private function createCsvFile(array $rows, string $filename = 'test.csv'): string
    {
        $header = "email,first_name,last_name,phone,country,language,tags\n";
        $content = $header . implode("\n", $rows);
        $path = "csv-imports/{$filename}";
        Storage::disk('local')->put($path, $content);
        return $path;
    }

    private function createImport(): CsvImport
    {
        return CsvImport::create([
            'partner_firebase_id' => $this->partnerId,
            'uploaded_by' => 'uploader_uid',
            'filename' => 'test.csv',
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function test_import_valid_csv(): void
    {
        $path = $this->createCsvFile([
            'alice@test.com,Alice,Smith,+33612345,FR,fr,vip|europe',
            'bob@test.com,Bob,Jones,,DE,de,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals('completed', $import->status);
        $this->assertEquals(2, $import->imported);
        $this->assertEquals(0, $import->duplicates);
        $this->assertEquals(0, $import->errors);

        $this->assertDatabaseHas('subscribers', ['email' => 'alice@test.com', 'country' => 'FR']);
        $this->assertDatabaseHas('subscribers', ['email' => 'bob@test.com', 'country' => 'DE']);

        Queue::assertPushed(SyncSubscriberToFirestore::class, 2);
        Queue::assertPushed(SendSubscriberInvitation::class, 2);
    }

    public function test_import_handles_duplicates(): void
    {
        // Pre-existing subscriber
        Subscriber::factory()->forAgreement($this->agreement)->create(['email' => 'existing@test.com']);

        $path = $this->createCsvFile([
            'existing@test.com,Existing,User,,,fr,',
            'new@test.com,New,User,,,fr,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals(1, $import->imported);
        $this->assertEquals(1, $import->duplicates);
    }

    public function test_import_validates_emails(): void
    {
        $path = $this->createCsvFile([
            'not-an-email,Bad,User,,,fr,',
            'good@test.com,Good,User,,,fr,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals(1, $import->imported);
        $this->assertEquals(1, $import->errors);
        $this->assertNotEmpty($import->error_details);
    }

    public function test_import_validates_country_code(): void
    {
        $path = $this->createCsvFile([
            'bad@test.com,Test,User,,FRANCE,fr,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals(0, $import->imported);
        $this->assertEquals(1, $import->errors);
    }

    public function test_import_respects_max_subscribers_limit(): void
    {
        $this->agreement->update(['max_subscribers' => 1]);

        $path = $this->createCsvFile([
            'first@test.com,First,User,,,fr,',
            'second@test.com,Second,User,,,fr,',
            'third@test.com,Third,User,,,fr,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals(1, $import->imported);
        // Rest should be errors (limit reached)
        $this->assertGreaterThan(0, $import->errors);
    }

    public function test_import_skips_empty_rows(): void
    {
        $path = $this->createCsvFile([
            'valid@test.com,Valid,User,,,fr,',
            ',,,,,,,', // empty row
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $import->refresh();
        $this->assertEquals(1, $import->imported);
    }

    public function test_import_cleans_up_file(): void
    {
        $path = $this->createCsvFile([
            'cleanup@test.com,Clean,User,,,fr,',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_import_handles_missing_file(): void
    {
        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, 'csv-imports/nonexistent.csv');
        $job->handle();

        $import->refresh();
        $this->assertEquals('failed', $import->status);
    }

    public function test_import_parses_tags(): void
    {
        $path = $this->createCsvFile([
            'tagged@test.com,Tag,User,,,fr,vip|premium|europe',
        ]);

        $import = $this->createImport();

        $job = new ProcessCsvImport($import->id, $this->partnerId, $path);
        $job->handle();

        $subscriber = Subscriber::where('email', 'tagged@test.com')->first();
        $this->assertIsArray($subscriber->tags);
        $this->assertCount(3, $subscriber->tags);
        $this->assertContains('vip', $subscriber->tags);
    }
}
