<?php

use Webkul\Security\Http\Middleware\ContentSecurityPolicy;

it('sends a content security policy with HTML pages', function () {
    $policy = $this->get(route('admin.session.create'))->assertOk()->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'")
        ->toContain('https://cdn.jsdelivr.net');
});

it('switches to report-only or off with SECURITY_CSP', function () {
    config(['security.csp' => 'report-only']);

    $response = $this->get(route('admin.session.create'));

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("object-src 'none'");

    config(['security.csp' => 'off']);

    $response = $this->get(route('admin.session.create'));

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('adds extra form targets and leaves JSON responses alone', function () {
    config(['security.csp_form_action_extra' => 'https://login.example-idp.test']);

    expect((new ContentSecurityPolicy)->policy())->toContain("form-action 'self' https://login.example-idp.test");

    $json = $this->getJson('/.well-known/agent.json');

    expect($json->headers->has('Content-Security-Policy'))->toBeFalse();
});
