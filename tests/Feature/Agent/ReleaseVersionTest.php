<?php

use Webkul\Core\Core;

/**
 * Agents read the server version from the discovery card to decide what they can rely on, so it
 * reports Luminara's own release rather than the Krayin version it was forked from.
 */
it('reports the Luminara release on the agent discovery card', function () {
    expect(Core::LUMINARA_VERSION)->toMatch('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/');

    expect(core()->luminaraVersion())->toBe(Core::LUMINARA_VERSION)
        ->and(core()->version())->toBe(Core::KRAYIN_VERSION);

    $this->getJson('/.well-known/agent.json')
        ->assertOk()
        ->assertJsonPath('version', Core::LUMINARA_VERSION);
});
