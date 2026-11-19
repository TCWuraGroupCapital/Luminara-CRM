<?php

return [
    'menu' => [
        'title' => 'Gourwest Global Hub',
    ],

    'acl' => [
        'title' => 'Gourwest Global Hub',
        'customers' => 'Unified 7-Country Profiles & Deduplication',
        'integrations' => '8-System Integration Hub & Replay',
        'loyalty' => 'Loyalty Tiers & Cross-Border Points',
        'sla' => 'Regional Customer Service & SLA Desk',
        'benchmark' => 'Service Warranty & <3s Benchmark',
    ],

    'index' => [
        'title' => 'Gourwest Global CRM & Integration Hub',
        'subtitle' => 'Unified Golden-Record profiles across 7 countries (AU, NZ, SG, MY, TH, UK, CA), real-time 8-system integration telemetry, loyalty ledger, regional SLA desk, and <3s warranty controls.',
        'seed-btn' => 'Seed Gourwest Global Dataset',
        'refresh-btn' => 'Refresh Telemetry',
        'retry-btn' => 'Retry',
        'load-error' => 'Unable to load Gourwest Global Hub telemetry. Please retry.',
        'empty-title' => 'No Gourwest Multi-Region Profiles Provisioned Yet',
        'empty-description' => 'Provision the 7-country customer profiles, 11 Brief stakeholders, 8 enterprise system contracts, cross-region duplicate pairs, and regional SLA tickets in one click.',
    ],

    'kpis' => [
        'golden-records' => 'Golden Customer Records',
        'loyalty-liability' => 'Unified Loyalty Points',
        'integrations-health' => 'Healthy System Contracts',
        'sla-latency' => 'Retrieval p95 & Open SLAs',
    ],

    'tabs' => [
        'customers' => 'Unified 7-Region Customers',
        'integrations' => '8-System Integration Hub',
        'sla' => 'Loyalty & Regional SLA Desk',
        'benchmark' => 'Warranty & <3s Benchmark',
    ],

    'customers' => [
        'all-regions' => 'All 7 Regions',
        'search-placeholder' => 'Search member ID, Golden UID, guest name, or outlet...',
        'duplicates-banner' => 'Cross-region duplicate clusters detected across regional silos',
        'deduplicate-btn' => 'Resolve Cross-Region Duplicates',
        'adjust-points-btn' => 'Adjust Points',
        'merged-badge' => 'Merged Secondary',
        'golden-badge' => 'Golden Primary',
    ],

    'integrations' => [
        'replay-btn' => 'Replay Dead-Letter Events',
        'sync-btn' => 'Sync Now',
        'recent-events' => 'Recent Cross-System Sync & Webhook Stream',
        'webhook-endpoint' => 'HMAC-SHA256 Webhook Endpoint',
    ],

    'sla' => [
        'new-ticket-btn' => 'Log Regional SLA Ticket',
        'resolve-btn' => 'Resolve Ticket',
        'resolved-label' => 'Resolved',
        'loyalty-modal-title' => 'Adjust Cross-Border Loyalty Points',
        'ticket-modal-title' => 'Log Regional Customer Service SLA Ticket',
        'points-delta' => 'Points Adjustment (+/-)',
        'reason' => 'Audit Reason',
        'region' => 'Jurisdiction / Region',
        'channel' => 'Service Channel',
        'category' => 'Complaint Category',
        'priority' => 'SLA Priority',
        'subject' => 'Subject',
        'summary' => 'Investigation Notes',
        'save-btn' => 'Save',
        'cancel-btn' => 'Cancel',
    ],

    'benchmark' => [
        'run-btn' => 'Run Live 25-Query <3s Benchmark',
        'sla-pass' => 'PASSES <= 3,000 ms WARRANTY',
        'sla-fail' => 'SLA BREACH (> 3,000 ms)',
        'index-audit-title' => '5,000,000-Record Composite Index & Readiness Audit',
        'privacy-matrix-title' => '7-Country Privacy Statute & Data Sovereignty Matrix',
    ],

    'messages' => [
        'seed-success' => 'Gourwest 7-country enterprise dataset seeded cleanly.',
        'deduplicate-success' => 'Consolidated :count cross-region duplicate profiles into Golden Records.',
        'sync-success' => 'Synchronized :system heartbeat and regional telemetry.',
        'replay-success' => 'Replayed :count dead-letter integration events cleanly.',
        'loyalty-adjusted' => 'Updated customer loyalty balance and recalculated tier.',
        'ticket-created' => 'Logged regional customer service SLA ticket.',
        'ticket-resolved' => 'Resolved regional SLA ticket and recorded audit trail.',
        'benchmark-complete' => 'Completed 25-query <3s retrieval and 5M-record warranty benchmark.',
    ],
];
