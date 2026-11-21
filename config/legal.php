<?php

return [
    /**
     * Off by default: /terms, /privacy, /legal/dpa and /legal/subprocessors answer 404 and
     * sign-up asks for no acceptance. Turn on only once the texts have been reviewed.
     */
    'enabled' => (bool) env('LUMINARA_LEGAL_PAGES', false),

    /**
     * A label for the current texts, shown on each page and stored with every sign-up's
     * acceptance (for example 2026-12-01). A hash of the Terms and Privacy Policy is stored
     * with it, so the record shows exactly which wording was accepted.
     */
    'version' => env('LUMINARA_LEGAL_VERSION', ''),

    /**
     * Folder holding the operator's own texts as Markdown: terms.md, privacy.md, dpa.md and
     * subprocessors.md. A missing file means that page answers 404. The default sits on the
     * storage volume, so the texts survive image rebuilds and are part of off-site backups.
     */
    'path' => env('LUMINARA_LEGAL_PATH', storage_path('app/legal')),
];
