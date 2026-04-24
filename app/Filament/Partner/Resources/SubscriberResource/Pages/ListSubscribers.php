<?php

namespace App\Filament\Partner\Resources\SubscriberResource\Pages;

use App\Filament\Partner\Resources\SubscriberResource;
use App\Models\Agreement;
use App\Models\Subscriber;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Ajouter un client'),

            Actions\Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Fichier CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('Colonnes attendues (1 ligne par client) : email, first_name, last_name, phone, country, language, group_label, region, department, external_id. Seul email est obligatoire.')
                        ->disk('local')
                        ->directory('tmp-csv-uploads'),
                    Forms\Components\Toggle::make('skip_duplicates')
                        ->label('Ignorer les doublons d\'email')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    $partnerId = $user->partner_firebase_id;
                    $agreement = Agreement::where('partner_firebase_id', $partnerId)->first();
                    if (!$agreement) {
                        Notification::make()->danger()->title('Aucun contrat actif')->send();
                        return;
                    }

                    $path = storage_path('app/' . $data['file']);
                    if (!file_exists($path)) {
                        Notification::make()->danger()->title('Fichier introuvable')->send();
                        return;
                    }

                    $csv = array_map('str_getcsv', file($path));
                    if (count($csv) < 2) {
                        Notification::make()->warning()->title('Fichier vide')->send();
                        @unlink($path);
                        return;
                    }

                    $header = array_map('strtolower', array_map('trim', array_shift($csv)));
                    $emailIdx = array_search('email', $header);
                    if ($emailIdx === false) {
                        Notification::make()->danger()->title('Colonne "email" manquante')->send();
                        @unlink($path);
                        return;
                    }

                    $skipDuplicates = (bool) ($data['skip_duplicates'] ?? true);
                    $existing = Subscriber::where('partner_firebase_id', $partnerId)
                        ->pluck('email')->map(fn($e) => strtolower($e))->toArray();

                    $imported = 0; $skipped = 0; $errors = 0;
                    DB::transaction(function () use (
                        $csv, $header, $emailIdx, $partnerId, $agreement,
                        $existing, $skipDuplicates, &$imported, &$skipped, &$errors
                    ) {
                        foreach ($csv as $row) {
                            $email = strtolower(trim($row[$emailIdx] ?? ''));
                            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $errors++;
                                continue;
                            }
                            if ($skipDuplicates && in_array($email, $existing, true)) {
                                $skipped++;
                                continue;
                            }

                            $getCol = fn($name) => ($idx = array_search($name, $header)) !== false
                                ? trim($row[$idx] ?? '') : null;

                            try {
                                Subscriber::create([
                                    'partner_firebase_id' => $partnerId,
                                    'agreement_id' => $agreement->id,
                                    'email' => $email,
                                    'first_name' => $getCol('first_name') ?: null,
                                    'last_name' => $getCol('last_name') ?: null,
                                    'phone' => $getCol('phone') ?: null,
                                    'country' => $getCol('country') ?: null,
                                    'language' => $getCol('language') ?: 'fr',
                                    'group_label' => $getCol('group_label') ?: null,
                                    'region' => $getCol('region') ?: null,
                                    'department' => $getCol('department') ?: null,
                                    'external_id' => $getCol('external_id') ?: null,
                                    'status' => 'active',
                                    'invite_token' => Str::random(64),
                                    'invited_at' => now(),
                                    'tags' => [],
                                    'custom_fields' => [],
                                ]);
                                $imported++;
                                $existing[] = $email;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                        }
                    });

                    @unlink($path);

                    Notification::make()
                        ->success()
                        ->title("Import terminé")
                        ->body("Importés : {$imported} · Ignorés (doublons) : {$skipped} · Erreurs : {$errors}")
                        ->send();
                }),
        ];
    }
}
