<?php

namespace App\Observers;

use App\Models\Agreement;
use App\Models\Subscriber;
use App\Jobs\SyncSubscriberToFirestore;

class AgreementObserver
{
    /**
     * Block sos_call_active=true unless the partner has signed all required
     * legal documents (or admin set legal_override). The check runs BEFORE
     * the row is persisted; throwing here aborts the save and bubbles up
     * as a Filament validation error.
     */
    public function saving(Agreement $agreement): void
    {
        if (
            $agreement->isDirty('sos_call_active')
            && $agreement->sos_call_active === true
            && !$agreement->isLegallyCleared()
        ) {
            throw new \RuntimeException(
                'Impossible d\'activer SOS-Call : le partenaire n\'a pas encore signé '
                . 'les documents légaux requis (CGV B2B, DPA, Bon de commande). '
                . 'Allez dans l\'onglet "Documents légaux" → "Générer les 3 documents légaux" '
                . '→ "Valider tous & envoyer pour signature". Ou activez l\'override légal '
                . 'si un contrat papier a été signé.'
            );
        }
    }

    /**
     * When agreement status changes, sync all linked subscribers to Firestore.
     * Note: Audit logging is handled in AgreementService, not here.
     */
    public function updated(Agreement $agreement): void
    {
        if ($agreement->wasChanged('status')) {
            $subscribers = Subscriber::where('agreement_id', $agreement->id)
                ->whereNull('deleted_at')
                ->get();

            foreach ($subscribers as $subscriber) {
                SyncSubscriberToFirestore::dispatch($subscriber, 'upsert');
            }
        }
    }
}
