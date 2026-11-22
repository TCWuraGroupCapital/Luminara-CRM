<?php

namespace Webkul\Security\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Shared password rule for every place a person sets a password.
 *
 * Minimum 12 characters, at least one letter, mixed case, and a number.
 * Outside the test suite the password is also checked against the Have I
 * Been Pwned range API. That network call is skipped when APP_ENV is testing.
 */
class PasswordPolicy
{
    public static function register(): void
    {
        Password::defaults(function () {
            return self::make(! app()->environment('testing'));
        });
    }

    public static function make(bool $rejectCompromised): Password
    {
        $rule = Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers();

        if ($rejectCompromised) {
            $rule->uncompromised();
        }

        return $rule;
    }

    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            Password::defaults(),
        ];
    }
}
