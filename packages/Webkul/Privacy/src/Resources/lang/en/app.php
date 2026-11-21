<?php

return [
    'acl' => [
        'privacy' => 'Privacy requests',
    ],
    'panel' => [
        'title' => 'Privacy requests',
        'intro' => 'Answer this person\'s request to see or delete their data.',
        'export' => 'Download their data (JSON)',
        'erase-title' => 'Erase this person',
        'erase-warning' => 'Removes their name, contact details, emails, attachments, notes, customer facts and their details in the change ledger. Deals and quotes are kept. This cannot be undone.',
        'confirm-label' => 'Type :name to confirm',
        'erase-button' => 'Erase permanently',
        'erased-on' => 'This person\'s personal data was erased on :date.',
    ],
    'erased-success' => 'Personal data erased. Deals and quotes were kept.',
    'confirm-mismatch' => 'The name you typed does not match. Nothing was erased.',
    'already-erased' => 'This person was already erased.',
];
