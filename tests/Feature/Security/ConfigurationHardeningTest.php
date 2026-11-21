<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(DatabaseTransactions::class);

/**
 * Found by an authenticated OWASP ZAP scan: the scanner saved "c:/Windows/system.ini" as the
 * locale, and from then on every page, the sign-in page included, failed with "Invalid
 * characters present in locale".
 */
function storeLocaleSetting(string $value): void
{
    DB::table('core_config')->where('code', 'general.general.locale_settings.locale')->delete();
    DB::table('core_config')->insert([
        'code' => 'general.general.locale_settings.locale',
        'value' => $value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function postLocale(string $value): TestResponse
{
    return test()->actingAs(getDefaultAdmin(), 'user')
        ->post(route('admin.configuration.store', ['slug' => 'general', 'slug2' => 'general']), [
            'keys' => [json_encode(['key' => 'general.general.locale_settings', 'fields' => [['name' => 'locale']]])],
            'general' => ['general' => ['locale_settings' => ['locale' => $value]]],
        ]);
}

it('keeps serving pages when the stored locale is not a real locale', function () {
    storeLocaleSetting('c:/Windows/system.ini');

    $this->get(route('admin.session.create'))->assertOk();

    expect(app()->getLocale())->toBe(config('app.locale'));
});

it('applies a stored locale the app ships', function () {
    storeLocaleSetting('es');

    $this->get(route('admin.session.create'))->assertOk();

    expect(app()->getLocale())->toBe('es');
});

it('refuses a select setting outside its own options', function () {
    postLocale('c:/Windows/system.ini')->assertSessionHasErrors('general.general.locale_settings.locale');

    expect(DB::table('core_config')->where('code', 'general.general.locale_settings.locale')->value('value'))
        ->not->toBe('c:/Windows/system.ini');

    postLocale('ja')->assertSessionHasNoErrors();

    expect(DB::table('core_config')->where('code', 'general.general.locale_settings.locale')->value('value'))->toBe('ja');
});
