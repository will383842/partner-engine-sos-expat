<?php

/**
 * Partner Filament panel translations (FR).
 *
 * Convention:
 *   nav.*         — navigation groups + labels
 *   resource.*    — Filament Resources (Subscriber / Invoice / Activity)
 *   page.*        — custom Pages (Hierarchy, MyAccount)
 *   widget.*      — Dashboard widgets
 *   common.*      — shared strings (actions, status badges, etc.)
 *
 * Every string referenced via __('panel.xxx') in the PHP / Blade files
 * lives here. Mirror structure in lang/en/panel.php so the toggle can
 * swap the full UI.
 */
return [

    // ===== Panel branding =====
    'brand_name' => 'SOS-Expat · Espace partenaire',

    // ===== Navigation groups =====
    'nav' => [
        'group_pilotage'     => 'Pilotage',
        'group_clients'      => 'Gestion clients',
        'group_billing'      => 'Facturation',
        'group_account'      => 'Mon compte',

        'dashboard'          => 'Tableau de bord',
        'subscribers'        => 'Mes clients',
        'activity'           => 'Activité SOS-Call',
        'hierarchy'          => 'Cabinets & régions',
        'invoices'           => 'Mes factures',
        'my_account'         => 'Mon contrat',
    ],

    // ===== Shared strings =====
    'common' => [
        'save'           => 'Enregistrer',
        'cancel'         => 'Annuler',
        'confirm'        => 'Confirmer',
        'edit'           => 'Éditer',
        'view'           => 'Voir',
        'delete'         => 'Supprimer',
        'export_csv'     => 'Exporter en CSV',
        'import_csv'     => 'Import CSV',
        'download_pdf'   => 'Télécharger le PDF',
        'pay_online'     => 'Payer en ligne',
        'back'           => 'Retour',
        'dash'           => '—',
        'no_limit'       => 'Sans limite',
        'unlimited'      => 'Illimité',
        'yes'            => 'Oui',
        'no'             => 'Non',
        'status'         => 'Statut',
        'active'         => 'Actif',
        'suspended'      => 'Suspendu',
        'invited'        => 'Invité',
        'paid'           => 'Payée',
        'pending'        => 'En attente',
        'overdue'        => 'En retard',
        'cancelled'      => 'Annulée',
        'expert'         => 'Expert',
        'lawyer'         => 'Avocat',
        'expert_expat'   => 'Expert expat',
        'lawyer_local'   => 'Avocat local',
        'copy_code'      => 'Code copié',
        'copy_email'     => 'Email copié',
        'copy_invoice'   => 'Numéro copié',
    ],

    // ===== Subscriber resource =====
    'subscriber' => [
        'model_label'           => 'Client',
        'plural_label'          => 'Clients',

        'section_info'          => 'Informations du client',
        'section_hierarchy'     => 'Hiérarchie (organisation interne)',
        'section_hierarchy_desc'=> 'Segmentation libre : cabinet, région, département. Utilisée pour les rapports drill-down.',
        'section_access'        => 'Accès SOS-Call',

        'first_name'            => 'Prénom',
        'last_name'             => 'Nom',
        'full_name'             => 'Client',
        'email'                 => 'Email',
        'phone'                 => 'Téléphone',
        'phone_hint'            => 'Format +33 6 12 34 56 78',
        'country'               => 'Pays',
        'language'              => 'Langue',
        'group_label'           => 'Cabinet / Unité',
        'group_label_placeholder' => 'Ex : Paris, Lyon, Direction',
        'region'                => 'Région',
        'region_placeholder'    => 'Ex : Île-de-France',
        'department'            => 'Département / Service',
        'department_placeholder'=> 'Ex : IT, RH, Commercial',
        'external_id'           => 'Référence interne',
        'external_id_placeholder' => 'Ex : ID dans votre CRM',
        'sos_code'              => 'Code SOS-Call',
        'calls_total'           => 'Appels',
        'added_at'              => 'Ajouté le',
        'expires_at'            => 'Expire le',
        'expires_hint'          => 'Laisser vide = aucune limite (accès valable tant que le contrat est actif).',

        'action_add'            => 'Ajouter un client',
        'action_suspend'        => 'Suspendre',
        'action_reactivate'     => 'Réactiver',
        'action_assign_cabinet' => 'Assigner un cabinet',
        'action_assign_region'  => 'Assigner une région',
        'action_extend'         => "Prolonger l'accès",

        'filter_cabinet'        => 'Cabinet',

        'import_csv_title'      => 'Import CSV',
        'import_csv_help'       => "Colonnes attendues (1 ligne par client) : email, first_name, last_name, phone, country, language, group_label, region, department, external_id. Seul email est obligatoire.",
        'import_csv_skip_dup'   => "Ignorer les doublons d'email",
        'import_no_agreement'   => 'Aucun contrat actif',
        'import_file_missing'   => 'Fichier introuvable',
        'import_empty'          => 'Fichier vide',
        'import_missing_email'  => 'Colonne "email" manquante',
        'import_done_title'     => 'Import terminé',
        'import_done_body'      => 'Importés : :imported · Ignorés (doublons) : :skipped · Erreurs : :errors',

        'extend_duration'       => 'Durée supplémentaire',
        'extend_30d'            => '+ 30 jours',
        'extend_90d'            => '+ 90 jours',
        'extend_180d'           => '+ 180 jours',
        'extend_365d'           => '+ 1 an',
    ],

    // ===== Invoice resource =====
    'invoice' => [
        'model_label'           => 'Facture',
        'plural_label'          => 'Factures',
        'number'                => 'N° facture',
        'number_short'          => 'N°',
        'period'                => 'Période',
        'active_subscribers'    => 'Clients actifs sur la période',
        'active_subs_short'     => 'Clients',
        'billing_rate'          => 'Tarif par client',
        'monthly_base_fee'      => 'Forfait fixe mensuel',
        'amount'                => 'Montant',
        'amount_total'          => 'Montant total',
        'due_date'              => 'Échéance',
        'paid_at'               => 'Payée le',
        'paid_via'              => 'Moyen de paiement',
        'section_details'       => 'Détail consommation',
        'calls_expert'          => 'Appels Expert expat',
        'calls_lawyer'          => 'Appels Avocat local',
        'filter_status'         => 'Statut',
    ],

    // ===== Activity resource =====
    'activity' => [
        'model_label'           => 'Appel',
        'plural_label'          => 'Appels',
        'date'                  => 'Date',
        'client'                => 'Client',
        'cabinet'               => 'Cabinet',
        'code'                  => 'Code',
        'type'                  => 'Type',
        'duration'              => 'Durée',
        'country'               => 'Pays',
        'action_detail'         => 'Détail',
        'filter_type'           => 'Type',
        'filter_period'         => 'Période',
        'filter_from'           => 'Du',
        'filter_to'             => 'Au',
        'duration_minutes'      => ':m min',
    ],

    // ===== Hierarchy page =====
    'hierarchy' => [
        'title'                 => 'Hiérarchie : cabinets, régions, départements',
        'group_by'              => 'Grouper par :',
        'dim_cabinet'           => 'Cabinet / Unité',
        'dim_region'            => 'Région',
        'dim_department'        => 'Département',
        'col_name'              => 'Nom',
        'col_email'             => 'Email',
        'col_phone'             => 'Téléphone',
        'col_code'              => 'Code',
        'col_calls_month'       => 'Appels (mois)',
        'col_status'            => 'Statut',
        'col_actions'           => '',
        'col_total_clients'     => 'Total clients',
        'col_active_clients'    => 'Clients actifs',
        'col_usage_pct'         => "Taux d'usage",
        'col_est_invoice'       => 'Facture estimée',
        'flat_fee_notice'       => 'Forfait fixe mensuel partenaire : :amount',
        'flat_fee_explanation'  => "Ce forfait s'applique au niveau du contrat partenaire et s'ajoute aux estimations par groupe ci-dessous (qui correspondent au tarif par client × clients actifs).",
        'total_estimated'       => 'Estimation totale du mois : :amount',
        'back_to_list'          => '← Retour à la liste',
        'clients_count'         => ':count clients',
        'unassigned'            => '— Non renseigné —',
        'unassigned_badge'      => 'À renseigner',
        'empty_no_hierarchy'    => "Aucun client n'est assigné à un :dimension pour l'instant.",
        'empty_hint'            => 'Pour y remédier, édite tes clients depuis "Mes clients" et renseigne les champs hiérarchie.',
        'empty_drill'           => 'Aucun client dans ce groupe.',
        'see_clients'           => 'Voir les clients →',
        'edit'                  => 'Éditer',
        'footer_hint'           => '💡 Pour créer une hiérarchie, ouvre la fiche d\'un client dans **Mes clients** et renseigne les champs *Cabinet*, *Région* et *Département*. Les agrégations se mettent à jour ici en temps réel.',
    ],

    // ===== MyAccount page =====
    'my_account' => [
        'title'                 => 'Mon contrat SOS-Expat',
        'no_agreement'          => "Aucun contrat n'est associé à votre compte. Contactez votre interlocuteur SOS-Expat.",
        'partner_company'       => 'Entreprise partenaire',
        'status_active'         => 'Contrat actif',
        'status_paused'         => 'Suspendu',
        'status_expired'        => 'Expiré',
        'status_draft'          => 'En préparation',
        'economic_model'        => 'Modèle économique',
        'model_commission'      => "Commission à l'acte",
        'model_sos_call'        => 'SOS-Call — Forfait mensuel',
        'model_hybrid'          => 'Hybride',
        'rate_per_client'       => 'Tarif par client actif',
        'rate_suffix'           => '/ mois',
        'call_types'            => "Types d'appels autorisés",
        'call_types_both'       => 'Expert + Avocat',
        'call_types_expat'      => 'Expert seulement',
        'call_types_lawyer'     => 'Avocat seulement',
        'payment_terms'         => 'Délai de paiement',
        'payment_terms_days'    => ':days jours',
        'quotas_section'        => 'Quotas & limites',
        'active_clients_label'  => 'Clients actifs',
        'calls_per_client'      => 'Appels par client',
        'default_access_duration' => "Durée d'accès client (par défaut)",
        'duration_days'         => ':days jours',
        'duration_permanent'    => 'Permanent',
        'contract_dates'        => 'Dates contractuelles',
        'contract_start'        => 'Début du contrat',
        'contract_end'          => 'Fin du contrat',
        'contract_end_none'     => 'Sans date de fin',
        'contact_note'          => 'Pour modifier votre contrat, vos quotas ou votre interlocuteur de facturation, contactez votre chargé de compte SOS-Expat.',
    ],

    // ===== Widgets =====
    'widget' => [
        'stats' => [
            'active_clients'        => 'Clients actifs',
            'active_clients_desc'   => 'Base couverte ce mois-ci',
            'expert_calls'          => 'Appels Expert (mois)',
            'lawyer_calls'          => 'Appels Avocat (mois)',
            'estimated_invoice'     => 'Facture estimée',
            'avg_duration'          => 'Durée moyenne appel',
            'avg_duration_desc'     => 'Sur :count appel(s) ce mois',
            'usage_rate'            => "Taux d'usage",
            'usage_rate_desc'       => 'Appels / clients actifs ce mois',
            'top_country'           => "Top pays d'intervention",
            'top_country_desc'      => 'Pays le plus sollicité ce mois',
            'invoices_todo'         => 'Factures à traiter',
            'invoices_overdue'      => ':count en retard — à régler vite',
            'invoices_pending'      => 'En attente de paiement',
            'invoices_ok'           => 'Tout est à jour',
            'delta_vs'              => 'vs :label',
            'delta_label_last'      => 'mois-1',
            'delta_equal'           => '= :label',
            'minutes'               => ':m min',
        ],
        'revenue' => [
            'heading'               => 'Évolution des appels sur 12 mois',
        ],
        'top_countries' => [
            'heading'               => "Top 10 pays d'intervention (ce mois)",
            'series_label'          => 'Appels',
        ],
        'invoice_status' => [
            'heading'               => 'Statut des factures',
        ],
        'provider_split' => [
            'heading'               => 'Répartition Expert / Avocat (12 mois)',
        ],
        'top_subscribers' => [
            'heading'               => 'Top 10 clients les plus actifs (ce mois)',
            'col_client'            => 'Client',
            'col_cabinet'           => 'Cabinet',
            'col_code'              => 'Code',
            'col_calls_month'       => 'Appels ce mois',
            'empty'                 => 'Aucun appel enregistré ce mois',
        ],
    ],

];
