<?php

/**
 * Admin Filament panel translations (FR).
 *
 * Convention:
 *   nav.*           — navigation groups + labels
 *   common.*        — shared strings (status, actions, currencies)
 *   partner.*       — PartnerResource
 *   subscriber.*    — SubscriberResource (admin view)
 *   invoice.*       — PartnerInvoiceResource (admin view)
 *   email_template.* — EmailTemplateResource
 *   api_key.*       — PartnerApiKeyResource
 *   audit.*         — AuditLogResource
 *   user.*          — UserResource (admin users)
 *   widget.*        — Dashboard widgets
 *
 * Mirror structure in lang/en/admin.php so the toggle swaps the full UI.
 */
return [

    // ===== Panel branding =====
    'brand_name' => 'SOS-Expat Admin',

    // ===== Navigation =====
    'nav' => [
        'group_dashboard'   => 'Tableau de bord',
        'group_partners'    => 'Partenaires',
        'group_billing'     => 'Facturation SOS-Call',
        'group_monitoring'  => 'Surveillance',
        'group_config'      => 'Configuration',

        'partners'          => 'Partenaires',
        'subscribers'       => 'Clients (Subscribers)',
        'invoices'          => 'Factures',
        'email_templates'   => 'Templates email',
        'api_keys'          => 'Clés API partenaires',
        'audit'             => 'Audit logs',
        'users'             => 'Utilisateurs admin',
        'group_legal'       => 'Légal',
    ],

    // ===== Shared =====
    'common' => [
        'save'              => 'Enregistrer',
        'cancel'            => 'Annuler',
        'confirm'           => 'Confirmer',
        'edit'              => 'Éditer',
        'view'              => 'Voir',
        'detail'            => 'Détail',
        'delete'            => 'Supprimer',
        'create'            => 'Créer',
        'export_csv'        => 'Exporter en CSV',
        'download_pdf'      => 'Télécharger PDF',
        'copy_code'         => 'Code copié',
        'copy_email'        => 'Email copié',
        'copy_invoice'      => 'Numéro copié',
        'close'             => 'Fermer',
        'dash'              => '—',
        'never'             => 'Jamais',
        'global'            => '🌐 Global',
        'permanent'         => 'Permanent',
        'unlimited'         => 'Illimité',

        // status
        'status'            => 'Statut',
        'active'            => 'Actif',
        'paused'            => 'Suspendu',
        'expired'           => 'Expiré',
        'invited'           => 'Invité',
        'suspended'         => 'Suspendu',
        'paid'              => 'Payée',
        'pending'           => 'En attente',
        'overdue'           => 'En retard',
        'cancelled'         => 'Annulée',
        'draft'             => 'Brouillon',

        // call types
        'expert'            => 'Expert',
        'lawyer'            => 'Avocat',
        'expert_expat'      => 'Expert expat',
        'lawyer_local'      => 'Avocat local',

        // languages (used in selects)
        'lang_fr'           => 'Français',
        'lang_en'           => 'English',
        'lang_es'           => 'Español',
        'lang_de'           => 'Deutsch',
        'lang_pt'           => 'Português',
        'lang_ar'           => 'العربية',
        'lang_zh'           => '中文',
        'lang_ru'           => 'Русский',
        'lang_hi'           => 'हिन्दी',

        // payment methods
        'pay_stripe'        => 'Stripe',
        'pay_sepa'          => 'Virement SEPA',
        'pay_manual'        => 'Manuel',
    ],

    // ===== Partner resource =====
    'partner' => [
        'model_label'       => 'Partenaire',
        'plural_label'      => 'Partenaires',

        // Wizard steps
        'wizard_general'    => 'Informations générales',
        'wizard_status'     => 'Statut et dates',
        'wizard_economic'   => 'Modèle économique',
        'wizard_economic_desc' => 'Choisissez UN modèle — les champs inutiles sont automatiquement remis à zéro.',
        'wizard_quotas'     => 'Limites et quotas',

        // Fields
        'partner_name'      => 'Nom du partenaire',
        'legal_status'      => 'Statut légal',
        'firebase_id'       => 'Firebase ID',
        'firebase_id_hint'  => 'Identifiant unique utilisé par les intégrations Firebase',
        'agreement_name'    => "Nom interne de l'accord",
        'billing_email'     => 'Email facturation',
        'billing_email_short' => 'Email fact.',
        'starts_at'         => 'Date de début',
        'expires_at'        => "Date d'expiration (optionnel)",
        'expires_short'     => 'Expire le',
        'created_at'        => 'Créé le',
        'notes'             => 'Notes internes',

        // Economic model
        'economic_model'    => 'Modèle économique',
        'economic_model_filter' => 'Modèle économique',
        'economic_model_apply' => 'Modèle économique appliqué à ce partenaire',
        'model_commission'  => 'Commission',
        'model_commission_long' => "💱 Commission à l'acte — partenaire touche X par appel, client paye le prix standard (19€/49€)",
        'model_sos_call'    => 'SOS-Call',
        'model_sos_call_long' => '💰 SOS-Call forfait mensuel — partenaire paye X€/client/mois, client appelle gratuitement',
        'model_hybrid'      => 'Hybride',
        'model_hybrid_long' => '⚠️ Hybride (rare) — forfait mensuel + commission par appel en plus',
        'filter_commission' => "Commission à l'acte",
        'filter_sos_call'   => 'SOS-Call forfait',

        // Commission section
        'section_commission' => "Paramètres commission à l'acte",
        'section_commission_desc' => "S'applique sur chaque appel payé par le client final",
        'commission_lawyer' => 'Commission avocat (cents)',
        'commission_lawyer_hint' => 'En centimes — ex: 300 = 3€',
        'commission_expat'  => 'Commission expert (cents)',
        'commission_type'   => 'Type de commission',
        'commission_fixed'  => 'Fixe par appel',
        'commission_percent' => 'Pourcentage',
        'commission_percent_label' => 'Commission %',

        // SOS-Call section
        'section_sos_call'  => 'Paramètres SOS-Call forfait mensuel',
        'section_sos_call_desc' => 'Le partenaire est facturé chaque mois. Ses clients appellent gratuitement via sos-call.sos-expat.com',
        'billing_rate'      => 'Tarif mensuel par client actif',
        'billing_rate_hint' => 'Mettre à 0 pour facturer uniquement un forfait fixe mensuel.',
        'billing_rate_short' => 'Tarif/mois',
        'monthly_base_fee'  => 'Forfait fixe mensuel (optionnel)',
        'monthly_base_fee_hint' => 'Montant fixe facturé chaque mois, indépendamment du nombre de clients. Laisser vide ou à 0 pour facturer uniquement par client. Combiné avec le tarif par client = modèle hybride.',
        'pricing_tiers'     => 'Forfait par paliers de clients (optionnel, prioritaire sur le forfait fixe)',
        'pricing_tiers_hint' => 'Définissez des paliers tarifaires basés sur le nombre de clients actifs. Exemple : 0–500 clients = 500€/mois, 501–1000 = 650€, 1001–5000 = 800€. Le palier qui contient le nombre de clients du mois remplace le forfait fixe ci-dessus. Le tarif par client (s\'il est > 0) s\'ajoute en plus.',
        'tier_min'          => 'À partir de (clients)',
        'tier_max'          => 'Jusqu\'à (clients)',
        'tier_max_unlimited' => 'illimité',
        'tier_max_hint'     => 'Laisser vide pour le dernier palier sans limite supérieure',
        'tier_amount'       => 'Montant mensuel',
        'tier_add'          => 'Ajouter un palier',
        'billing_currency'  => 'Devise de facturation',
        'payment_terms'     => 'Délai de paiement (jours)',
        'call_types'        => "Types d'appels autorisés",
        'call_types_both'   => 'Expert + Avocat',
        'call_types_expat'  => 'Expert seulement',
        'call_types_lawyer' => 'Avocat seulement',

        // Quotas
        'max_subscribers'   => 'Max subscribers (0 = illimité)',
        'max_calls_per_sub' => 'Max appels / subscriber (0 = illimité)',
        'default_duration'  => 'Durée par défaut subscriber (jours)',
        'default_duration_hint' => 'Nombre de jours avant expiration — laissez vide pour durée illimitée',
        'max_duration'      => 'Durée max subscriber (jours)',

        // Table
        'col_clients'       => 'Clients',

        // Actions
        'action_toggle_to_sos'      => 'Basculer en SOS-Call',
        'action_toggle_to_commission' => 'Basculer en Commission',
        'toggle_to_sos_desc'        => "Ce partenaire passera du modèle Commission (paiement à l'acte) au modèle SOS-Call (forfait mensuel). Les tarifs commission seront remis à 0.",
        'toggle_to_commission_desc' => 'Ce partenaire repassera en modèle Commission. Le forfait mensuel SOS-Call sera désactivé.',

        'action_suspend_all'    => 'Suspendre tous les clients',
        'suspend_all_heading'   => 'Suspendre tous les clients de ce partenaire ?',
        'suspend_all_desc'      => "ATTENTION : cette action va bloquer l'accès SOS-Call pour tous les clients actifs de « :partner ». À utiliser uniquement après plusieurs relances sans paiement. Pour les gros comptes B2B, privilégier un contact commercial direct d'abord.",
        'suspend_all_submit'    => 'Confirmer la suspension',
        'suspend_reason'        => 'Raison de la suspension (log audit)',
        'suspend_reason_placeholder' => 'Ex: facture SOS-202604-0001 impayée depuis 45 jours, 3 relances sans réponse',
        'suspend_done_title'    => ':count clients suspendus',
        'suspend_done_body'     => 'Partenaire: :partner',

        'action_reactivate_all' => 'Réactiver tous les clients',
        'reactivate_all_heading' => 'Réactiver tous les clients de ce partenaire ?',
        'reactivate_all_desc'   => "Rend à nouveau opérationnel l'accès SOS-Call pour tous les clients suspendus de ce partenaire.",
        'reactivate_done_title' => ':count clients réactivés',
    ],

    // ===== Subscriber resource (admin) =====
    'subscriber' => [
        'model_label'       => 'Client',
        'plural_label'      => 'Clients',
        'nav_label'         => 'Clients (Subscribers)',

        'section_partner'   => 'Partenaire',
        'section_profile'   => 'Profil',
        'section_hierarchy' => 'Hiérarchie (optionnel)',
        'section_hierarchy_desc' => 'Pour les gros partenaires avec cabinets, régions ou départements. Tous les champs sont libres — le partenaire définit sa propre segmentation.',
        'section_sos_call'  => 'SOS-Call',

        'agreement'         => 'Accord / Partenaire',
        'partner_firebase_id' => 'Firebase ID partenaire',
        'first_name'        => 'Prénom',
        'last_name'         => 'Nom',
        'email'             => 'Email',
        'phone'             => 'Téléphone (E.164)',
        'phone_short'       => 'Téléphone',
        'phone_hint'        => 'Format: +33612345678',
        'country'           => 'Pays (ISO)',
        'language'          => 'Langue',

        'group_label'       => 'Cabinet / Unité',
        'group_label_short' => 'Cabinet',
        'group_label_hint'  => 'Ex: "Paris", "Lyon", "Direction", "Cabinet Nord"',
        'region'            => 'Région',
        'region_hint'       => 'Ex: "Île-de-France", "APAC"',
        'department'        => 'Département / Service',
        'department_short'  => 'Dépt/Service',
        'department_hint'   => 'Ex: "IT", "RH", "Commercial"',
        'external_id'       => 'ID externe partenaire',
        'external_id_short' => 'ID externe',
        'external_id_hint'  => 'Identifiant dans le CRM du partenaire (optionnel)',

        'sos_call_code'     => 'Code SOS-Call',
        'sos_call_code_hint' => 'Format: PREFIX-YYYY-RANDOM5 (ex: XXX-2026-A3K9M)',
        'sos_call_activated_at' => 'Activé le',
        'sos_call_expires_at' => 'Expire le (optionnel)',
        'sos_call_expires_short' => 'Expire le',

        'calls_expert'      => 'Expert',
        'calls_lawyer'      => 'Avocat',
        'created_at'        => 'Créé le',

        'filter_has_code'   => 'Avec code SOS-Call',
        'action_suspend'    => 'Suspendre',
        'action_reactivate' => 'Réactiver',
    ],

    // ===== Invoice resource (admin) =====
    'invoice' => [
        'model_label'       => 'Facture',
        'plural_label'      => 'Factures',

        'section_id'        => 'Identification',
        'section_amounts'   => 'Montants',
        'section_status'    => 'Statut et paiement',
        'section_stripe'    => 'Stripe',

        'partner'           => 'Partenaire',
        'invoice_number'    => 'Numéro de facture',
        'invoice_number_short' => 'N° Facture',
        'invoice_number_col' => 'N°',
        'period'            => 'Période (YYYY-MM)',
        'period_short'      => 'Période',

        'active_subscribers' => 'Clients actifs',
        'active_subs_short' => 'Clients',
        'billing_rate'      => 'Tarif unitaire',
        'monthly_base_fee'  => 'Forfait fixe mensuel',
        'billing_currency'  => 'Devise',
        'total_amount'      => 'Montant total',
        'amount'            => 'Montant',

        'due_date'          => "Date d'échéance",
        'due_date_short'    => 'Échéance',
        'paid_at'           => 'Date de paiement',
        'paid_short'        => 'Payée le',
        'paid_via'          => 'Moyen de paiement',

        'stripe_id'         => 'Stripe Invoice ID',
        'stripe_url'        => 'URL Stripe hosted',

        'action_mark_paid'  => 'Marquer payée',
        'action_mark_paid_bulk' => 'Marquer payées (groupe)',
        'mark_paid_note'    => 'Note de paiement (optionnel)',
        'mark_paid_done'    => 'Facture marquée payée',
        'action_download_pdf' => 'Télécharger PDF',
    ],

    // ===== Email template resource =====
    'email_template' => [
        'model_label'       => 'Template email',
        'plural_label'      => 'Templates email',

        'section_id'        => 'Identification',
        'section_content'   => 'Contenu',

        'type'              => 'Type',
        'language'          => 'Langue',
        'partner_optional'  => 'Partenaire (vide = global)',
        'partner_optional_hint' => 'Laissez vide pour utiliser ce template comme défaut global',
        'is_active'         => 'Actif',
        'subject'           => 'Objet',
        'body_html'         => 'Corps HTML',
        'body_html_hint'    => 'Variables disponibles: {first_name}, {partner_name}, {sos_call_code}, {expires_at}, {invoice_number}, {total_amount}, etc.',
        'partner_col'       => 'Partenaire',
        'updated_at'        => 'Modifié le',

        // types
        'type_invitation'           => 'Invitation',
        'type_reminder'             => 'Rappel',
        'type_expiration'           => 'Expiration',
        'type_sos_call_activation'  => 'Activation SOS-Call',
        'type_monthly_invoice'      => 'Facture mensuelle',
        'type_invoice_overdue'      => 'Facture en retard',
        'type_subscriber_magic_link' => 'Magic link subscriber',
    ],

    // ===== API keys resource =====
    'api_key' => [
        'model_label'       => 'Clé API',
        'plural_label'      => 'Clés API',

        'partner'           => 'Partenaire',
        'name'              => 'Libellé (description interne)',
        'name_placeholder'  => 'ex: Production CRM integration',
        'scopes'            => 'Scopes autorisés',
        'scopes_hint'       => 'Permissions accordées à cette clé. Principe du moindre privilège.',
        'scope_subs_read'   => 'Lire les clients',
        'scope_subs_write'  => 'Créer / modifier / supprimer des clients',
        'scope_activity'    => "Lire l'activité",
        'scope_invoices'    => 'Lire les factures',
        'environment'       => 'Environnement',
        'env_live'          => 'Production (pk_live_…)',
        'env_test'          => 'Sandbox (pk_test_…)',

        // Table
        'col_prefix'        => 'Clé (préfixe)',
        'col_scopes'        => 'Scopes',
        'col_last_used'     => 'Dernière utilisation',
        'col_revoked'       => 'Révoquée',
        'col_created'       => 'Créée le',

        'action_revoke'     => 'Révoquer',
        'revoke_desc'       => 'Cette clé cessera immédiatement de fonctionner. Cette action est irréversible.',
        'revoked_done'      => 'Clé révoquée',
    ],

    // ===== Audit logs =====
    'audit' => [
        'model_label'       => 'Audit log',
        'plural_label'      => 'Audit logs',

        'date'              => 'Date',
        'role'              => 'Rôle',
        'actor'             => 'Acteur',
        'action'            => 'Action',
        'resource_type'     => 'Type ressource',
        'resource_id'       => 'ID ressource',
        'ip'                => 'IP',
        'action_partial'    => 'Action (partiel)',

        'role_super_admin'  => 'Super Admin',
        'role_admin'        => 'Admin',
        'role_accountant'   => 'Accountant',
        'role_support'      => 'Support',
        'role_partner'      => 'Partner',
        'role_system'       => 'System',
    ],

    // ===== Admin users =====
    'user' => [
        'model_label'       => 'Utilisateur',
        'plural_label'      => 'Utilisateurs',
        'nav_label'         => 'Utilisateurs admin',

        'section_identity'  => 'Identité',
        'section_access'    => 'Accès',

        'name'              => 'Nom complet',
        'name_short'        => 'Nom',
        'email'             => 'Email',
        'role'              => 'Rôle',
        'is_active'         => 'Compte actif',
        'is_active_short'   => 'Actif',
        'password'          => 'Mot de passe',
        'password_hint'     => 'Minimum 12 caractères. Laisser vide pour conserver le mot de passe actuel.',
        'last_login'        => 'Dernière connexion',
        'created_at'        => 'Créé le',

        'role_super_admin_long' => 'Super Admin (tout + impersonate + delete)',
        'role_admin_long'   => 'Admin (CRUD complet)',
        'role_accountant_long' => 'Comptable (factures + rapports)',
        'role_support_long' => 'Support (read + édit. limitée)',
        'role_super_admin'  => 'Super Admin',
        'role_admin'        => 'Admin',
        'role_accountant'   => 'Comptable',
        'role_support'      => 'Support',

        'action_activate'   => 'Activer',
        'action_deactivate' => 'Désactiver',
    ],

    // ===== Widgets =====
    // ===== Admin profile (EditAdminProfile page) =====
    'profile' => [
        'identity_section'        => 'Identité',
        'identity_section_desc'   => 'Votre nom et votre adresse e-mail.',
        'password_section'        => 'Mot de passe',
        'password_section_desc'   => 'Laissez vide pour conserver votre mot de passe actuel.',
        'security_section'        => 'Sécurité — Authentification à deux facteurs',
        'security_section_desc'   => 'Renforcez la sécurité de votre compte avec un code reçu par email à chaque connexion.',
        'two_factor_email_label'  => 'Activer la 2FA par email',
        'two_factor_email_help'   => 'Quand activée, un code à 6 chiffres vous est envoyé par email à chaque connexion. Le code expire après 10 minutes.',
    ],

    'widget' => [
        'stats' => [
            'active_partners'       => 'Partenaires actifs',
            'active_partners_desc'  => ':count en mode SOS-Call (forfait)',
            'active_subscribers'    => 'Clients actifs',
            'active_subscribers_desc' => 'Tous partenaires confondus',
            'revenue_mtd'           => 'Revenu du mois',
            'revenue_mtd_eur'       => 'Revenu du mois (EUR)',
            'revenue_mtd_usd'       => 'Revenu du mois (USD)',
            'revenue_first'         => 'Premier mois',
            'revenue_delta_up'      => '+:pct% vs mois dernier',
            'revenue_delta_down'    => ':pct% vs mois dernier',
            'unpaid_invoices'       => 'Factures impayées',
            'unpaid_invoices_eur'   => 'Factures impayées (EUR)',
            'unpaid_invoices_usd'   => 'Factures impayées (USD)',
            'overdue_count'         => ':count en retard',
            'calls_this_month'      => 'Appels ce mois',
            'calls_this_month_desc' => 'Tous SOS-Call confondus',
            'avg_invoice'           => 'Facture moyenne',
            'avg_invoice_desc'      => 'Ce mois-ci',
        ],
        'revenue' => [
            'heading'               => 'Revenus SOS-Call (12 derniers mois)',
            'series_paid'           => 'Payées',
            'series_pending'        => 'En attente',
        ],
        'top_partners' => [
            'heading'               => 'Top 10 partenaires par revenu (12 derniers mois)',
            'col_partner'           => 'Partenaire',
            'col_clients'           => 'Clients',
            'col_rate'              => 'Tarif/mois',
            'col_revenue_12m'       => 'Revenu 12 mois',
            'col_unpaid'            => 'Impayé',
            'col_contact'           => 'Contact',
        ],
        'pending' => [
            'heading'               => 'Factures en attente de paiement',
            'col_remaining'         => 'Reste',
            'days_remaining'        => ':days j',
            'overdue'               => 'Échu',
            'note_optional'         => 'Note (optionnel)',
        ],
        'overdue' => [
            'heading'               => 'Factures en retard',
            'col_delay'             => 'Retard',
            'days_overdue'          => ':days jours',
        ],
        'recent_calls' => [
            'heading'               => '20 derniers appels SOS-Call',
            'col_when'              => 'Quand',
            'col_partner'           => 'Partenaire',
            'col_client'            => 'Client',
            'col_code'              => 'Code',
            'col_type'              => 'Type',
            'col_duration'          => 'Durée',
            'minutes'               => ':m min',
            'lawyer_emoji'          => '⚖️ Avocat',
            'expert_emoji'          => '👤 Expert',
        ],
        'breakdown' => [
            'heading'               => 'Répartition du revenu par partenaire (12 mois)',
            'series_revenue'        => 'Revenu (€)',
            'empty'                 => 'Aucune facture payée',
        ],
        'holds' => [
            'unpaid_invoices'       => 'Factures impayées',
            'unpaid_invoices_eur'   => 'Factures impayées (EUR)',
            'unpaid_invoices_usd'   => 'Factures impayées (USD)',
            'unpaid_invoices_desc'  => 'Montant dû par partenaires',
            'calls_on_hold'         => 'Appels en hold',
            'calls_on_hold_desc'    => 'Providers en attente de paiement partenaire',
            'cost_to_release'       => 'Coût provider à débloquer',
            'cost_to_release_eur'   => 'Coût provider à débloquer (EUR)',
            'cost_to_release_usd'   => 'Coût provider à débloquer (USD)',
            'cost_to_release_desc'  => 'Montant provider libéré si factures payées',
        ],
    ],

    // ===== Legal documents (CGV B2B, DPA, Order Form) =====
    'legal' => [
        'templates_nav'         => 'Modèles légaux',
        'template_model_label'  => 'Modèle légal',
        'templates_plural_label' => 'Modèles légaux',

        'kind'                  => 'Type',
        'kind_cgv_b2b'          => 'CGV B2B',
        'kind_dpa'              => 'DPA RGPD',
        'kind_order_form'       => 'Bon de commande',
        'language'              => 'Langue',
        'version'               => 'Version',
        'version_hint'          => 'Format semver, ex : 1.0.0, 1.1.0, 2.0.0',
        'title'                 => 'Titre',
        'body_html'             => 'Contenu (HTML)',
        'body_html_hint'        => 'Variables disponibles : {{partner_name}}, {{billing_rate}}, {{billing_currency}}, {{starts_at}}, etc.',
        'change_notes'          => 'Notes de version (changelog)',
        'is_published'          => 'Publié',
        'is_published_hint'     => "Une fois publié, ce template devient la version active pour les nouveaux partenaires.",
        'published'             => 'Publié',
        'published_at'          => 'Date de publication',

        'section_meta'          => 'Type, langue, version',
        'section_content'       => 'Contenu',
        'section_publication'   => 'Publication',
        'preview'               => 'Aperçu',
    ],

];
