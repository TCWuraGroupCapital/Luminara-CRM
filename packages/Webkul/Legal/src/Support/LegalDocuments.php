<?php

namespace Webkul\Legal\Support;

use Illuminate\Support\Str;

/**
 * The operator's own legal texts, kept as Markdown files outside the code so each install
 * publishes its own wording. Nothing here ships legal text.
 */
class LegalDocuments
{
    /**
     * Document key => file name. The keys are the only documents that can ever be served.
     */
    public const FILES = [
        'terms' => 'terms.md',
        'privacy' => 'privacy.md',
        'dpa' => 'dpa.md',
        'subprocessors' => 'subprocessors.md',
    ];

    public static function enabled(): bool
    {
        return (bool) config('legal.enabled');
    }

    /**
     * Sign-up asks for acceptance only when the pages are on and there are Terms to accept.
     */
    public static function requiresAcceptance(): bool
    {
        return self::enabled() && self::exists('terms');
    }

    public static function exists(string $document): bool
    {
        return self::markdown($document) !== null;
    }

    public static function markdown(string $document): ?string
    {
        $file = self::FILES[$document] ?? null;

        if ($file === null) {
            return null;
        }

        $path = rtrim((string) config('legal.path'), '/\\').DIRECTORY_SEPARATOR.$file;

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $markdown = (string) file_get_contents($path);

        return trim($markdown) === '' ? null : $markdown;
    }

    /**
     * Markdown to HTML with raw HTML escaped and javascript: style links dropped, so a text
     * file can never inject script into the page.
     */
    public static function html(string $document): ?string
    {
        $markdown = self::markdown($document);

        if ($markdown === null) {
            return null;
        }

        return (string) Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public static function title(string $document): string
    {
        $markdown = (string) self::markdown($document);

        if (preg_match('/^#\s+(.+)$/m', $markdown, $match) === 1) {
            return trim($match[1]);
        }

        return trans('legal::app.titles.'.$document);
    }

    /**
     * What a sign-up records as accepted: the configured label plus a short hash of the Terms
     * and Privacy Policy as they read at that moment.
     */
    public static function acceptanceVersion(): string
    {
        $label = trim((string) config('legal.version')) ?: 'unversioned';

        $hash = substr(hash('sha256', (string) self::markdown('terms')."\0".(string) self::markdown('privacy')), 0, 12);

        return Str::limit($label, 48, '').'#'.$hash;
    }
}
