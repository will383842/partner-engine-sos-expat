<?php

namespace Database\Seeders;

use App\Models\LegalDocumentTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\View;

/**
 * Seeds the 3 initial legal document templates (CGV B2B, DPA, Order Form)
 * in French. The Blade default views in resources/views/legal/body/* are
 * the source of truth — this seeder simply registers an empty template row
 * so the LegalDocumentService can attribute a version number to generated
 * documents. The actual content is rendered from the Blade view at draft
 * generation time.
 *
 * Run via:
 *   php artisan db:seed --class=Database\\Seeders\\LegalDocumentTemplateSeeder
 */
class LegalDocumentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $kinds = [
            LegalDocumentTemplate::KIND_CGV_B2B => [
                'fr' => 'Conditions Générales de Vente B2B SOS-Call',
                'en' => 'B2B SOS-Call Terms of Service',
            ],
            LegalDocumentTemplate::KIND_DPA => [
                'fr' => 'Accord de traitement de données (DPA / RGPD art. 28)',
                'en' => 'Data Processing Agreement (DPA / GDPR art. 28)',
            ],
            LegalDocumentTemplate::KIND_ORDER_FORM => [
                'fr' => 'Bon de commande SOS-Call',
                'en' => 'SOS-Call Order Form',
            ],
        ];

        foreach ($kinds as $kind => $titles) {
            foreach ($titles as $lang => $title) {
                $exists = LegalDocumentTemplate::where('kind', $kind)
                    ->where('language', $lang)
                    ->where('version', '1.0.0')
                    ->exists();
                if ($exists) continue;

                LegalDocumentTemplate::create([
                    'kind' => $kind,
                    'language' => $lang,
                    'version' => '1.0.0',
                    'title' => $title,
                    // Empty body_html → LegalDocumentService falls back to the
                    // default Blade view (resources/views/legal/body/{kind}.blade.php).
                    'body_html' => '',
                    'variables' => [
                        'partner_name', 'billing_rate', 'billing_currency',
                        'monthly_base_fee', 'payment_terms_days', 'starts_at', 'expires_at',
                    ],
                    'is_published' => true,
                    'published_at' => now(),
                    'published_by' => 'seeder',
                    'change_notes' => 'Version initiale — fournie par seeder.',
                ]);
            }
        }
    }
}
