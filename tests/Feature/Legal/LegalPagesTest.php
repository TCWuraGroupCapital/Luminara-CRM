<?php

use Webkul\Legal\Support\LegalDocuments;

/**
 * Each test writes its own texts into a throwaway folder, so nothing depends on what an
 * operator keeps in storage/app/legal.
 */
beforeEach(function () {
    $this->legalDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'legal-test-'.bin2hex(random_bytes(4));
    mkdir($this->legalDir);

    config(['legal.path' => $this->legalDir, 'legal.enabled' => true, 'legal.version' => '2026-12-01']);
});

afterEach(function () {
    array_map('unlink', glob($this->legalDir.DIRECTORY_SEPARATOR.'*') ?: []);
    @rmdir($this->legalDir);
});

it('answers 404 for every legal page while the pages are off', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\nText.");
    config(['legal.enabled' => false]);

    foreach (['/terms', '/privacy', '/legal/dpa', '/legal/subprocessors'] as $path) {
        $this->get($path)->assertNotFound();
    }

    expect(LegalDocuments::requiresAcceptance())->toBeFalse();
});

it('renders the operator text with its version and links to the other pages', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms of Service\n\n## 1. The service\n\nWe host **your** CRM.");
    file_put_contents($this->legalDir.'/privacy.md', "# Privacy Policy\n\nWe keep little.");

    $this->get('/terms')
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=300, public')
        ->assertSee('<h2>1. The service</h2>', false)
        ->assertSee('<strong>your</strong>', false)
        ->assertSee(trans('legal::app.version', ['version' => '2026-12-01']))
        ->assertSee(route('legal.privacy'), false);

    $this->get('/privacy')->assertOk()->assertSee('We keep little.');
});

it('answers 404 for a page whose text is missing or empty', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\nText.");
    file_put_contents($this->legalDir.'/dpa.md', "   \n");

    $this->get('/terms')->assertOk();
    $this->get('/privacy')->assertNotFound();
    $this->get('/legal/dpa')->assertNotFound();
});

it('escapes raw HTML and drops unsafe links in the text', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\n<script>alert(1)</script>\n\n[click](javascript:alert(2))");

    $this->get('/terms')
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('javascript:alert(2)', false);
});

it('records a version label plus a hash that changes when the wording changes', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\nVersion one.");
    file_put_contents($this->legalDir.'/privacy.md', "# Privacy\n\nText.");

    $first = LegalDocuments::acceptanceVersion();

    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\nVersion two.");

    expect($first)->toStartWith('2026-12-01#')
        ->and(strlen($first))->toBeLessThanOrEqual(64)
        ->and(LegalDocuments::acceptanceVersion())->not->toBe($first)
        ->and(LegalDocuments::requiresAcceptance())->toBeTrue();
});

it('shows the legal links on the sign-in page only when the pages are on', function () {
    file_put_contents($this->legalDir.'/terms.md', "# Terms\n\nText.");

    $this->get(route('admin.session.create'))->assertOk()->assertSee(route('legal.terms'), false);

    config(['legal.enabled' => false]);

    $this->get(route('admin.session.create'))->assertOk()->assertDontSee(route('legal.terms'), false);
});
